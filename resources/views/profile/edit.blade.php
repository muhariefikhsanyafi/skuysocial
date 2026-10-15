<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-[var(--skuy-text-primary)]">
                {{ __('settings.title') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
                {{ __('settings.account.description') }}
            </p>
        </div>
    </x-slot>

    <div class="skuy-profile-page" data-flow-account="settings">
        <div class="skuy-profile-shell space-y-6">
            @include('profile.partials.update-profile-information-form')
            @include('points.partials.wallet-summary', ['pointBalance' => $pointBalance])
            @include('profile.partials.update-message-privacy-form')
            @include('profile.partials.push-notifications')

            @include('profile.partials.update-password-form')

            <section class="skuy-settings-panel">
                <header>
                    <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.heading') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.two_factor.description') }}</p>
                </header>
                <a href="{{ route('security.two-factor.show') }}" class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] px-4 py-3 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                    {{ __('settings.two_factor.manage') }}
                </a>
            </section>

            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
