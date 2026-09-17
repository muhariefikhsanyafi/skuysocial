<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="login-heading">
        <div class="skuy-auth-intro">
            <h1 id="login-heading" class="skuy-auth-heading">{{ __('auth.login.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.login.intro') }}</p>
        </div>

        <x-auth-session-status variant="auth" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <label for="email" class="skuy-auth-label">{{ __('auth.login.identifier_label') }}</label>
                <input
                    id="email"
                    type="text"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    @if ($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('email')" id="email-error" />
            </div>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('auth.login.password_label')"
                autocomplete="current-password"
            />

            @if (Route::has('password.request'))
                <div class="skuy-auth-utility">
                    <a class="skuy-auth-link" href="{{ route('password.request') }}">
                        {{ __('auth.login.forgot_password') }}
                    </a>
                </div>
            @endif

            <div class="skuy-auth-turnstile">
                <x-turnstile action="login" />
            </div>

            <button type="submit" class="skuy-auth-primary-action">
                {{ __('auth.login.submit') }}
            </button>

            <p class="skuy-auth-signup">
                <span>{{ __('auth.login.no_account') }}</span>
                <a class="skuy-auth-signup__link" href="{{ route('register') }}">
                    {{ __('auth.login.register') }}
                </a>
            </p>
        </form>
    </section>
</x-guest-layout>
