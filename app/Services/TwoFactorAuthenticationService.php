<?php

namespace App\Services;

use App\Models\TwoFactorAuthentication;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public const SETUP_LIFETIME_MINUTES = 10;

    public const RECOVERY_CODE_COUNT = 10;

    /** Google2FA defaults to RFC 6238 HMAC-SHA1 with six digits and a 30-second step. */
    private const TOTP_STEP_SECONDS = 30;

    private const CLOCK_DRIFT_STEPS = 1;

    public function beginSetup(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->canMaintainAuthenticatedSession()) {
                throw new DomainException('The account is not eligible for two-factor setup.');
            }

            $now = now();

            DB::table('user_two_factor_authentications')->insertOrIgnore([
                'user_id' => $lockedUser->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($security->isEnabled()) {
                throw new DomainException('Two-factor authentication is already enabled.');
            }

            $secret = app(Google2FA::class)->generateSecretKey();

            $security->forceFill([
                'pending_secret' => $secret,
                'pending_expires_at' => $now->addMinutes(self::SETUP_LIFETIME_MINUTES),
                'secret' => null,
                'enabled_at' => null,
                'last_used_step' => null,
            ])->save();
        });
    }

    public function isEnabled(User $user): bool
    {
        return $user->twoFactorAuthentication()
            ->whereNotNull('enabled_at')
            ->whereNotNull('secret')
            ->exists();
    }

    /**
     * @return list<string>|null
     */
    public function confirmSetup(User $user, #[\SensitiveParameter] string $code): ?array
    {
        return DB::transaction(function () use ($user, $code): ?array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->canMaintainAuthenticatedSession()) {
                return null;
            }

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $security instanceof TwoFactorAuthentication
                || $security->isEnabled()
                || ! is_string($security->pending_secret)
                || $security->pending_expires_at === null
                || $security->pending_expires_at->isPast()
            ) {
                return null;
            }

            $usedStep = $this->verifyTotpStep($security->pending_secret, $code, null);

            if ($usedStep === null) {
                return null;
            }

            $sessionVersion = $lockedUser->authSessionVersion();

            if ($sessionVersion === null || $sessionVersion === PHP_INT_MAX) {
                throw new DomainException('The account authentication state is invalid.');
            }

            $now = now();
            $recoveryCodes = $this->generateRecoveryCodes();

            $security->forceFill([
                'secret' => $security->pending_secret,
                'pending_secret' => null,
                'pending_expires_at' => null,
                'enabled_at' => $now,
                'last_used_step' => $usedStep,
            ])->save();

            $security->recoveryCodes()->delete();
            $this->persistRecoveryCodes($lockedUser, $recoveryCodes, $now);

            $lockedUser->forceFill([
                'auth_session_version' => $sessionVersion + 1,
                'remember_token' => Str::random(60),
            ])->save();

            return $recoveryCodes;
        });
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user): array {
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
            ) {
                throw new DomainException('Two-factor authentication is not enabled for this account.');
            }

            $codes = $this->generateRecoveryCodes();
            $security->recoveryCodes()->delete();
            $this->persistRecoveryCodes($lockedUser, $codes, now());

            return $codes;
        });
    }

    public function disable(User $user, #[\SensitiveParameter] string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
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
            ) {
                return false;
            }

            $usedStep = $this->verifyTotpStep($security->secret, $code, $security->last_used_step);

            if ($usedStep === null) {
                return false;
            }

            $sessionVersion = $lockedUser->authSessionVersion();

            if ($sessionVersion === null || $sessionVersion === PHP_INT_MAX) {
                throw new DomainException('The account authentication state is invalid.');
            }

            $security->recoveryCodes()->delete();
            $security->forceFill([
                'secret' => null,
                'pending_secret' => null,
                'pending_expires_at' => null,
                'enabled_at' => null,
                'last_used_step' => null,
            ])->save();

            $lockedUser->forceFill([
                'auth_session_version' => $sessionVersion + 1,
                'remember_token' => Str::random(60),
            ])->save();

            return true;
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return $user->twoFactorAuthentication()
            ->first()
            ?->recoveryCodes()
            ->count() ?? 0;
    }

    public function verifyTotpStep(
        #[\SensitiveParameter] string $secret,
        #[\SensitiveParameter] string $code,
        ?int $lastUsedStep,
    ): ?int {
        if (! preg_match('/\A[0-9]{6}\z/', $code)) {
            return null;
        }

        $step = app(Google2FA::class)->verifyKeyNewer(
            $secret,
            $code,
            $lastUsedStep ?? -1,
            self::CLOCK_DRIFT_STEPS,
            intdiv(now()->timestamp, self::TOTP_STEP_SECONDS),
        );

        return is_int($step) ? $step : null;
    }

    /** Recovery codes have 128 bits of entropy, so SHA-256 remains safe across app-key rotation. */
    public function recoveryCodeDigest(#[\SensitiveParameter] string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return array_map(
            static fn (): string => strtoupper(bin2hex(random_bytes(16))),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    /**
     * @param  list<string>  $codes
     */
    private function persistRecoveryCodes(User $user, #[\SensitiveParameter] array $codes, mixed $now): void
    {
        foreach ($codes as $code) {
            TwoFactorRecoveryCode::query()->create([
                'user_id' => $user->getKey(),
                'code_hash' => $this->recoveryCodeDigest($code),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
