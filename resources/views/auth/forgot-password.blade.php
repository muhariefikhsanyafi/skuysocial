<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="forgot-password-heading">
        <div class="skuy-auth-intro">
            <h1 id="forgot-password-heading" class="skuy-auth-heading">{{ __('auth.forgot_password.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.forgot_password.intro') }}</p>
        </div>

        <x-auth-session-status variant="auth" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <label for="email" class="skuy-auth-label">{{ __('auth.forgot_password.email_label') }}</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    @if ($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('email')" id="email-error" />
            </div>

            <div class="skuy-auth-turnstile">
                <x-turnstile action="forgot_password" />
            </div>

            <button type="submit" class="skuy-auth-primary-action">
                {{ __('auth.forgot_password.submit') }}
            </button>

            <div class="skuy-auth-utility">
                <a class="skuy-auth-link" href="{{ route('login') }}">
                    {{ __('auth.forgot_password.back_to_login') }}
                </a>
            </div>
        </form>
    </section>
</x-guest-layout>
