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
    private const RECOVERY_ACTION_TOKEN_KEY = 'security.two_factor.recovery_action_token';

    private const RECOVERY_ACTION_EXPIRY_KEY = 'security.two_factor.recovery_action_expires_at';

    private const RECOVERY_HANDOFF_TOKEN_KEY = 'security.two_factor.recovery_handoff_token';

    private const RECOVERY_SESSION_BINDING_KEY = 'security.two_factor.recovery_session_binding';

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
        $sessionBinding = $request->session()->get(self::RECOVERY_SESSION_BINDING_KEY);

        if (! is_string($sessionBinding) || ! preg_match('/\A[a-f0-9]{64}\z/', $sessionBinding)) {
            $sessionBinding = bin2hex(random_bytes(32));
            $request->session()->put(self::RECOVERY_SESSION_BINDING_KEY, $sessionBinding);
        }

        $recoveryActionToken = bin2hex(random_bytes(32));
        $request->session()->put(self::RECOVERY_ACTION_TOKEN_KEY, $recoveryActionToken);
        $request->session()->put(
            self::RECOVERY_ACTION_EXPIRY_KEY,
            now()->addMinutes(TwoFactorAuthenticationService::RECOVERY_CODE_HANDOFF_LIFETIME_MINUTES)->timestamp,
        );

        return $this->noStore(view('settings.two-factor', [
            'enabled' => $security?->isEnabled() ?? false,
            'pendingSecret' => $pendingSecret,
            'qrCode' => $qrCode,
            'remainingRecoveryCodes' => $twoFactor->remainingRecoveryCodes($user),
            'recoveryActionToken' => $recoveryActionToken,
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

        $action = $this->recoveryActionContext($request);

        if ($action === null) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['security' => __('settings.two_factor.action_unavailable')]);
        }

        try {
            $confirmed = $twoFactor->confirmSetupForDisplay(
                $request->user(),
                $code,
                $action['operation_token'],
                $action['session_binding'],
            );
        } catch (DomainException) {
            $confirmed = false;
        }

        if (! $confirmed) {
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

        return $this->redirectToRecoveryCodeDisplay($request, $action['operation_token']);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $action = $this->recoveryActionContext($request);

        if ($action === null) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['security' => __('settings.two_factor.action_unavailable')]);
        }

        try {
            $regenerated = $twoFactor->regenerateRecoveryCodes(
                $request->user(),
                $action['operation_token'],
                $action['session_binding'],
            );
        } catch (DomainException) {
            $regenerated = false;
        }

        if (! $regenerated) {
            return redirect()->route('security.two-factor.show')
                ->withErrors(['security' => __('settings.two_factor.action_unavailable')]);
        }

        return $this->redirectToRecoveryCodeDisplay($request, $action['operation_token']);
    }

    public function showRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor): Response|RedirectResponse
    {
        $operationToken = $request->session()->get(self::RECOVERY_HANDOFF_TOKEN_KEY);
        $sessionBinding = $request->session()->get(self::RECOVERY_SESSION_BINDING_KEY);

        if (! $this->isRecoveryToken($operationToken) || ! $this->isRecoveryToken($sessionBinding)) {
            $request->session()->forget(self::RECOVERY_HANDOFF_TOKEN_KEY);

            return redirect()->route('security.two-factor.show', [], Response::HTTP_SEE_OTHER);
        }

        $display = $twoFactor->consumeRecoveryCodeHandoff(
            $request->user(),
            $operationToken,
            $sessionBinding,
        );
        $request->session()->forget([
            self::RECOVERY_HANDOFF_TOKEN_KEY,
            self::RECOVERY_ACTION_TOKEN_KEY,
            self::RECOVERY_ACTION_EXPIRY_KEY,
        ]);

        if ($display === null) {
            return redirect()->route('security.two-factor.show', [], Response::HTTP_SEE_OTHER);
        }

        $description = $display['purpose'] === 'setup'
            ? __('settings.two_factor.recovery_codes_description')
            : __('settings.two_factor.recovery_codes_regenerated');

        return $this->recoveryCodesView(
            $display['codes'],
            __('settings.two_factor.recovery_codes_heading'),
            $description,
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

    /**
     * @return array{operation_token: string, session_binding: string}|null
     */
    private function recoveryActionContext(Request $request): ?array
    {
        $operationToken = $request->input('recovery_code_action_token');
        $sessionToken = $request->session()->get(self::RECOVERY_ACTION_TOKEN_KEY);
        $sessionTokenExpiresAt = $request->session()->get(self::RECOVERY_ACTION_EXPIRY_KEY);
        $sessionBinding = $request->session()->get(self::RECOVERY_SESSION_BINDING_KEY);

        if (
            ! $this->isRecoveryToken($operationToken)
            || ! $this->isRecoveryToken($sessionToken)
            || ! is_int($sessionTokenExpiresAt)
            || $sessionTokenExpiresAt <= now()->timestamp
            || ! $this->isRecoveryToken($sessionBinding)
            || ! hash_equals($sessionToken, $operationToken)
        ) {
            return null;
        }

        return [
            'operation_token' => $operationToken,
            'session_binding' => $sessionBinding,
        ];
    }

    private function isRecoveryToken(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function redirectToRecoveryCodeDisplay(Request $request, #[\SensitiveParameter] string $operationToken): RedirectResponse
    {
        $request->session()->forget([
            self::RECOVERY_ACTION_TOKEN_KEY,
            self::RECOVERY_ACTION_EXPIRY_KEY,
        ]);
        $request->session()->put(self::RECOVERY_HANDOFF_TOKEN_KEY, $operationToken);

        return redirect()->route(
            'security.two-factor.recovery-codes.display',
            [],
            Response::HTTP_SEE_OTHER,
        );
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
