<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationOtpService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request, EmailVerificationOtpService $otpService): View
    {
        /** @var User $user */
        $user = $request->user();
        $otp = $user->emailVerificationOtp()->first();

        return view('auth.verify-email', [
            'alreadyVerified' => $user->hasVerifiedEmail(),
            'maskedEmail' => $this->maskEmail($user->email),
            'hasActiveCode' => $otp !== null
                && $otp->expires_at->isFuture()
                && $otp->attempt_count < 5,
            'codeExpired' => $otp !== null && $otp->expires_at->isPast(),
            'attemptLimitReached' => $otp !== null && $otp->attempt_count >= 5,
            'resendCooldownSeconds' => $otpService->cooldownSeconds($user),
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return '***';
        }

        return mb_substr($localPart, 0, 1).'***@'.$domain;
    }
}
