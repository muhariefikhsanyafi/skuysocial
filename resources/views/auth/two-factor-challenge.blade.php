<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="two-factor-heading">
        <div class="skuy-auth-intro">
            <h1 id="two-factor-heading" class="skuy-auth-heading">{{ __('auth.two_factor.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.two_factor.intro') }}</p>
        </div>

        @if ($errors->has('code'))
            <p id="code-error" class="skuy-auth-error" role="alert">{{ __('auth.two_factor.invalid') }}</p>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <label for="code" class="skuy-auth-label">{{ __('auth.two_factor.code_label') }}</label>
                <p id="code-hint" class="m-0 text-sm leading-6 text-[var(--skuy-text-secondary)]">
                    {{ __('auth.two_factor.code_hint') }}
                </p>
                <input
                    id="code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="64"
                    required
                    autofocus
                    aria-describedby="code-hint{{ $errors->has('code') ? ' code-error' : '' }}"
                    @if ($errors->has('code')) aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
            </div>

            <button type="submit" class="skuy-auth-primary-action">
                {{ __('auth.two_factor.verify') }}
            </button>
        </form>

        <form method="POST" action="{{ route('two-factor.cancel') }}" class="min-w-0">
            @csrf
            <button type="submit" class="skuy-auth-link w-full justify-center text-center">
                {{ __('auth.two_factor.cancel') }}
            </button>
        </form>
    </section>
</x-guest-layout>
