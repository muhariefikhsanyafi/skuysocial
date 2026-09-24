<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-[var(--skuy-text-primary)]">{{ $heading }}</h1>
    </x-slot>

    <div class="skuy-profile-page">
        <section class="skuy-profile-shell rounded-[var(--radius-large)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-5 sm:p-8">
            <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]" role="status" aria-live="polite">{{ $description }}</p>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('settings.two_factor.recovery_codes_heading') }}">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li class="rounded-[var(--radius-small)] bg-[var(--skuy-surface-subtle)] px-3 py-3 font-mono text-sm text-[var(--skuy-text-primary)]">
                        <code>{{ implode('-', str_split($recoveryCode, 8)) }}</code>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm font-semibold text-[var(--skuy-danger)]">{{ __('settings.two_factor.recovery_codes_warning') }}</p>
            <a href="{{ route('security.two-factor.show') }}" class="mt-6 inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] bg-[var(--skuy-brand-primary)] px-4 py-3 text-sm font-bold text-[var(--skuy-on-brand)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">{{ __('settings.two_factor.done') }}</a>
        </section>
    </div>
</x-app-layout>
