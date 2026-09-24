<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticationService $twoFactor): Response
    {
        /** @var User $user */
        $user = $request->user();
        $security = $user->twoFactorAuthentication()->first();
        $pendingSecret = $security?->pending_expires_at?->isFuture()
            ? $security->pending_secret
            : null;
        $qrCode = $pendingSecret === null
            ? null
            : $this->renderQrCode($user->username, $pendingSecret);

        return $this->noStore(view('settings.two-factor', [
            'enabled' => $security?->isEnabled() ?? false,
            'pendingSecret' => $pendingSecret,
            'qrCode' => $qrCode,
            'remainingRecoveryCodes' => $twoFactor->remainingRecoveryCodes($user),
        ]));
    }

    public function startSetup(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        try {
            $twoFactor->beginSetup($request->user());
        } catch (DomainException) {
            return redirect()->route('security.two-factor.show');
        }

        return redirect()->route('security.two-factor.show');
    }

    public function confirmSetup(Request $request, TwoFactorAuthenticationService $twoFactor): Response|RedirectResponse
    {
        $previousSessionVersion = $request->session()->get(EnforceAccountSession::SESSION_VERSION_KEY);
        $code = $request->input('code');

        if (! is_string($code) || ! preg_match('/\A[0-9]{6}\z/', $code)) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['code' => __('settings.two_factor.invalid_code')]);
        }

        try {
            $recoveryCodes = $twoFactor->confirmSetup($request->user(), $code);
        } catch (DomainException) {
            $recoveryCodes = null;
        }

        if ($recoveryCodes === null) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['code' => __('settings.two_factor.invalid_code')]);
        }

        $request->session()->put(
            EnforceAccountSession::SESSION_VERSION_KEY,
            is_int($previousSessionVersion) && $previousSessionVersion < PHP_INT_MAX
                ? $previousSessionVersion + 1
                : -1,
        );
        $request->session()->regenerate();

        return $this->recoveryCodesView(
            $recoveryCodes,
            __('settings.two_factor.recovery_codes_heading'),
            __('settings.two_factor.recovery_codes_description'),
        );
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor): Response|RedirectResponse
    {
        try {
            $recoveryCodes = $twoFactor->regenerateRecoveryCodes($request->user());
        } catch (DomainException) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['security' => __('settings.two_factor.action_unavailable')]);
        }

        return $this->recoveryCodesView(
            $recoveryCodes,
            __('settings.two_factor.recovery_codes_heading'),
            __('settings.two_factor.recovery_codes_regenerated'),
        );
    }

    public function disable(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $previousSessionVersion = $request->session()->get(EnforceAccountSession::SESSION_VERSION_KEY);
        $code = $request->input('code');

        if (! is_string($code) || ! preg_match('/\A[0-9]{6}\z/', $code)) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['code' => __('settings.two_factor.invalid_code')]);
        }

        try {
            $disabled = $twoFactor->disable($request->user(), $code);
        } catch (DomainException) {
            $disabled = false;
        }

        if (! $disabled) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['code' => __('settings.two_factor.invalid_code')]);
        }

        $request->session()->put(
            EnforceAccountSession::SESSION_VERSION_KEY,
            is_int($previousSessionVersion) && $previousSessionVersion < PHP_INT_MAX
                ? $previousSessionVersion + 1
                : -1,
        );
        $request->session()->regenerate();

        return redirect()->route('security.two-factor.show')
            ->with('two_factor_status', 'disabled');
    }

    private function renderQrCode(string $accountLabel, #[\SensitiveParameter] string $secret): string
    {
        try {
            return app(Google2FAQRCode::class)->getQRCodeInline('SkuySocial', $accountLabel, $secret);
        } catch (\Throwable) {
            throw new DomainException('Unable to render authenticator setup.');
        }
    }

    /**
     * @param  list<string>  $recoveryCodes
     */
    private function recoveryCodesView(#[\SensitiveParameter] array $recoveryCodes, string $heading, string $description): Response
    {
        return $this->noStore(view('settings.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'heading' => $heading,
            'description' => $description,
        ]));
    }

    private function noStore(View $view): Response
    {
        return response($view)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
