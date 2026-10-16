<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\TwoFactorAuthentication;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\TwoFactorLoginChallengeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleAuthController extends Controller
{
    private const STATE_SESSION = 'google_oauth.authorization';
    private const REGISTRATION_SESSION = 'google_oauth.pending_registration';
    private const REGISTRATION_TTL = 600;
    private const GOOGLE_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USER = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function redirect(Request $request): RedirectResponse
    {
        return $this->authorizeGoogle($request, false);
    }

    public function link(Request $request): RedirectResponse
    {
        return $this->authorizeGoogle($request, true);
    }

    private function enabled(): bool
    {
        return config('services.google.enabled') === true
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function callbackUrl(): string
    {
        return (string) (config('services.google.redirect') ?: route('auth.google.callback'));
    }

    private function authorizeGoogle(Request $request, bool $link): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        if ($link && (! $request->user() instanceof User || ! $request->user()->hasVerifiedEmail())) {
            abort(403);
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $request->session()->forget(self::REGISTRATION_SESSION);
        $request->session()->put(self::STATE_SESSION, [
            'state' => $state,
            'verifier' => $verifier,
            'created_at' => time(),
            'intent' => $link ? 'link' : 'login',
            'user_id' => $link ? $request->user()->getKey() : null,
        ]);

        return redirect()->away(self::GOOGLE_AUTH.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(
        Request $request,
        LocalePreference $locales,
        TwoFactorLoginChallengeService $challenges,
    ): RedirectResponse {
        $pending = $request->session()->pull(self::STATE_SESSION);
        $expectedState = is_array($pending) ? ($pending['state'] ?? null) : null;
        $state = $request->query('state');
        $code = $request->query('code');

        if (
            ! $this->enabled()
            || ! is_string($expectedState)
            || ! is_string($state)
            || ! hash_equals($expectedState, $state)
            || ! is_int($pending['created_at'] ?? null)
            || time() - $pending['created_at'] > 600
            || time() < $pending['created_at']
            || ! in_array($pending['intent'] ?? null, ['login', 'link'], true)
            || ! is_string($pending['verifier'] ?? null)
            || ! is_string($code)
            || $code === ''
            || strlen($code) > 2048
        ) {
            return $this->fail();
        }

        $link = $pending['intent'] === 'link';
        if ($link && (! $request->user() instanceof User || $request->user()->getKey() !== $pending['user_id'])) {
            return $this->fail();
        }
        if (! $link && Auth::check()) {
            return $this->fail();
        }

        try {
            $token = Http::asForm()->acceptJson()->timeout(10)->post(self::GOOGLE_TOKEN, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->callbackUrl(),
                'grant_type' => 'authorization_code',
                'code_verifier' => $pending['verifier'],
            ]);

            if (! $token->successful() || ! is_string($accessToken = $token->json('access_token')) || $accessToken === '') {
                return $this->fail();
            }

            $profile = Http::withToken($accessToken)->acceptJson()->timeout(10)->get(self::GOOGLE_USER);

            if (! $profile->successful()) {
                return $this->fail();
            }

            $subject = $profile->json('sub');
            $email = $profile->json('email');
            $verified = $profile->json('email_verified');
            $name = $profile->json('name');

            if (
                ! is_string($subject) || $subject === '' || strlen($subject) > 255
                || ! is_string($email) || strlen($email) > 255
                || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || $verified !== true
            ) {
                return $this->fail();
            }

            $email = strtolower($email);

            if ($link) {
                return $this->linkAccount($request, $subject, $email);
            }

            return $this->loginAccount($request, $locales, $challenges, $subject, $email, $name);
        } catch (Throwable) {
            // Never render or log Google tokens/authorization codes.
            return $this->fail();
        }
    }

    private function linkAccount(Request $request, string $subject, string $email): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasVerifiedEmail() || strcasecmp($email, $user->email) !== 0) {
            return $this->fail();
        }

        $linked = DB::transaction(function () use ($user, $subject): bool {
            $account = User::query()->lockForUpdate()->find($user->getKey());
            if (
                ! $account instanceof User
                || ! $account->canMaintainAuthenticatedSession()
                || ($account->google_sub !== null && $account->google_sub !== $subject)
                || User::query()->where('google_sub', $subject)->whereKeyNot($account->getKey())->exists()
            ) {
                return false;
            }

            $account->forceFill(['google_sub' => $subject])->save();

            return true;
        });

        return $linked
            ? redirect()->route('user.edit', ['username' => $user->username])->with('google_linked', true)
            : $this->fail();
    }

    /**
     * Existing Google subjects sign in normally, including moderation and 2FA.
     * New subjects must choose a permanent SkuySocial username before any
     * account is created or authenticated.
     */
    private function loginAccount(
        Request $request,
        LocalePreference $locales,
        TwoFactorLoginChallengeService $challenges,
        string $subject,
        string $email,
        mixed $name,
    ): RedirectResponse {
        $user = User::query()->where('google_sub', $subject)->first();

        if (! $user instanceof User) {
            // Never attach Google to an existing password account by email.
            if (User::query()->where('email', $email)->exists()) {
                return $this->fail();
            }

            $target = $challenges->safeIntendedUrl($request);
            $request->session()->forget('url.intended');
            $request->session()->regenerate();
            $request->session()->put(self::REGISTRATION_SESSION, [
                'subject' => $subject,
                'email' => $email,
                'name' => is_string($name) && trim($name) !== ''
                    ? Str::limit(trim($name), 255, '')
                    : '',
                'created_at' => time(),
                'target' => $target,
            ]);

            return redirect()->route('auth.google.complete');
        }

        if (! $user->canAuthenticate() || $user->authSessionVersion() === null) {
            return $this->fail();
        }

        $target = $challenges->safeIntendedUrl($request);
        $request->session()->forget('url.intended');

        if ($user->hasVerifiedEmail() && TwoFactorAuthentication::query()
            ->where('user_id', $user->getKey())->first()?->isEnabled()) {
            $request->session()->regenerate();
            $challenges->begin($user, $request, false, 'web', $target);

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(EnforceAccountSession::SESSION_VERSION_KEY, $user->authSessionVersion());

        $locale = $locales->supported($user->locale) ?? $locales->currentLocale();
        $cookie = $locales->synchronize($request, $locale, $user);

        return redirect()->to(
            $user->hasVerifiedEmail() ? ($target ?? route('dashboard', absolute: false)) : route('verification.notice')
        )->withCookie($cookie);
    }

    /** @return array{subject: string, email: string, name: string, created_at: int, target: ?string}|null */
    private function pendingRegistration(Request $request): ?array
    {
        $pending = $request->session()->get(self::REGISTRATION_SESSION);

        if (
            ! is_array($pending)
            || ! is_int($pending['created_at'] ?? null)
            || $pending['created_at'] > time()
            || time() - $pending['created_at'] > self::REGISTRATION_TTL
            || ! is_string($pending['subject'] ?? null)
            || $pending['subject'] === ''
            || strlen($pending['subject']) > 255
            || ! is_string($pending['email'] ?? null)
            || ! filter_var($pending['email'], FILTER_VALIDATE_EMAIL)
            || strlen($pending['email']) > 255
            || ! is_string($pending['name'] ?? null)
            || ! (is_null($pending['target'] ?? null) || is_string($pending['target']))
        ) {
            $request->session()->forget(self::REGISTRATION_SESSION);

            return null;
        }

        return $pending;
    }

    public function complete(Request $request): View|RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $pending = $this->pendingRegistration($request);

        return $pending === null
            ? $this->fail()
            : view('auth.google-complete', ['pending' => $pending]);
    }

    public function storeCompleted(Request $request, LocalePreference $locales): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $pending = $this->pendingRegistration($request);
        if ($pending === null) {
            return $this->fail();
        }

        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower($request->input('username'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users', 'regex:/^[a-zA-Z0-9_.]+$/'],
        ]);

        try {
            $user = DB::transaction(function () use ($pending, $validated, $locales): ?User {
                // Recheck identity and email to close races with other signups.
                if (
                    User::query()->where('google_sub', $pending['subject'])->exists()
                    || User::query()->where('email', $pending['email'])->exists()
                ) {
                    return null;
                }

                $user = new User([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $pending['email'],
                    'password' => Hash::make(Str::random(64)),
                ]);
                $user->forceFill([
                    'google_sub' => $pending['subject'],
                    'email_verified_at' => now(),
                    'locale' => $locales->currentLocale(),
                    'role' => UserRole::User->value,
                    'moderation_state' => ModerationState::Active->value,
                    'moderation_state_changed_at' => null,
                    'moderation_state_version' => 0,
                    'auth_session_version' => 0,
                ])->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $user = null;
        }

        if (! $user instanceof User) {
            $request->session()->forget(self::REGISTRATION_SESSION);

            return $this->fail();
        }

        $request->session()->forget(self::REGISTRATION_SESSION);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(EnforceAccountSession::SESSION_VERSION_KEY, $user->authSessionVersion());

        $locale = $locales->supported($user->locale) ?? $locales->currentLocale();
        $cookie = $locales->synchronize($request, $locale, $user);

        return redirect()->to($pending['target'] ?? route('dashboard', absolute: false))->withCookie($cookie);
    }

    public function cancelRegistration(Request $request): RedirectResponse
    {
        $request->session()->forget(self::REGISTRATION_SESSION);

        return redirect()->route('login');
    }

    private function fail(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => __('google_auth.failed')]);
    }
}
