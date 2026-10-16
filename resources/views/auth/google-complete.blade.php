<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="google-complete-heading" data-google-complete>
        <div class="skuy-auth-intro">
            <h1 id="google-complete-heading" class="skuy-auth-heading">{{ __('google_auth.complete_title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('google_auth.complete_intro') }}</p>
        </div>

        <form method="POST" action="{{ route('auth.google.complete.store') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <label for="google-email" class="skuy-auth-label">{{ __('auth.register.email_label') }}</label>
                <p id="google-email" class="skuy-auth-input">{{ $pending['email'] }}</p>
                <p class="text-sm text-[var(--skuy-text-secondary)]">{{ __('google_auth.verified_email_note') }}</p>
            </div>

            <div class="skuy-auth-field">
                <label for="name" class="skuy-auth-label">{{ __('auth.register.name_label') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name', $pending['name']) }}"
                    required autofocus maxlength="255" autocomplete="name"
                    @if ($errors->has('name')) aria-describedby="name-error" aria-invalid="true" @endif
                    class="skuy-auth-input">
                <x-input-error variant="auth" :messages="$errors->get('name')" id="name-error" />
            </div>

            <div class="skuy-auth-field">
                <label for="username" class="skuy-auth-label">{{ __('auth.register.username_label') }}</label>
                <p id="username-hint" class="text-sm text-[var(--skuy-text-secondary)]">{{ __('google_auth.username_note') }}</p>
                <input id="username" type="text" name="username" value="{{ old('username') }}"
                    required maxlength="255" autocomplete="username" pattern="^[a-zA-Z0-9_.]+$"
                    title="{{ __('auth.register.username_format_hint') }}"
                    oninput="this.value = this.value.replace(/\s/g, '').toLowerCase()"
                    @if ($errors->has('username')) aria-describedby="username-hint username-error" aria-invalid="true"
                    @else aria-describedby="username-hint" @endif class="skuy-auth-input">
                <x-input-error variant="auth" :messages="$errors->get('username')" id="username-error" />
            </div>

            <button type="submit" class="skuy-auth-primary-action">{{ __('google_auth.complete_submit') }}</button>
        </form>
        <form method="POST" action="{{ route('auth.google.complete.cancel') }}" class="skuy-auth-form">
            @csrf
            <button type="submit" class="skuy-auth-link min-h-11 text-center">{{ __('google_auth.complete_cancel') }}</button>
        </form>
    </section>
</x-guest-layout>
