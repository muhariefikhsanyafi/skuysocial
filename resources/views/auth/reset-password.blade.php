<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="reset-password-heading">
        <div class="skuy-auth-intro">
            <h1 id="reset-password-heading" class="skuy-auth-heading">{{ __('auth.reset_password.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.reset_password.intro') }}</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="skuy-auth-form">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="skuy-auth-field">
                <label for="email" class="skuy-auth-label">{{ __('auth.reset_password.email_label') }}</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="email"
                    @if ($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('email')" id="email-error" />
            </div>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('auth.reset_password.new_password')"
            />

            <x-auth-password-field
                id="password_confirmation"
                name="password_confirmation"
                :label="__('auth.reset_password.password_confirmation')"
            />

            <div class="skuy-auth-turnstile">
                <x-turnstile action="reset_password" />
            </div>

            <button type="submit" class="skuy-auth-primary-action">
                {{ __('auth.reset_password.submit') }}
            </button>

            <div class="skuy-auth-utility">
                <a class="skuy-auth-link" href="{{ route('login') }}">
                    {{ __('auth.reset_password.back_to_login') }}
                </a>
            </div>
        </form>
    </section>
</x-guest-layout>
