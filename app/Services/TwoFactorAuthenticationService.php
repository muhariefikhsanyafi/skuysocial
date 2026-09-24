<?php

namespace App\Services;

use App\Models\TwoFactorAuthentication;
use App\Models\TwoFactorRecoveryCode;
use App\Models\TwoFactorRecoveryCodeHandoff;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public const SETUP_LIFETIME_MINUTES = 10;

    public const RECOVERY_CODE_COUNT = 10;

    public const RECOVERY_CODE_HANDOFF_LIFETIME_MINUTES = 10;

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
        return $this->completeSetup($user, $code, null, null);
    }

    public function confirmSetupForDisplay(
        User $user,
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $operationToken,
        #[\SensitiveParameter] string $sessionBinding,
    ): bool {
        return is_array($this->completeSetup($user, $code, $operationToken, $sessionBinding));
    }

    /**
     * @return list<string>|null
     */
    private function completeSetup(
        User $user,
        #[\SensitiveParameter] string $code,
        ?string $operationToken,
        ?string $sessionBinding,
    ): ?array {
        return DB::transaction(function () use ($user, $code, $operationToken, $sessionBinding): ?array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->canMaintainAuthenticatedSession()) {
                return null;
            }

            if ($operationToken !== null && $sessionBinding !== null) {
                $existing = $this->existingHandoffResult($lockedUser, $operationToken, $sessionBinding);

                if ($existing !== null) {
                    return $existing ? [] : null;
                }
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

            if ($operationToken !== null && $sessionBinding !== null) {
                $this->invalidatePendingRecoveryCodeHandoffs($lockedUser);
                $this->createRecoveryCodeHandoff(
                    $lockedUser,
                    $operationToken,
                    $sessionBinding,
                    'setup',
                    $recoveryCodes,
                    $now,
                );
            }

            return $recoveryCodes;
        });
    }

    /**
     * @return array{codes: list<string>, purpose: string}|null
     */
    public function consumeRecoveryCodeHandoff(
        User $user,
        #[\SensitiveParameter] string $operationToken,
        #[\SensitiveParameter] string $sessionBinding,
    ): ?array {
        return DB::transaction(function () use ($user, $operationToken, $sessionBinding): ?array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->canMaintainAuthenticatedSession()) {
                return null;
            }

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if (! $security instanceof TwoFactorAuthentication || ! $security->isEnabled()) {
                return null;
            }

            $handoff = TwoFactorRecoveryCodeHandoff::query()
                ->where('operation_digest', hash('sha256', $operationToken))
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $handoff instanceof TwoFactorRecoveryCodeHandoff
                || ! hash_equals($handoff->session_digest, hash('sha256', $sessionBinding))
                || $handoff->consumed_at !== null
                || ! is_string($handoff->encrypted_payload)
            ) {
                return null;
            }

            if ($handoff->expires_at->lessThanOrEqualTo(now())) {
                $this->consumeAndClearHandoff($handoff);

                return null;
            }

            try {
                $codes = json_decode(
                    Crypt::decryptString($handoff->encrypted_payload),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (\Illuminate\Contracts\Encryption\DecryptException|\JsonException) {
                $this->consumeAndClearHandoff($handoff);

                return null;
            }

            $invalidCodes = ! is_array($codes) || ! array_is_list($codes) || count($codes) !== self::RECOVERY_CODE_COUNT;

            if (! $invalidCodes) {
                foreach ($codes as $recoveryCode) {
                    if (! is_string($recoveryCode) || ! preg_match('/\A[A-F0-9]{32}\z/', $recoveryCode)) {
                        $invalidCodes = true;
                        break;
                    }
                }
            }

            if ($invalidCodes || ! in_array($handoff->purpose, ['setup', 'regeneration'], true)) {
                $this->consumeAndClearHandoff($handoff);

                return null;
            }

            $purpose = $handoff->purpose;
            $this->consumeAndClearHandoff($handoff);

            return ['codes' => $codes, 'purpose' => $purpose];
        });
    }

    public function regenerateRecoveryCodes(
        User $user,
        #[\SensitiveParameter] string $operationToken,
        #[\SensitiveParameter] string $sessionBinding,
    ): bool {
        return DB::transaction(function () use ($user, $operationToken, $sessionBinding): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->canMaintainAuthenticatedSession()) {
                throw new DomainException('The account is not eligible for recovery code regeneration.');
            }

            $existing = $this->existingHandoffResult($lockedUser, $operationToken, $sessionBinding);

            if ($existing !== null) {
                return $existing;
            }

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if (! $security instanceof TwoFactorAuthentication || ! $security->isEnabled()) {
                throw new DomainException('Two-factor authentication is not enabled for this account.');
            }

            $now = now();
            $codes = $this->generateRecoveryCodes();
            $security->recoveryCodes()->delete();
            $this->persistRecoveryCodes($lockedUser, $codes, $now);
            $this->invalidatePendingRecoveryCodeHandoffs($lockedUser);
            $this->createRecoveryCodeHandoff(
                $lockedUser,
                $operationToken,
                $sessionBinding,
                'regeneration',
                $codes,
                $now,
            );

            return true;
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
            $this->invalidatePendingRecoveryCodeHandoffs($lockedUser);
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

    private function existingHandoffResult(
        User $user,
        #[\SensitiveParameter] string $operationToken,
        #[\SensitiveParameter] string $sessionBinding,
    ): ?bool {
        $handoff = TwoFactorRecoveryCodeHandoff::query()
            ->where('operation_digest', hash('sha256', $operationToken))
            ->lockForUpdate()
            ->first();

        if (! $handoff instanceof TwoFactorRecoveryCodeHandoff) {
            return null;
        }

        if (
            $handoff->user_id !== $user->getKey()
            || ! hash_equals($handoff->session_digest, hash('sha256', $sessionBinding))
        ) {
            return false;
        }

        if (
            $handoff->consumed_at !== null
            || $handoff->expires_at->lessThanOrEqualTo(now())
            || ! is_string($handoff->encrypted_payload)
        ) {
            if ($handoff->consumed_at === null && is_string($handoff->encrypted_payload)) {
                $this->consumeAndClearHandoff($handoff);
            }

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $codes
     */
    private function createRecoveryCodeHandoff(
        User $user,
        #[\SensitiveParameter] string $operationToken,
        #[\SensitiveParameter] string $sessionBinding,
        string $purpose,
        #[\SensitiveParameter] array $codes,
        mixed $now,
    ): void {
        TwoFactorRecoveryCodeHandoff::query()
            ->where('user_id', $user->getKey())
            ->where('expires_at', '<=', $now)
            ->delete();

        TwoFactorRecoveryCodeHandoff::query()->create([
            'user_id' => $user->getKey(),
            'operation_digest' => hash('sha256', $operationToken),
            'session_digest' => hash('sha256', $sessionBinding),
            'purpose' => $purpose,
            'encrypted_payload' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
            'expires_at' => $now->copy()->addMinutes(self::RECOVERY_CODE_HANDOFF_LIFETIME_MINUTES),
            'consumed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function invalidatePendingRecoveryCodeHandoffs(User $user): void
    {
        TwoFactorRecoveryCodeHandoff::query()
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->update([
                'encrypted_payload' => null,
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function consumeAndClearHandoff(TwoFactorRecoveryCodeHandoff $handoff): void
    {
        $handoff->forceFill([
            'encrypted_payload' => null,
            'consumed_at' => now(),
        ])->save();
    }
}
