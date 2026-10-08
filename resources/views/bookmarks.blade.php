<x-app-layout>
    <div class="skuy-feed-page skuy-saved-page">
        <div class="skuy-feed-content skuy-saved-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <div class="min-w-0">
                    <h1 class="skuy-list-page-header__title">{{ __('saved.title') }}</h1>
                    <p class="mt-1 text-sm text-[var(--skuy-text-muted)]">{{ __('saved.subtitle') }}</p>
                </div>
            </header>

            @if ($posts->isEmpty())
                <div class="skuy-social-empty" role="status">
                    <span class="skuy-social-empty__mark" aria-hidden="true">
                        <x-fa-icon name="bookmark" />
                    </span>
                    <h2 class="skuy-social-empty__title">{{ __('saved.empty') }}</h2>
                </div>
            @else
                <div class="skuy-feed-list">
                    @foreach ($posts as $post)
                        @php
                            $isRepost = $post->repost_id !== null;
                            $displayPost = $isRepost ? $post->originalPost : $post;
                            $modernMedia = is_array($displayPost?->images) ? $displayPost->images : [];
                            $mediaPaths = [];

                            foreach ($modernMedia as $mediaPath) {
                                if (is_string($mediaPath) && trim($mediaPath) !== '' && ! in_array($mediaPath, $mediaPaths, true)) {
                                    $mediaPaths[] = $mediaPath;
                                }
                            }

                            if ($mediaPaths === [] && is_string($displayPost?->image) && trim($displayPost->image) !== '') {
                                $mediaPaths[] = $displayPost->image;
                            }

                            $isCarousel = count($mediaPaths) > 1;
                        @endphp

                        <article class="skuy-post">
                            @if ($isRepost)
                                <div class="skuy-post__repost">
                                    <x-fa-icon name="retweet" class="text-base" />
                                    <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                                </div>
                            @endif

                            <div class="skuy-post__layout">
                                <div class="skuy-post__avatar-column">
                                    <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                        @if ($displayPost->user->avatar)
                                            <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                        @else
                                            <span aria-hidden="true">{{ substr($displayPost->user->name, 0, 1) }}</span>
                                        @endif
                                    </a>
                                </div>

                                <div class="skuy-post__body">
                                    <header class="skuy-post__header">
                                        <div class="skuy-post__identity">
                                            <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__display-name">
                                                {{ $displayPost->user->name }}
                                            </a>
                                            @if ($displayPost->user->is_verified)
                                                <x-verified-badge />
                                            @endif
                                            <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__username">
                                                <span aria-hidden="true">@</span>{{ $displayPost->user->username }}
                                            </a>
                                            <time datetime="{{ $displayPost->created_at->toIso8601String() }}" class="skuy-post__time">
                                                {{ $displayPost->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}
                                            </time>
                                        </div>
                                    </header>

                                    <a href="{{ route('posts.show', $displayPost->uid) }}" class="block">
                                        <p class="skuy-post__text">{{ $displayPost->content }}</p>

                                        @if ($mediaPaths !== [])
                                            @if ($isCarousel)
                                                <div class="skuy-feed-post__media skuy-feed-post__media--carousel">
                                                    <div class="skuy-media-track skuy-feed-media-track--carousel">
                                                        @foreach ($mediaPaths as $mediaPath)
                                                            @php($mediaUrl = asset('storage/' . $mediaPath))
                                                            <div class="skuy-media-item skuy-feed-media-item--portrait-preview">
                                                                <img src="{{ $mediaUrl }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                @php($singleMediaUrl = asset('storage/' . $mediaPaths[0]))
                                                <div class="skuy-feed-post__media skuy-feed-post__media--single">
                                                    <img class="skuy-saved-post__single-image" src="{{ $singleMediaUrl }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                </div>
                                            @endif
                                        @endif
                                    </a>

                                    <footer class="skuy-feed-post__actions skuy-saved-post__actions">
                                        <a href="{{ route('posts.show', $displayPost->uid) }}" class="skuy-saved-post__view-link">
                                            {{ __('threads.actions.view_thread') }}
                                        </a>

                                        <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark">
                                                <x-fa-icon name="bookmark" weight="solid" class="text-xl" />
                                            </button>
                                        </form>
                                    </footer>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($posts->hasPages())
                    <div class="mt-6">
                        {{ $posts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
