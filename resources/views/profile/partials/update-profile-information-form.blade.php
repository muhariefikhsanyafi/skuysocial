<section class="skuy-settings-panel">
    <header>
        <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
            {{ __('settings.account.email_heading') }}
        </h2>

        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.account.email_description') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div class="space-y-2">
            <x-input-label for="email" :value="__('settings.profile.email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" required autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="space-y-2">
                    <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
                        {{ __('settings.profile.email_unverified') }}

                        <button form="send-verification" type="submit" class="inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] text-sm font-semibold text-[var(--skuy-secondary)] underline underline-offset-4 hover:text-[var(--skuy-secondary-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                            {{ __('settings.profile.resend_verification') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                            {{ __('settings.profile.verification_sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col-reverse items-stretch gap-3 border-t border-[var(--skuy-border-subtle)] pt-6 sm:flex-row sm:items-center sm:justify-end">
            <x-primary-button>
                {{ __('settings.profile.save') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                    {{ __('settings.profile.saved') }}
                </p>
            @endif
        </div>
    </form>
</section>
