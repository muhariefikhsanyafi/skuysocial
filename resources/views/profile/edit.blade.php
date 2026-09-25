<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-[var(--skuy-text-primary)]">
                {{ __('settings.title') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
                {{ __('settings.profile.description') }}
            </p>
        </div>
    </x-slot>

    <div class="skuy-profile-page">
        <div class="skuy-profile-shell space-y-6">
            <section class="skuy-settings-panel">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="skuy-settings-panel">
                @include('profile.partials.update-password-form')
            </section>

            <section class="skuy-settings-panel skuy-settings-panel--danger">
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </div>
</x-app-layout>
