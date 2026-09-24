<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ModerationState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TwoFactorAuthentication;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\TwoFactorLoginChallengeService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        LocalePreference $locales,
        TwoFactorLoginChallengeService $challenges,
    ): RedirectResponse {
        $effectiveGuestLocale = $locales->currentLocale();
        $candidate = $request->authenticate();
        $remember = $request->shouldRemember();
        $credentials = ['password' => $request->string('password')->toString()];
        $intendedUrl = $challenges->safeIntendedUrl($request);

        try {
            $loginState = DB::transaction(function () use ($candidate, $request, $remember, $credentials, $intendedUrl, $challenges): array {
                $user = User::query()->lockForUpdate()->find($candidate->getKey());

                if (! $user instanceof User || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
                    return ['status' => 'failed'];
                }

                $moderationState = $user->moderationState();

                if (! $moderationState?->allowsAuthentication()) {
                    return ['status' => match ($moderationState) {
                        ModerationState::Suspended => 'suspended',
                        ModerationState::Banned => 'banned',
                        default => 'failed',
                    }];
                }

                Auth::guard('web')->getProvider()->rehashPasswordIfRequired($user, $credentials);

                if (! $user->hasVerifiedEmail()) {
                    Auth::guard('web')->login($user, false);
                    $request->session()->regenerate();

                    return ['status' => 'unverified', 'user' => $user];
                }

                $security = TwoFactorAuthentication::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($security instanceof TwoFactorAuthentication && $security->isEnabled()) {
                    $request->session()->forget('url.intended');
                    $request->session()->regenerate();
                    $challenges->begin($user, $request, $remember, 'web', $intendedUrl);

                    return ['status' => 'challenge'];
                }

                Auth::guard('web')->login($user, $remember);
                $request->session()->regenerate();

                return ['status' => 'authenticated', 'user' => $user];
            });
        } catch (DomainException) {
            $state = $candidate->fresh()?->moderationState();

            return back()->withErrors([
                'email' => match ($state) {
                    ModerationState::Suspended => trans('moderation.login.suspended'),
                    ModerationState::Banned => trans('moderation.login.banned'),
                    default => trans('auth.failed'),
                },
            ])->onlyInput('email');
        }

        if ($loginState['status'] === 'challenge') {
            return redirect()->route('two-factor.challenge');
        }

        if (in_array($loginState['status'], ['failed', 'suspended', 'banned'], true)) {
            return back()->withErrors([
                'email' => match ($loginState['status']) {
                    'suspended' => trans('moderation.login.suspended'),
                    'banned' => trans('moderation.login.banned'),
                    default => trans('auth.failed'),
                },
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = $loginState['user'];

        return $this->completeLogin($request, $locales, $user, $effectiveGuestLocale);
    }

    private function completeLogin(
        Request $request,
        LocalePreference $locales,
        User $user,
        string $effectiveGuestLocale,
    ): RedirectResponse {
        $user->refresh();
        $locale = $locales->supported($user->locale) ?? $effectiveGuestLocale;
        $cookie = $locales->synchronize($request, $locale, $user);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')->withCookie($cookie);
        }

        return redirect()->intended(route('dashboard', absolute: false))->withCookie($cookie);
    }

    public function destroy(Request $request, TwoFactorLoginChallengeService $challenges): RedirectResponse
    {
        $challenges->clear($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
