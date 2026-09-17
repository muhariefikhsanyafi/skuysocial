<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="register-heading">
        <div class="skuy-auth-intro">
            <h1 id="register-heading" class="skuy-auth-heading">{{ __('auth.register.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.register.intro') }}</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <label for="name" class="skuy-auth-label">{{ __('auth.register.name_label') }}</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                    @if ($errors->has('name')) aria-describedby="name-error" aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('name')" id="name-error" />
            </div>

            <div class="skuy-auth-field">
                <label for="username" class="skuy-auth-label">{{ __('auth.register.username_label') }}</label>
                <p id="username-hint" class="m-0 text-sm font-medium leading-6 text-[var(--skuy-text-secondary)]">
                    {{ __('auth.register.username_hint') }}
                </p>
                <input
                    id="username"
                    type="text"
                    name="username"
                    value="{{ old('username') }}"
                    required
                    autocomplete="username"
                    pattern="^[a-zA-Z0-9_.]+$"
                    title="{{ __('auth.register.username_format_hint') }}"
                    oninput="this.value = this.value.replace(/\s/g, '').toLowerCase()"
                    @if ($errors->has('username')) aria-describedby="username-hint username-error" aria-invalid="true" @else aria-describedby="username-hint" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('username')" id="username-error" />
            </div>

            <div class="skuy-auth-field">
                <label for="email" class="skuy-auth-label">{{ __('auth.register.email_label') }}</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    @if ($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif
                    class="skuy-auth-input"
                >
                <x-input-error variant="auth" :messages="$errors->get('email')" id="email-error" />
            </div>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('auth.register.password_label')"
            />

            <x-auth-password-field
                id="password_confirmation"
                name="password_confirmation"
                :label="__('auth.register.password_confirmation_label')"
            />

            <div class="skuy-auth-turnstile">
                <x-turnstile action="register" />
            </div>

            <button type="submit" class="skuy-auth-primary-action">
                {{ __('auth.register.submit') }}
            </button>

            <p class="skuy-auth-signup">
                <span>{{ __('auth.register.has_account') }}</span>
                <a class="skuy-auth-signup__link" href="{{ route('login') }}">
                    {{ __('auth.register.sign_in') }}
                </a>
            </p>
        </form>
    </section>
</x-guest-layout>
