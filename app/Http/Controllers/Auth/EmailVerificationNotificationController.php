<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationOtpResult;
use App\Services\EmailVerificationOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, EmailVerificationOtpService $otpService): RedirectResponse
    {
        $result = $otpService->issue($request->user());

        if ($result === EmailVerificationOtpResult::AlreadyVerified) {
            return redirect()->intended(route('dashboard', absolute: false))
                ->with('verification_status', 'already_verified');
        }

        $status = match ($result) {
            EmailVerificationOtpResult::Sent => 'sent',
            EmailVerificationOtpResult::Cooldown => 'cooldown',
            EmailVerificationOtpResult::DeliveryFailed => 'mail_failed',
            default => 'mail_failed',
        };

        return back()->with('verification_status', $status);
    }
}
