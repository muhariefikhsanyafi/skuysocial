<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
                {{ __('settings.title') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
                {{ __('settings.profile.description') }}
            </p>
        </div>
    </x-slot>

    <div class="skuy-profile-page">
        <div class="skuy-profile-shell space-y-6">
            <section class="rounded-[var(--radius-large)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-5 sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="rounded-[var(--radius-large)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-5 sm:p-8">
                @include('profile.partials.update-password-form')
            </section>

            <section class="rounded-[var(--radius-large)] border border-[var(--skuy-danger)] bg-[var(--skuy-surface)] p-5 sm:p-8">
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </div>
</x-app-layout>
