<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="verify-email-heading">
        <div class="skuy-auth-intro">
            <h1 id="verify-email-heading" class="skuy-auth-heading">{{ __('auth.verify_email.title') }}</h1>

            @if ($alreadyVerified)
                <p class="skuy-auth-intro__copy" role="status">{{ __('auth.verify_email.already_verified') }}</p>
            @else
                <p class="skuy-auth-intro__copy">
                    {{ __($hasActiveCode ? 'auth.verify_email.sent_intro' : 'auth.verify_email.intro', ['email' => $maskedEmail]) }}
                </p>
            @endif
        </div>

        @if ($alreadyVerified)
            <a href="{{ route('dashboard') }}" class="skuy-auth-primary-action">
                {{ __('auth.verify_email.continue') }}
            </a>
        @else
            @php
                $statusMessages = [
                    'sent' => __('auth.verify_email.sent'),
                    'mail_failed' => __('auth.verify_email.mail_failed'),
                    'cooldown' => __('auth.verify_email.cooldown'),
                    'already_verified' => __('auth.verify_email.already_verified'),
                ];
                $verificationErrors = [
                    'invalid' => __('auth.verify_email.invalid'),
                    'expired' => __('auth.verify_email.expired'),
                    'attempt_limit' => __('auth.verify_email.attempt_limit'),
                ];
                $verificationStatus = session('verification_status');
            @endphp

            @if (isset($statusMessages[$verificationStatus]))
                <div class="skuy-auth-status" role="status">
                    {{ $statusMessages[$verificationStatus] }}
                </div>
            @endif

            @if ($codeExpired && ! in_array($verificationStatus, array_keys($verificationErrors), true))
                <div class="skuy-auth-status" role="status">
                    {{ __('auth.verify_email.code_expired') }}
                </div>
            @elseif ($attemptLimitReached && ! in_array($verificationStatus, array_keys($verificationErrors), true))
                <div class="skuy-auth-status" role="status">
                    {{ __('auth.verify_email.attempt_limit_reached') }}
                </div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" class="skuy-auth-form">
                @csrf

                <div class="skuy-auth-field">
                    <label for="code" class="skuy-auth-label">{{ __('auth.verify_email.code_label') }}</label>
                    <p id="code-hint" class="m-0 text-sm leading-6 text-[var(--skuy-text-secondary)]">
                        {{ __('auth.verify_email.code_hint') }}
                    </p>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        required
                        autofocus
                        aria-describedby="code-hint{{ isset($verificationErrors[$verificationStatus]) ? ' code-error' : '' }}"
                        @if (isset($verificationErrors[$verificationStatus])) aria-invalid="true" @endif
                        class="skuy-auth-input"
                    >
                    @if (isset($verificationErrors[$verificationStatus]))
                        <p id="code-error" class="skuy-auth-error" role="alert">
                            {{ $verificationErrors[$verificationStatus] }}
                        </p>
                    @endif
                </div>

                <button type="submit" class="skuy-auth-primary-action">
                    {{ __('auth.verify_email.verify') }}
                </button>
            </form>

            <div
                class="flex min-w-0 flex-col gap-3"
                x-data="{ remaining: @js($resendCooldownSeconds) }"
                x-init="if (remaining > 0) { const timer = window.setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) window.clearInterval(timer); }, 1000); }"
            >
                <p
                    x-show="remaining > 0"
                    x-cloak
                    role="status"
                    aria-live="polite"
                    class="m-0 text-sm leading-6 text-[var(--skuy-text-secondary)]"
                    x-text="@js(__('auth.verify_email.resend_cooldown', ['seconds' => ':seconds'])).replace(':seconds', remaining)"
                >
                    {{ __('auth.verify_email.resend_cooldown', ['seconds' => $resendCooldownSeconds]) }}
                </p>

                <form method="POST" action="{{ route('verification.send') }}" class="min-w-0">
                    @csrf
                    <button
                        type="submit"
                        x-bind:disabled="remaining > 0"
                        class="skuy-auth-primary-action disabled:cursor-not-allowed disabled:border-[var(--skuy-border-subtle)] disabled:bg-[var(--skuy-surface-subtle)] disabled:text-[var(--skuy-text-muted)]"
                    >
                        {{ __('auth.verify_email.resend') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="min-w-0">
                    @csrf
                    <button type="submit" class="skuy-auth-link w-full justify-center text-center">
                        {{ __('auth.common.log_out') }}
                    </button>
                </form>
            </div>
        @endif
    </section>
</x-guest-layout>
