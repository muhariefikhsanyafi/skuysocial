<x-app-layout>
    <div class="skuy-feed-page">
        <div class="skuy-feed-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19 3 12l7-7M3 12h18"></path></svg>
                </a>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('saved.title') }}</h1>
                    <p class="mt-1 text-sm text-[var(--skuy-text-muted)]">{{ __('saved.subtitle') }}</p>
                </div>
            </div>

            @if ($posts->isEmpty())
                <div class="skuy-profile-empty" role="status">
                    <div class="mb-4 text-5xl opacity-60" aria-hidden="true">🔖</div>
                    <p class="skuy-profile-empty__title">{{ __('saved.empty') }}</p>
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
                        @endphp

                        <article class="skuy-post">
                            @if ($isRepost)
                                <div class="skuy-post__repost">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path></svg>
                                    <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                                </div>
                            @endif

                            <div class="skuy-post__layout">
                                <div class="skuy-post__avatar-column">
                                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                    @if ($displayPost->user->avatar)
                                        <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                    @else
                                        <span aria-hidden="true">
                                            {{ substr($displayPost->user->name, 0, 1) }}
                                        </span>
                                    @endif
                                </a>
                                </div>

                                <div class="skuy-post__body">
                                    <div class="skuy-post__header">
                                        <div class="skuy-post__identity">
                                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__display-name">
                                            {{ $displayPost->user->username }}
                                        </a>
                                        <time datetime="{{ $displayPost->created_at->toIso8601String() }}" class="skuy-post__time">
                                            {{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}
                                        </time>
                                        </div>
                                    </div>

                                    <a href="{{ route('posts.show', $displayPost->uid) }}" class="block">
                                        <p class="skuy-post__text">{{ $displayPost->content }}</p>

                                        @if ($mediaPaths !== [])
                                            @if (count($mediaPaths) === 1)
                                                <div class="skuy-profile-post__single-media mb-3">
                                            @else
                                                <div class="skuy-media-track mb-3">
                                            @endif
                                                @foreach ($mediaPaths as $mediaPath)
                                                    @php($mediaUrl = asset('storage/' . $mediaPath))
                                                    @if (count($mediaPaths) === 1)
                                                        <img src="{{ $mediaUrl }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                    @else
                                                        <div class="skuy-media-item">
                                                            <img src="{{ $mediaUrl }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </a>

                                    <div class="skuy-profile-post__footer">
                                        <a href="{{ route('posts.show', $displayPost->uid) }}" class="skuy-profile-post__view-link">
                                            {{ __('threads.actions.view_thread') }}
                                        </a>

                                        <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark !border-[var(--skuy-secondary)] !bg-[var(--skuy-selected)] !text-[var(--skuy-on-selected)]">
                                                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-3.5L5 21V4a1 1 0 0 1 1-1Z"></path></svg>
                                                <span class="text-xs font-semibold">{{ __('threads.actions.delete') }}</span>
                                            </button>
                                        </form>
                                    </div>
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
