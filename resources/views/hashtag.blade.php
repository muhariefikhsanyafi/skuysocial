<x-app-layout>
    <div class="skuy-discovery-page" data-flow-discovery="hashtag">
        <div class="skuy-discovery-shell">
            <a href="{{ route('search') }}" class="skuy-discovery-back">
                <x-fa-icon name="arrow-left-long" />
                <span>{{ __('navigation.search') }}</span>
            </a>

            <header class="skuy-discovery-context" aria-labelledby="hashtag-page-title">
                <span class="skuy-discovery-context__icon" aria-hidden="true">#</span>
                <div class="skuy-discovery-context__body">
                    <p class="skuy-discovery-eyebrow">{{ __('search.hashtag.heading') }}</p>
                    <h1 id="hashtag-page-title" class="skuy-discovery-context__title">
                        <span aria-hidden="true">#</span>{{ $hashtag->name }}
                    </h1>
                    <p class="skuy-discovery-context__count">
                        {{ trans_choice('search.hashtag.found', $posts->total(), ['count' => $posts->total()]) }}
                    </p>
                </div>
            </header>

            <section class="skuy-discovery-listing" aria-labelledby="hashtag-threads-heading">
                <div class="skuy-discovery-listing__heading">
                    <h2 id="hashtag-threads-heading" class="skuy-discovery-section__title">{{ __('search.sections.threads') }}</h2>
                </div>

                @if ($posts->isEmpty())
                    <div class="skuy-discovery-empty" role="status">
                        <span class="skuy-discovery-empty__mark" aria-hidden="true">
                            <x-fa-icon name="comment-lines" />
                        </span>
                        <h3 class="skuy-discovery-empty__title">{{ __('search.empty.threads') }}</h3>
                    </div>
                @else
                    <div class="skuy-discovery-post-list">
                        @foreach ($posts as $post)
                            @php
                                $isRepost = $post->repost_id !== null;
                                $displayPost = $post->originalPost ?? $post;
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

                                $likeCount = (int) ($post->likes_count ?? 0);
                                $commentCount = (int) ($post->comments_count ?? 0);
                                $likeLabel = $post->liked_by_me ? __('threads.actions.unlike') : __('threads.labels.like');
                            @endphp

                            <article x-data="{ showComments: false }" class="skuy-post skuy-discovery-post" aria-labelledby="hashtag-post-{{ $post->uid }}">
                                @if ($isRepost)
                                    <div class="skuy-post__repost">
                                        <x-fa-icon name="retweet" class="text-base" />
                                        <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                                    </div>
                                @endif

                                <div class="skuy-post__layout">
                                    <div class="skuy-post__avatar-column">
                                        <a href="{{ route('user.profile', $post->user->username) }}" class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $post->user->name]) }}">
                                            @if ($post->user->avatar)
                                                <img src="{{ asset('storage/' . $post->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $post->user->name]) }}">
                                            @else
                                                <span aria-hidden="true">{{ substr($post->user->name, 0, 1) }}</span>
                                            @endif
                                        </a>
                                    </div>

                                    <div class="skuy-post__body">
                                        <div class="skuy-post__body-main">
                                            <header class="skuy-post__header">
                                                <div class="skuy-post__identity">
                                                    <a id="hashtag-post-{{ $post->uid }}" href="{{ route('user.profile', $post->user->username) }}" class="skuy-post__display-name">
                                                        {{ $post->user->name }}
                                                    </a>
                                                    <a href="{{ route('user.profile', $post->user->username) }}" class="skuy-post__username">
                                                        <span aria-hidden="true">@</span>{{ $post->user->username }}
                                                    </a>
                                                    @if ($post->user->is_verified)
                                                        <x-verified-badge />
                                                    @endif
                                                    @if ($post->topic)
                                                        <span aria-hidden="true" class="text-[var(--skuy-text-muted)]">&middot;</span>
                                                        <a href="{{ route('topik.show', $post->topic) }}" class="skuy-post__topic">#{{ $post->topic }}</a>
                                                    @endif
                                                    <time datetime="{{ $post->created_at->toIso8601String() }}" class="skuy-post__time">
                                                        {{ $post->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}
                                                    </time>
                                                </div>

                                                @if ($post->user_id === auth()->id())
                                                    <div class="skuy-discovery-post__owner-actions">
                                                        <a href="{{ route('posts.edit', $post->uid) }}" class="skuy-button skuy-button--quiet">{{ __('threads.actions.edit') }}</a>
                                                        <form action="{{ route('posts.destroy', $post->uid) }}" method="POST" data-confirm="{{ __('threads.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm);">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="skuy-button skuy-button--quiet skuy-button--danger">{{ __('threads.actions.delete') }}</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </header>

                                            <div class="skuy-post__content">
                                                <p class="skuy-post__text">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="skuy-hashtag">#$1</a>', e($displayPost->content ?? '')) !!}</p>

                                                @if (count($mediaPaths) === 1)
                                                    <div class="skuy-discovery-post__single-media">
                                                        <img src="{{ asset('storage/' . $mediaPaths[0]) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                    </div>
                                                @elseif (count($mediaPaths) > 1)
                                                    <div
                                                        x-data="{
                                                            activeSlide: 0,
                                                            updateDots(event) {
                                                                const maxIndex = {{ count($mediaPaths) - 1 }};
                                                                if (maxIndex === 0) return;
                                                                const maxScroll = event.target.scrollWidth - event.target.clientWidth;
                                                                if (maxScroll === 0) return;
                                                                this.activeSlide = Math.round((event.target.scrollLeft / maxScroll) * maxIndex);
                                                            }
                                                        }"
                                                        data-media-slider="true"
                                                        class="skuy-discovery-post__media-slider"
                                                    >
                                                        <div @scroll.passive="updateDots" data-media-slider-track="true" class="skuy-media-track flex gap-1.5 sm:gap-2 overflow-x-auto snap-x snap-mandatory pb-3 pt-1 w-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                                            @foreach ($mediaPaths as $mediaPath)
                                                                <div data-media-slide="true" class="skuy-media-item skuy-discovery-post__media-item snap-center shrink-0 w-[85%] sm:w-[48%]">
                                                                    <img src="{{ asset('storage/' . $mediaPath) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <div data-media-indicator="true" class="skuy-media-dots" aria-hidden="true">
                                                            @foreach ($mediaPaths as $index => $mediaPath)
                                                                <div data-media-indicator-dot="true" class="skuy-media-dot" :class="activeSlide === {{ $index }} ? 'is-active w-4 sm:w-5 bg-black dark:bg-white' : 'w-1.5 sm:w-2 bg-gray-300 dark:bg-[#242424]' "></div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="skuy-actions skuy-actions--primary skuy-discovery-post__actions">
                                                <form action="{{ route('posts.like', $post->uid) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" aria-label="{{ $likeLabel }}" title="{{ $likeLabel }}" aria-pressed="{{ $post->liked_by_me ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction">
                                                        <x-fa-icon name="heart" :weight="$post->liked_by_me ? 'solid' : 'regular'" class="text-xl" />
                                                        <span>{{ $likeCount > 0 ? $likeCount : '' }}</span>
                                                    </button>
                                                </form>

                                                <button @click="showComments = !showComments" type="button" aria-label="{{ __('threads.actions.reply') }}" title="{{ __('threads.actions.reply') }}" :aria-expanded="showComments.toString()" aria-controls="hashtag-comments-{{ $post->uid }}" class="skuy-action">
                                                    <x-fa-icon name="comment" class="text-xl" />
                                                    <span>{{ $commentCount > 0 ? $commentCount : '' }}</span>
                                                </button>

                                                <button type="button" aria-label="{{ __('threads.repost_coming_soon') }}" title="{{ __('threads.repost_coming_soon') }}" class="skuy-action">
                                                    <x-fa-icon name="retweet" class="text-xl" />
                                                </button>

                                                @if ($displayPost->bookmarked_by_me)
                                                    <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark">
                                                            <x-fa-icon name="bookmark" weight="solid" class="text-xl" />
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('bookmarks.store', $displayPost->uid) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" aria-label="{{ __('threads.actions.save') }}" title="{{ __('threads.actions.save') }}" aria-pressed="false" class="skuy-action skuy-action--bookmark">
                                                            <x-fa-icon name="bookmark" class="text-xl" />
                                                        </button>
                                                    </form>
                                                @endif

                                                <button type="button" aria-label="{{ __('threads.actions.share') }}" title="{{ __('threads.actions.share') }}" class="skuy-action">
                                                    <x-fa-icon name="share-nodes" class="text-xl" />
                                                </button>
                                            </div>

                                            <div class="skuy-discovery-post__footer">
                                                <a href="{{ route('posts.show', $post->uid) }}" class="skuy-discovery-post__view-link">
                                                    {{ __('threads.actions.view_thread') }}
                                                </a>
                                            </div>

                                            @php
                                                $topComment = $post->feedComments->first();
                                            @endphp

                                            @if ($topComment)
                                                <div x-show="!showComments" class="skuy-comment-preview">
                                                    <div class="skuy-comment">
                                                        <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => $topComment->user->name]) }}">
                                                            @if ($topComment->user->avatar)
                                                                <img src="{{ asset('storage/' . $topComment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $topComment->user->name]) }}">
                                                            @else
                                                                <span aria-hidden="true">{{ substr($topComment->user->name, 0, 1) }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="skuy-comment__body">
                                                            <div class="skuy-comment__identity">
                                                                <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $topComment->user->username }}</span>
                                                                <span class="skuy-comment__time">{{ $topComment->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</span>
                                                            </div>
                                                            <p class="skuy-comment__text">{{ $topComment->content }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <div id="hashtag-comments-{{ $post->uid }}" x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="skuy-comments">
                                                <a href="{{ route('posts.show', $post->uid) }}" class="skuy-discovery-post__view-link">
                                                    {{ __('threads.actions.view_full_thread') }}
                                                </a>

                                                <div class="skuy-discovery-comments-list">
                                                    @foreach ($post->feedComments as $comment)
                                                        <div x-data="{ openReply: false, isSubmitting: false }" class="skuy-comment">
                                                            <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}">
                                                                @if ($comment->user->avatar)
                                                                    <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}">
                                                                @else
                                                                    <span aria-hidden="true">{{ substr($comment->user->name, 0, 1) }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="skuy-comment__body">
                                                                <div class="skuy-comment__identity">
                                                                    <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $comment->user->username }}</span>
                                                                    <span class="skuy-comment__time">{{ $comment->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</span>
                                                                </div>
                                                                <p class="skuy-comment__text">{{ $comment->content }}</p>
                                                                <button @click="openReply = !openReply" type="button" :aria-expanded="openReply.toString()" class="skuy-comment__reply">{{ __('threads.actions.reply') }}</button>

                                                                <div x-show="openReply" style="display: none;">
                                                                    <form action="{{ route('comments.store', $post->uid) }}" method="POST" class="skuy-inline-form" @submit="isSubmitting = true">
                                                                        @csrf
                                                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                                        <input type="text" name="content" class="skuy-inline-input" placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}" required>
                                                                        <button type="submit" :disabled="isSubmitting" class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs">
                                                                            <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                                                            <span x-show="isSubmitting">...</span>
                                                                        </button>
                                                                    </form>
                                                                </div>

                                                                @if ($comment->feedReplies->count() > 0)
                                                                    <div class="skuy-comment__replies">
                                                                        @foreach ($comment->feedReplies as $reply)
                                                                            <div class="skuy-comment">
                                                                                <div class="skuy-avatar skuy-avatar--tiny" aria-label="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}">
                                                                                    @if ($reply->user->avatar)
                                                                                        <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}">
                                                                                    @else
                                                                                        <span aria-hidden="true">{{ substr($reply->user->name, 0, 1) }}</span>
                                                                                    @endif
                                                                                </div>
                                                                                <div class="skuy-comment__body">
                                                                                    <div class="skuy-comment__identity">
                                                                                        <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $reply->user->username }}</span>
                                                                                        <span class="skuy-comment__time">{{ $reply->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</span>
                                                                                    </div>
                                                                                    <p class="skuy-comment__text">{{ $reply->content }}</p>
                                                                                </div>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <form action="{{ route('comments.store', $post->uid) }}" method="POST" class="skuy-inline-form">
                                                    @csrf
                                                    <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                                        @if (Auth::user()->avatar)
                                                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                                        @else
                                                            <span aria-hidden="true">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                                        @endif
                                                    </div>
                                                    <input type="text" name="content" class="skuy-inline-input" placeholder="{{ __('threads.comments.add') }}" required autocomplete="off">
                                                    <button type="submit" class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs">{{ __('threads.actions.send') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($posts->hasPages())
                        <div class="skuy-discovery-pagination">
                            {{ $posts->links() }}
                        </div>
                    @endif
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
