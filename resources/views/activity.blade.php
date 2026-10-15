<x-app-layout>
    <div class="skuy-feed-page skuy-activity-page" data-flow-activity="notifications">
        <div class="skuy-feed-content skuy-activity-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <h1 class="skuy-list-page-header__title">{{ __('activity.title') }}</h1>
            </header>

            @if ($notifications->isEmpty())
                <div class="skuy-social-empty" role="status">
                    <span class="skuy-social-empty__mark" aria-hidden="true">
                        <x-fa-icon name="bell" />
                    </span>
                    <h2 class="skuy-social-empty__title">{{ __('activity.empty_title') }}</h2>
                    <p class="skuy-social-empty__description">{{ __('activity.empty_description') }}</p>
                </div>
            @else
                <ul class="skuy-activity-list">
                    @foreach ($notifications as $notification)
                        @php
                            $data = $notification->data;
                            $actor = $notificationActors->get($data['user_id'] ?? null);
                            $actorAvatar = $actor ? $actor->avatar : ($data['avatar'] ?? null);
                            $message = match ($data['type'] ?? null) {
                                'like' => __('activity.messages.like'),
                                'comment' => __('activity.messages.comment'),
                                'reply' => __('activity.messages.reply'),
                                'follow' => __('activity.messages.follow'),
                                'repost' => __('activity.messages.repost'),
                                default => $data['message'] ?? '',
                            };
                            $postRouteKey = $data['post_slug'] ?? null;
                            $postTargetUrl = null;
                            $isUnread = $notification->read_at === null;

                            if (in_array($data['type'] ?? null, ['like', 'comment', 'reply'], true)
                                && is_string($postRouteKey)
                                && $postRouteKey !== '') {
                                $postTargetUrl = route('posts.show', $postRouteKey);
                                if (($data['type'] ?? null) === 'reply' && is_numeric($data['parent_id'] ?? null)) {
                                    $postTargetUrl .= '#comment-'.(int) $data['parent_id'];
                                }
                            }
                        @endphp

                        <li class="skuy-activity-item{{ $isUnread ? ' skuy-activity-item--unread' : '' }}" data-notification-read="{{ $isUnread ? 'unread' : 'read' }}">
                            <a href="{{ route('user.profile', $data['username']) }}" class="skuy-avatar skuy-avatar--small skuy-activity-item__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $data['name']]) }}">
                                @if ($actorAvatar)
                                    <img src="{{ asset('storage/' . $actorAvatar) }}" alt="{{ $data['name'] }}" class="object-cover" loading="lazy" decoding="async">
                                @else
                                    <span aria-hidden="true">{{ mb_substr($data['name'], 0, 1) }}</span>
                                @endif
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
                                    <x-fa-icon name="heart" weight="solid" />
                                @elseif (in_array(($data['type'] ?? null), ['comment', 'reply'], true))
                                    <x-fa-icon name="comment" />
                                @elseif (($data['type'] ?? null) === 'follow')
                                    <x-fa-icon name="user-plus" weight="solid" />
                                @elseif (($data['type'] ?? null) === 'repost')
                                    <x-fa-icon name="retweet" />
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
