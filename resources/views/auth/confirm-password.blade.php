<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="confirm-password-heading">
        <div class="skuy-auth-intro">
            <h1 id="confirm-password-heading" class="skuy-auth-heading">{{ __('auth.confirm_password.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.confirm_password.intro') }}</p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="skuy-auth-form">
            @csrf

            <div class="skuy-auth-field">
                <x-input-label for="password" :value="__('auth.common.password')" class="skuy-auth-label" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="skuy-auth-input"
                    required
                    autocomplete="current-password"
                    @if ($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif
                />

                <x-input-error variant="auth" :messages="$errors->get('password')" id="password-error" />
            </div>

            <x-primary-button class="skuy-auth-primary-action">
                {{ __('auth.confirm_password.submit') }}
            </x-primary-button>
        </form>
    </section>
</x-guest-layout>
