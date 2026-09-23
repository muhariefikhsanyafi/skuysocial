<?php

namespace App\Services;

use App\Models\EmailVerificationOtp;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use DateTimeInterface;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class EmailVerificationOtpService
{
    private const CODE_LIFETIME_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EmailVerificationOtpCodeGenerator $codeGenerator,
    ) {}

    public function issue(User $user): EmailVerificationOtpResult
    {
        $deliveryException = null;

        try {
            return DB::transaction(function () use ($user, &$deliveryException): EmailVerificationOtpResult {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->hasVerifiedEmail()) {
                    return EmailVerificationOtpResult::AlreadyVerified;
                }

                $otp = EmailVerificationOtp::query()
                    ->where('user_id', $lockedUser->getKey())
                    ->lockForUpdate()
                    ->first();
                $now = now();

                if (
                    $otp !== null
                    && $otp->sent_at->greaterThan($now->copy()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
                ) {
                    return EmailVerificationOtpResult::Cooldown;
                }

                if ($this->mailerMayLogMessages()) {
                    $deliveryException = new LogicException('Verification codes cannot use a logging mail transport.');
                    throw $deliveryException;
                }

                do {
                    $code = $this->codeGenerator->generate();
                    $codeDigest = $this->digest($lockedUser, $code);
                } while ($otp !== null && hash_equals($otp->code_digest, $codeDigest));

                $otp ??= new EmailVerificationOtp;
                $otp->forceFill([
                    'user_id' => $lockedUser->getKey(),
                    'code_digest' => $codeDigest,
                    'attempt_count' => 0,
                    'expires_at' => $now->copy()->addMinutes(self::CODE_LIFETIME_MINUTES),
                    'sent_at' => $now,
                ])->save();

                try {
                    Notification::sendNow($lockedUser, new EmailVerificationCode($code));
                } catch (Throwable $exception) {
                    $deliveryException = $exception;
                    throw $exception;
                }

                $sentAt = now();
                $otp->forceFill([
                    'expires_at' => $sentAt->copy()->addMinutes(self::CODE_LIFETIME_MINUTES),
                    'sent_at' => $sentAt,
                ])->save();

                return EmailVerificationOtpResult::Sent;
            });
        } catch (Throwable $exception) {
            if ($exception !== $deliveryException) {
                throw $exception;
            }

            Log::warning('Email verification code delivery failed.', [
                'user_id' => $user->getKey(),
                'exception' => $deliveryException::class,
            ]);

            return EmailVerificationOtpResult::DeliveryFailed;
        }
    }

    public function verify(User $user, string $code): EmailVerificationOtpResult
    {
        $verifiedUser = null;
        $result = DB::transaction(function () use ($user, $code, &$verifiedUser): EmailVerificationOtpResult {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->hasVerifiedEmail()) {
                return EmailVerificationOtpResult::AlreadyVerified;
            }

            $otp = EmailVerificationOtp::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->expires_at->lessThanOrEqualTo(now())) {
                return EmailVerificationOtpResult::Expired;
            }

            if ($otp->attempt_count >= self::MAX_ATTEMPTS) {
                return EmailVerificationOtpResult::AttemptLimit;
            }

            $matches = preg_match('/\A[0-9]{6}\z/', $code) === 1
                && hash_equals($otp->code_digest, $this->digest($lockedUser, $code));

            if (! $matches) {
                $otp->attempt_count++;
                $otp->save();

                return $otp->attempt_count >= self::MAX_ATTEMPTS
                    ? EmailVerificationOtpResult::AttemptLimit
                    : EmailVerificationOtpResult::Invalid;
            }

            // Discard any persistent recaller issued before the account was verified.
            $lockedUser->setRememberToken(Str::random(60));

            if (! $lockedUser->markEmailAsVerified()) {
                throw new LogicException('Unable to persist email verification.');
            }

            $otp->delete();
            $verifiedUser = $lockedUser;

            return EmailVerificationOtpResult::Verified;
        });

        if ($result === EmailVerificationOtpResult::Verified && $verifiedUser instanceof User) {
            event(new Verified($verifiedUser));
        }

        return $result;
    }

    public function cooldownSeconds(User $user): int
    {
        $sentAt = EmailVerificationOtp::query()
            ->where('user_id', $user->getKey())
            ->value('sent_at');

        if (! is_string($sentAt) && ! ($sentAt instanceof DateTimeInterface)) {
            return 0;
        }

        $availableAt = Carbon::parse($sentAt)->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return max(0, $availableAt->getTimestamp() - now()->getTimestamp());
    }

    private function mailerMayLogMessages(): bool
    {
        $defaultMailer = config('mail.default');
        $mailers = config('mail.mailers');

        if (
            ! is_string($defaultMailer)
            || ! is_array($mailers)
            || ! array_key_exists($defaultMailer, $mailers)
        ) {
            return true;
        }

        return $this->mailerChainMayLog($defaultMailer, $mailers, []);
    }

    /**
     * Refuse log transports anywhere in the selected mailer or its failover chain.
     *
     * @param  array<string, mixed>  $mailers
     * @param  array<string, true>  $path
     */
    private function mailerChainMayLog(string $name, array $mailers, array $path): bool
    {
        if (isset($path[$name])) {
            return true;
        }

        $path[$name] = true;
        $mailer = $mailers[$name] ?? null;

        if (! is_array($mailer)) {
            return true;
        }

        $transport = $mailer['transport'] ?? null;

        if ($transport === 'log') {
            return true;
        }

        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return false;
        }

        $alternatives = $mailer['mailers'] ?? null;

        if (! is_array($alternatives) || $alternatives === []) {
            return true;
        }

        foreach ($alternatives as $alternative) {
            if (
                ! is_string($alternative)
                || $this->mailerChainMayLog($alternative, $mailers, $path)
            ) {
                return true;
            }
        }

        return false;
    }

    private function digest(User $user, string $code): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('Application key is required for email verification code digests.');
        }

        $message = implode("\0", [
            (string) $user->getKey(),
            (string) $user->getEmailForVerification(),
            $code,
        ]);

        return hash_hmac('sha256', $message, $key);
    }
}
