<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="login-heading">
        <div class="skuy-auth-intro">
            <h1 id="login-heading" class="skuy-auth-heading">{{ __('auth.login.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.login.intro') }}</p>
        </div>

        <x-auth-session-status variant="auth" :status="session('status')" />

        <x-input-error variant="auth" :messages="$errors->get('google')" id="google-auth-error" />

        @if (config('services.google.enabled') && filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
            <div class="skuy-google-auth" data-google-auth>
                <a href="{{ route('auth.google.redirect') }}" class="skuy-google-auth__button">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M21.6 12.23c0-.69-.06-1.35-.18-1.99H12v3.77h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.22c1.89-1.75 3-4.34 3-7.31Z"/>
                        <path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.61-2.46l-3.22-2.51c-.89.59-2.02.95-3.39.95a5.98 5.98 0 0 1-5.62-4.14H3.06v2.59A10 10 0 0 0 12 22Z"/>
                        <path fill="#FBBC05" d="M6.38 13.84a6 6 0 0 1 0-3.68V7.57H3.06a10 10 0 0 0 0 8.86l3.32-2.59Z"/>
                        <path fill="#EA4335" d="M12 6.02c1.47 0 2.78.51 3.81 1.5l2.86-2.86A9.54 9.54 0 0 0 12 2a10 10 0 0 0-8.94 5.57l3.32 2.59A5.98 5.98 0 0 1 12 6.02Z"/>
                    </svg>
                    <span>{{ __('google_auth.continue') }}</span>
                </a>
                <p class="skuy-google-auth__divider">{{ __('google_auth.or') }}</p>
            </div>
        @endif

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

            <div class="skuy-auth-utility skuy-auth-login-utility">
                <label for="remember" class="skuy-auth-remember">
                    <input
                        id="remember"
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                    >
                    <span>{{ __('auth.login.remember_30_days') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="skuy-auth-link" href="{{ route('password.request') }}">
                        {{ __('auth.login.forgot_password') }}
                    </a>
                @endif
            </div>

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
