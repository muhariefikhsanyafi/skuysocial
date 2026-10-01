<x-app-layout>
    <div class="skuy-feed-page skuy-activity-page">
        <div class="skuy-feed-content skuy-activity-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            @php
                $localePreference = app(\App\Services\LocalePreference::class);
                $locale = $localePreference->supported(auth()->user()->locale) ?? $localePreference->configuredLocale();
            @endphp
            <header class="skuy-list-page-header">
                <a href="{{ route('announcements.index') }}" aria-label="{{ __('announcements.back') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <h1 class="skuy-list-page-header__title">{{ $announcement->{'title_'.$locale} }}</h1>
            </header>
            <article class="skuy-activity-item">
                <div class="skuy-activity-item__body">
                    <p class="whitespace-pre-wrap break-words">{{ $announcement->{'body_'.$locale} }}</p>
                    <div class="text-sm text-[var(--skuy-text-muted)]">{{ $announcement->sent_at->locale($locale)->diffForHumans() }}</div>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
