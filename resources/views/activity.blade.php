<x-app-layout>
    <div class="skuy-feed-page skuy-activity-page">
        <div class="skuy-feed-content skuy-activity-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19 3 12l7-7M3 12h18"></path>
                    </svg>
                </a>
                <h1 class="skuy-list-page-header__title">{{ __('activity.title') }}</h1>
            </header>

            @if ($notifications->isEmpty())
                <div class="skuy-social-empty" role="status">
                    <span class="skuy-social-empty__mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 18h14l-1.4-1.8V10a5.6 5.6 0 0 0-11.2 0v6.2L5 18Z"></path>
                            <path stroke-linecap="round" d="M9.5 20a2.7 2.7 0 0 0 5 0"></path>
                        </svg>
                    </span>
                    <h2 class="skuy-social-empty__title">{{ __('activity.empty_title') }}</h2>
                    <p class="skuy-social-empty__description">{{ __('activity.empty_description') }}</p>
                </div>
            @else
                <ul class="skuy-activity-list">
                    @foreach ($notifications as $notification)
                        @php
                            $data = $notification->data;
                            $message = match ($data['type'] ?? null) {
                                'like' => __('activity.messages.like'),
                                'comment' => __('activity.messages.comment'),
                                'follow' => __('activity.messages.follow'),
                                'repost' => __('activity.messages.repost'),
                                default => $data['message'] ?? '',
                            };
                            $postRouteKey = $data['post_slug'] ?? null;
                            $postTargetUrl = null;
                            $isUnread = $notification->read_at === null;

                            if (
                                in_array($data['type'] ?? null, ['like', 'comment'], true)
                                && is_string($postRouteKey)
                                && $postRouteKey !== ''
                            ) {
                                $postTargetUrl = route('posts.show', $postRouteKey);
                            }
                        @endphp

                        <li class="skuy-activity-item{{ $isUnread ? ' skuy-activity-item--unread' : '' }}" data-notification-read="{{ $isUnread ? 'unread' : 'read' }}">
                            <a href="{{ route('user.profile', $data['username']) }}" class="skuy-avatar skuy-avatar--small skuy-activity-item__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $data['name']]) }}">
                                <img src="{{ isset($data['avatar']) ? asset('storage/' . $data['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($data['name']).'&background=random&color=fff&font-weight=bold' }}"
                                     alt="{{ $data['name'] }}"
                                     class="object-cover">
                            </a>

                            <div class="skuy-activity-item__body">
                                <p class="skuy-activity-item__message">
                                    <a href="{{ route('user.profile', $data['username']) }}" class="skuy-activity-item__actor">
                                        {{ $data['name'] }}
                                    </a>
                                    {{ $message }}
                                </p>
                                <div class="skuy-activity-item__meta">
                                    <time datetime="{{ $notification->created_at->toIso8601String() }}">
                                        {{ $notification->created_at->locale(app()->getLocale())->diffForHumans() }}
                                    </time>
                                    @if ($isUnread)
                                        <span class="skuy-activity-item__status">{{ __('activity.unread') }}</span>
                                    @else
                                        <span class="sr-only">{{ __('activity.read') }}</span>
                                    @endif
                                </div>
                                @if ($postTargetUrl)
                                    <a href="{{ $postTargetUrl }}" class="skuy-activity-item__target">
                                        {{ __('activity.view_thread') }}
                                    </a>
                                @endif
                            </div>

                            <span class="skuy-activity-item__type skuy-activity-item__type--{{ $data['type'] ?? 'other' }}" aria-hidden="true">
                                @if (($data['type'] ?? null) === 'like')
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"></path></svg>
                                @elseif (($data['type'] ?? null) === 'comment')
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.75L4 20l1.35-3.6A7.3 7.3 0 0 1 4 11.5 7.5 7.5 0 0 1 12 4a7.5 7.5 0 0 1 8 7.5Z"></path></svg>
                                @elseif (($data['type'] ?? null) === 'follow')
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"></circle><path stroke-linecap="round" d="M2 21a7 7 0 0 1 14 0"></path><path stroke-linecap="round" d="M18 8v6M15 11h6"></path></svg>
                                @elseif (($data['type'] ?? null) === 'repost')
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path></svg>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($notifications->hasPages())
                <div class="skuy-activity-pagination">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
