<x-app-layout>
    <div class="skuy-feed-page skuy-activity-page" data-flow-activity="announcements">
        <div class="skuy-feed-content skuy-activity-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <h1 class="skuy-list-page-header__title">{{ __('announcements.title') }}</h1>
            </header>
            @if ($announcements->isEmpty())
                <div class="skuy-social-empty" role="status">
                    <span class="skuy-social-empty__mark" aria-hidden="true"><x-fa-icon name="bullhorn" /></span>
                    <h2 class="skuy-social-empty__title">{{ __('announcements.empty_title') }}</h2>
                    <p class="skuy-social-empty__description">{{ __('announcements.empty_description') }}</p>
                </div>
            @else
                <ul class="skuy-activity-list">
                    @foreach ($announcements as $announcement)
                        @php
                            $localePreference = app(\App\Services\LocalePreference::class);
                            $locale = $localePreference->supported(auth()->user()->locale) ?? $localePreference->configuredLocale();
                            $isUnread = ! $announcement->read_by_me;
                        @endphp
                        <li class="skuy-activity-item skuy-announcement-item">
                            <div class="skuy-activity-item__body min-w-0 w-full">
                                <h2 class="skuy-activity-item__message">
                                    <a href="{{ route('announcements.show', $announcement) }}" class="skuy-activity-item__actor">
                                        {{ $announcement->{'title_'.$locale} }}
                                    </a>
                                </h2>
                                <p class="skuy-activity-item__meta">
                                    <time datetime="{{ $announcement->sent_at->toIso8601String() }}">{{ $announcement->sent_at->locale($locale)->diffForHumans() }}</time>
                                    <span class="skuy-activity-item__status">{{ $isUnread ? __('announcements.unread') : __('announcements.read') }}</span>
                                </p>
                                <p>{{ \Illuminate\Support\Str::limit($announcement->{'body_'.$locale}, 220) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($announcements->hasPages())
                <div class="skuy-activity-pagination">{{ $announcements->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
