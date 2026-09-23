<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationOtpResult;
use App\Services\EmailVerificationOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailOtpController extends Controller
{
    public function __invoke(Request $request, EmailVerificationOtpService $otpService): RedirectResponse
    {
        $submittedCode = $request->input('code');
        $code = is_string($submittedCode) ? $submittedCode : '';
        $result = $otpService->verify($request->user(), $code);

        if ($result === EmailVerificationOtpResult::Verified) {
            $request->user()->refresh();

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1')
                ->with('verification_status', 'verified');
        }

        if ($result === EmailVerificationOtpResult::AlreadyVerified) {
            return redirect()->intended(route('dashboard', absolute: false))
                ->with('verification_status', 'already_verified');
        }

        $status = match ($result) {
            EmailVerificationOtpResult::Invalid => 'invalid',
            EmailVerificationOtpResult::Expired => 'expired',
            EmailVerificationOtpResult::AttemptLimit => 'attempt_limit',
            default => 'invalid',
        };

        return back()->with('verification_status', $status);
    }
}
