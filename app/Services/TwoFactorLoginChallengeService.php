<?php

namespace App\Services;

use App\Models\TwoFactorAuthentication;
use App\Models\TwoFactorLoginChallenge;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TwoFactorLoginChallengeService
{
    public const SESSION_KEY = 'auth.two_factor_login_challenge';

    private const CHALLENGE_LIFETIME_MINUTES = 10;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function begin(
        User $user,
        Request $request,
        bool $remember,
        string $source = 'web',
        ?string $intendedUrl = null,
    ): void {
        if (! in_array($source, ['web', 'admin'], true)) {
            throw new \DomainException('The two-factor login source is invalid.');
        }

        $token = Str::random(64);
        $sessionDigest = hash('sha256', $request->session()->getId());
        TwoFactorLoginChallenge::query()->where('expires_at', '<=', now())->delete();

        DB::transaction(function () use ($user, $source, $remember, $intendedUrl, $token, $sessionDigest): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $security = TwoFactorAuthentication::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedUser->hasVerifiedEmail()
                || ! $lockedUser->canMaintainAuthenticatedSession()
                || ! $security instanceof TwoFactorAuthentication
                || ! $security->isEnabled()
                || ($source === 'admin' && ! $lockedUser->isUsableAdministrator())
            ) {
                throw new \DomainException('The account is not eligible for a two-factor login challenge.');
            }

            $sessionVersion = $lockedUser->authSessionVersion();

            if ($sessionVersion === null) {
                throw new \DomainException('The account authentication state is invalid.');
            }

            $now = now();

            TwoFactorLoginChallenge::query()
                ->where('user_id', $lockedUser->getKey())
                ->delete();

            TwoFactorLoginChallenge::query()->create([
                'user_id' => $lockedUser->getKey(),
                'challenge_digest' => hash('sha256', $token),
                'session_digest' => $sessionDigest,
                'auth_session_version' => $sessionVersion,
                'remember' => $remember,
                'intended_url' => $source === 'admin' ? '/admin' : $intendedUrl,
                'source' => $source,
                'expires_at' => $now->addMinutes(self::CHALLENGE_LIFETIME_MINUTES),
            ]);
        });

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'user_id' => $user->getKey(),
            'source' => $source,
        ]);
    }

    public function active(Request $request): ?TwoFactorLoginChallenge
    {
        $state = $this->sessionState($request);

        if ($state === null) {
            return null;
        }

        return TwoFactorLoginChallenge::query()
            ->with('user')
            ->where('challenge_digest', hash('sha256', $state['token']))
            ->where('user_id', $state['user_id'])
            ->where('session_digest', hash('sha256', $request->session()->getId()))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function complete(Request $request, #[\SensitiveParameter] string $code): ?TwoFactorLoginChallenge
    {
        $state = $this->sessionState($request);

        if ($state === null) {
            return null;
        }

        $digest = hash('sha256', $state['token']);
        $sessionDigest = hash('sha256', $request->session()->getId());

        return DB::transaction(function () use ($state, $digest, $sessionDigest, $code): ?TwoFactorLoginChallenge {
            $user = User::query()->lockForUpdate()->find($state['user_id']);

            if (! $user instanceof User) {
                return null;
            }

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();
            $challenge = TwoFactorLoginChallenge::query()
                ->where('challenge_digest', $digest)
                ->lockForUpdate()
                ->first();

            if (
                ! $challenge instanceof TwoFactorLoginChallenge
                || $challenge->user_id !== $state['user_id']
                || ! hash_equals($challenge->session_digest, $sessionDigest)
                || $challenge->consumed_at !== null
                || $challenge->expires_at->isPast()
            ) {
                return null;
            }

            if (
                ! $user->hasVerifiedEmail()
                || ! $user->canMaintainAuthenticatedSession()
                || $user->authSessionVersion() !== $challenge->auth_session_version
                || ! $security instanceof TwoFactorAuthentication
                || ! $security->isEnabled()
                || ($challenge->source === 'admin' && ! $user->isUsableAdministrator())
            ) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return null;
            }

            if (preg_match('/\A[0-9]{6}\z/', $code)) {
                $usedStep = $this->twoFactor->verifyTotpStep(
                    $security->secret,
                    $code,
                    $security->last_used_step,
                );

                if (! is_int($usedStep)) {
                    return null;
                }

                $security->forceFill(['last_used_step' => $usedStep])->save();
            } else {
                $normalizedCode = strtoupper(str_replace(['-', ' '], '', trim($code)));

                if (! preg_match('/\A[A-F0-9]{32}\z/', $normalizedCode)) {
                    return null;
                }

                $recoveryCode = TwoFactorRecoveryCode::query()
                    ->where('user_id', $user->getKey())
                    ->where('code_hash', $this->twoFactor->recoveryCodeDigest($normalizedCode))
                    ->lockForUpdate()
                    ->first();

                if (! $recoveryCode instanceof TwoFactorRecoveryCode || ! $recoveryCode->delete()) {
                    return null;
                }
            }

            $challenge->forceFill(['consumed_at' => now()])->save();
            $challenge->setRelation('user', $user);

            return $challenge;
        });
    }

    public function clear(Request $request): ?string
    {
        $state = $request->session()->pull(self::SESSION_KEY);
        $source = is_array($state) && in_array($state['source'] ?? null, ['web', 'admin'], true)
            ? $state['source']
            : null;

        if (is_array($state) && is_string($state['token'] ?? null)) {
            TwoFactorLoginChallenge::query()
                ->where('challenge_digest', hash('sha256', $state['token']))
                ->where('session_digest', hash('sha256', $request->session()->getId()))
                ->delete();
        }

        return $source;
    }

    public function safeIntendedUrl(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || $intended === '' || str_contains($intended, '\\')) {
            return null;
        }

        $parts = parse_url($intended);

        if (! is_array($parts)) {
            return null;
        }

        if (isset($parts['host'])) {
            if (strtolower($parts['host']) !== strtolower($request->getHost())) {
                return null;
            }

            if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                return null;
            }
        } elseif (! str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path;
    }

    public function source(Request $request): ?string
    {
        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) && in_array($state['source'] ?? null, ['web', 'admin'], true)
            ? $state['source']
            : null;
    }

    /**
     * @return array{token: string, user_id: int}|null
     */
    private function sessionState(Request $request): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (
            ! is_array($state)
            || ! is_string($state['token'] ?? null)
            || ! is_numeric($state['user_id'] ?? null)
        ) {
            return null;
        }

        return [
            'token' => $state['token'],
            'user_id' => (int) $state['user_id'],
        ];
    }
}
