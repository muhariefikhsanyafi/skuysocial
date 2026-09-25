<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.heading') }}</h1>
            <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.two_factor.description') }}</p>
        </div>
    </x-slot>

    <div class="skuy-profile-page skuy-security-page">
        <div class="skuy-security-shell space-y-6">
            <section class="skuy-settings-panel">
                <h2 class="text-lg font-bold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.status_heading') }}</h2>
                <p class="mt-3 text-sm font-semibold text-[var(--skuy-text-secondary)]" role="status">
                    {{ $enabled ? __('settings.two_factor.enabled') : __('settings.two_factor.disabled') }}
                </p>

                @if (session('two_factor_status') === 'disabled')
                    <p class="mt-3 text-sm font-semibold text-[var(--skuy-text-primary)]" role="status">
                        {{ __('settings.two_factor.disabled_success') }}
                    </p>
                @endif

                @if ($errors->has('security'))
                    <p class="mt-3 text-sm text-[var(--skuy-danger)]" role="alert">{{ $errors->first('security') }}</p>
                @endif

                @if (! $enabled && $pendingSecret === null)
                    <form action="{{ route('security.two-factor.setup') }}" method="POST" class="mt-6">
                        @csrf
                        <x-primary-button>{{ __('settings.two_factor.enable') }}</x-primary-button>
                    </form>
                @endif

                @if (! $enabled && $pendingSecret !== null)
                    <div class="mt-6 space-y-5">
                        <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.two_factor.setup_instructions') }}</p>
                        <img src="{{ $qrCode }}" width="220" height="220" alt="{{ __('settings.two_factor.qr_alt') }}" class="h-[220px] w-[220px] rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-white p-2">
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.manual_key') }}</p>
                            <code class="block break-all rounded-[var(--radius-small)] bg-[var(--skuy-surface-subtle)] p-3 text-sm text-[var(--skuy-text-primary)]">{{ $pendingSecret }}</code>
                        </div>
                        <form action="{{ route('security.two-factor.confirm') }}" method="POST" class="space-y-3">
                            @csrf
                            <input type="hidden" name="recovery_code_action_token" value="{{ $recoveryActionToken }}">
                            <label for="setup_code" class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.code_label') }}</label>
                            <input id="setup_code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required aria-describedby="setup-code-help{{ $errors->has('code') ? ' setup-code-error' : '' }}" @if ($errors->has('code')) aria-invalid="true" @endif class="block min-h-[48px] w-full max-w-xs rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-3 text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                            <p id="setup-code-help" class="text-sm text-[var(--skuy-text-muted)]">{{ __('settings.two_factor.code_hint') }}</p>
                            @if ($errors->has('code'))
                                <p id="setup-code-error" class="text-sm text-[var(--skuy-danger)]" role="alert">{{ $errors->first('code') }}</p>
                            @endif
                            <x-primary-button>{{ __('settings.two_factor.confirm_setup') }}</x-primary-button>
                        </form>
                    </div>
                @endif

                @if ($enabled)
                    <div class="mt-6 space-y-6">
                        <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
                            {{ __('settings.two_factor.remaining_codes', ['count' => $remainingRecoveryCodes]) }}
                        </p>

                        <form action="{{ route('security.two-factor.recovery-codes.regenerate') }}" method="POST">
                            @csrf
                            <input type="hidden" name="recovery_code_action_token" value="{{ $recoveryActionToken }}">
                            <x-primary-button>{{ __('settings.two_factor.regenerate') }}</x-primary-button>
                        </form>

                        <div class="border-t border-[var(--skuy-border-subtle)] pt-6">
                            <h3 class="text-base font-bold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.disable_heading') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.two_factor.disable_description') }}</p>
                            <form action="{{ route('security.two-factor.disable') }}" method="POST" class="mt-4 space-y-3">
                                @csrf
                                @method('DELETE')
                                <label for="disable_code" class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.disable_code_label') }}</label>
                                <input id="disable_code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required aria-describedby="disable-code-help{{ $errors->has('code') ? ' disable-code-error' : '' }}" @if ($errors->has('code')) aria-invalid="true" @endif class="block min-h-[48px] w-full max-w-xs rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-3 text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                                <p id="disable-code-help" class="text-sm text-[var(--skuy-text-muted)]">{{ __('settings.two_factor.code_hint') }}</p>
                                @if ($errors->has('code'))
                                    <p id="disable-code-error" class="text-sm text-[var(--skuy-danger)]" role="alert">{{ $errors->first('code') }}</p>
                                @endif
                                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-danger)] px-4 py-3 text-sm font-bold text-[var(--skuy-danger)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">{{ __('settings.two_factor.disable') }}</button>
                            </form>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
