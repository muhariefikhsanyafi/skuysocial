<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-[var(--skuy-text-primary)]">{{ $heading }}</h1>
    </x-slot>

    <div class="skuy-profile-page skuy-security-page">
        <section class="skuy-profile-shell skuy-security-shell">
            <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]" role="status" aria-live="polite">{{ $description }}</p>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('settings.two_factor.recovery_codes_heading') }}">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li class="rounded-[var(--radius-small)] bg-[var(--skuy-surface-subtle)] px-3 py-3 font-mono text-sm text-[var(--skuy-text-primary)]">
                        <code>{{ implode('-', str_split($recoveryCode, 8)) }}</code>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm font-semibold text-[var(--skuy-danger)]">{{ __('settings.two_factor.recovery_codes_warning') }}</p>
            <a href="{{ route('security.two-factor.show') }}" class="mt-6 inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-brand)] bg-[var(--skuy-brand)] px-4 py-3 text-sm font-bold text-[var(--skuy-on-brand)] transition-colors hover:border-[var(--skuy-brand-hover)] hover:bg-[var(--skuy-brand-hover)] active:border-[var(--skuy-brand-pressed)] active:bg-[var(--skuy-brand-pressed)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">{{ __('settings.two_factor.done') }}</a>
        </section>
    </div>
</x-app-layout>
