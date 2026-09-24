<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorAuthentication;
use App\Models\User;
use App\Services\EmailVerificationOtpResult;
use App\Services\EmailVerificationOtpService;
use App\Services\TwoFactorLoginChallengeService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyEmailOtpController extends Controller
{
    public function __invoke(
        Request $request,
        EmailVerificationOtpService $otpService,
        TwoFactorLoginChallengeService $challenges,
    ): RedirectResponse {
        $submittedCode = $request->input('code');
        $code = is_string($submittedCode) ? $submittedCode : '';

        try {
            $verification = DB::transaction(function () use ($request, $otpService, $code): array {
                $user = User::query()
                    ->lockForUpdate()
                    ->find($request->user()->getAuthIdentifier());

                if (! $user instanceof User) {
                    return [
                        'result' => EmailVerificationOtpResult::Invalid,
                        'requires_two_factor' => false,
                        'user' => null,
                    ];
                }

                $security = TwoFactorAuthentication::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                $result = $otpService->verify($user, $code);

                if (! in_array($result, [EmailVerificationOtpResult::Verified, EmailVerificationOtpResult::AlreadyVerified], true)) {
                    return [
                        'result' => $result,
                        'requires_two_factor' => false,
                        'user' => $user,
                    ];
                }

                $user->refresh();
                $requiresTwoFactor = $user->hasVerifiedEmail()
                    && $security instanceof TwoFactorAuthentication
                    && $security->isEnabled();

                if ($result === EmailVerificationOtpResult::Verified && $requiresTwoFactor) {
                    $sessionVersion = $user->authSessionVersion();

                    if ($sessionVersion === null || $sessionVersion === PHP_INT_MAX) {
                        throw new DomainException('The account authentication state is invalid.');
                    }

                    $user->forceFill(['auth_session_version' => $sessionVersion + 1])->save();
                }

                return [
                    'result' => $result,
                    'requires_two_factor' => $requiresTwoFactor,
                    'user' => $user,
                ];
            });
        } catch (DomainException) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => trans('auth.failed')]);
        }

        /** @var EmailVerificationOtpResult $result */
        $result = $verification['result'];
        /** @var User|null $verifiedUser */
        $verifiedUser = $verification['user'];

        if (in_array($result, [EmailVerificationOtpResult::Verified, EmailVerificationOtpResult::AlreadyVerified], true)) {
            if ($verification['requires_two_factor'] && $verifiedUser instanceof User) {
                $intendedUrl = $challenges->safeIntendedUrl($request);
                $request->session()->forget('url.intended');
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                try {
                    $challenges->begin($verifiedUser, $request, false, 'web', $intendedUrl);
                } catch (DomainException) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors(['email' => trans('auth.failed')]);
                }

                return redirect()->route('two-factor.challenge')
                    ->with('verification_status', 'verified');
            }

            $request->user()->refresh();
            $defaultDestination = $result === EmailVerificationOtpResult::Verified
                ? route('dashboard', absolute: false).'?verified=1'
                : route('dashboard', absolute: false);

            return redirect()->intended($defaultDestination)
                ->with('verification_status', $result === EmailVerificationOtpResult::Verified ? 'verified' : 'already_verified');
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
