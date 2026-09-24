@foreach ($posts as $post)
    @php
        $isRepost = $post->repost_id !== null;
        $displayPost = $isRepost ? $post->originalPost : $post;
    @endphp

    <article
        data-feed-thread-key="{{ $post->uid }}"
        x-data="{ showComments: false }"
        @click="if(!$event.target.closest('button, a, form, img')) window.location='{{ route('posts.show', $displayPost->uid) }}'"
        class="skuy-post skuy-feed-post cursor-pointer"
    >
        @if ($isRepost)
            <div class="skuy-post__repost skuy-feed-post__repost">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path>
                </svg>
                <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
            </div>
        @endif

        <div class="skuy-post__layout">
            <div class="skuy-post__avatar-column">
                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                    @if ($displayPost->user->avatar)
                        <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                    @else
                        {{ substr($displayPost->user->name, 0, 1) }}
                    @endif
                </a>
            </div>

            <div class="skuy-post__body">
                <div class="skuy-post__body-main">
                <div class="skuy-post__header">
                    <div class="skuy-post__identity">
                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__display-name">
                            {{ $displayPost->user->name }}
                        </a>
                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__username">
                            <span aria-hidden="true">@</span>{{ $displayPost->user->username }}
                        </a>

                        @if ($displayPost->user->is_verified)
                            <span class="inline-flex text-[var(--skuy-secondary)]" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                </svg>
                            </span>
                        @endif

                        @if ($displayPost->topic)
                            <span aria-hidden="true" class="text-[var(--skuy-text-muted)]">&middot;</span>
                            <a href="{{ route('topik.show', $displayPost->topic) }}" class="skuy-post__topic">#{{ $displayPost->topic }}</a>
                        @endif

                        <time datetime="{{ $displayPost->created_at->toIso8601String() }}" class="skuy-post__time">
                            {{ $displayPost->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}
                        </time>
                    </div>

                    @if ($displayPost->user_id === auth()->id())
                        <div
                            x-data="{
                                openOptions: false,
                                closeOptions(restoreFocus) {
                                    this.openOptions = false;

                                    if (restoreFocus) {
                                        this.$nextTick(() => {
                                            if (this.$refs.optionsTrigger) this.$refs.optionsTrigger.focus();
                                        });
                                    }
                                }
                            }"
                            @keydown.escape.window="if (openOptions) closeOptions(true)"
                            class="skuy-post__options"
                        >
                            <button
                                id="post-options-trigger-{{ $post->uid }}"
                                x-ref="optionsTrigger"
                                @click="openOptions = !openOptions"
                                type="button"
                                aria-label="{{ __('threads.actions.open_options') }}"
                                aria-expanded="false"
                                :aria-expanded="openOptions"
                                aria-controls="post-options-{{ $post->uid }}"
                                class="skuy-action"
                                aria-haspopup="menu"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="19" cy="12" r="1.5"></circle>
                                </svg>
                            </button>

                            <div id="post-options-{{ $post->uid }}" x-show="openOptions" @click.away="closeOptions()" style="display: none;" role="menu" aria-labelledby="post-options-trigger-{{ $post->uid }}" class="skuy-menu-surface absolute right-0 z-50 mt-2 w-44">
                                <a href="{{ route('posts.edit', $displayPost->uid) }}" class="skuy-menu-link flex items-center gap-2" role="menuitem">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    {{ __('threads.actions.edit') }}
                                </a>
                                <form action="{{ route('posts.destroy', $displayPost->uid) }}" method="POST" data-confirm="{{ __('threads.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="skuy-menu-link flex items-center gap-2 text-[var(--skuy-danger)]" role="menuitem">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M9 7V4h6v3m-9 0 1 13h10l1-13"></path></svg>
                                        {{ __('threads.actions.delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="skuy-post__content">
                    <p class="skuy-post__text">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="skuy-hashtag">#$1</a>', e($displayPost->content)) !!}</p>

                    @if ($displayPost->images && count($displayPost->images) > 0)
                        @php
                            $postImageUrls = collect($displayPost->images)
                                ->map(fn ($image) => asset('storage/' . $image))
                                ->values();
                        @endphp

                        <div x-data="{
                            activeSlide: 0,
                            updateDots(event) {
                                const maxIndex = {{ count($displayPost->images) - 1 }};
                                if (maxIndex === 0) return;

                                const maxScroll = event.target.scrollWidth - event.target.clientWidth;
                                if (maxScroll === 0) return;

                                this.activeSlide = Math.round((event.target.scrollLeft / maxScroll) * maxIndex);
                            }
                        }" class="mb-4 skuy-feed-post__media">
                            <div data-media-slider="true" @scroll.passive="updateDots" class="skuy-media-track{{ count($displayPost->images) === 1 ? ' skuy-feed-media-track--single' : '' }}">
                                @foreach ($displayPost->images as $imgUrl)
                                    <div data-media-slide="true" @click="openLightbox(@js($postImageUrls), {{ $loop->index }})" class="skuy-media-item">
                                        <img src="{{ asset('storage/' . $imgUrl) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                    </div>
                                @endforeach
                            </div>

                            @if (count($displayPost->images) > 1)
                                <div data-media-indicator="true" class="skuy-media-dots" aria-hidden="true">
                                    @foreach ($displayPost->images as $index => $img)
                                        <div data-media-indicator-dot="true" class="skuy-media-dot" :class="activeSlide === {{ $index }} ? 'is-active' : ''"></div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @php
                $compactActionCountThreshold = 1000;
                $formatActionCount = static function (int $count) use ($compactActionCountThreshold): string {
                    if ($count < $compactActionCountThreshold) {
                        return (string) $count;
                    }

                    foreach ([
                        'B' => 1_000_000_000,
                        'M' => 1_000_000,
                        'K' => 1_000,
                    ] as $suffix => $unit) {
                        if ($count >= $unit) {
                            return intdiv($count, $unit).$suffix;
                        }
                    }

                    return (string) $count;
                };
                $likeCount = (int) ($displayPost->likes_count ?? 0);
                $commentCount = (int) ($displayPost->comments_count ?? 0);
                $repostCount = (int) ($displayPost->reposts_count ?? 0);
                $likeDisplayCount = $likeCount > 0 ? $formatActionCount($likeCount) : '';
                $commentDisplayCount = $commentCount > 0 ? $formatActionCount($commentCount) : '';
                $repostDisplayCount = $repostCount > 0 ? $formatActionCount($repostCount) : '';
                $isDenseActionCount = static fn (string $displayCount): bool => strlen($displayCount) > 3;
                $likeActionCountIsDense = $isDenseActionCount($likeDisplayCount);
                $commentActionCountIsDense = $isDenseActionCount($commentDisplayCount);
                $repostActionCountIsDense = $isDenseActionCount($repostDisplayCount);
                $formatActionLabel = static fn (string $label, int $count): string => $count > 0
                    ? $label.' ('.$count.')'
                    : $label;
                $likeActionLabel = $formatActionLabel(
                    $displayPost->liked_by_me ? __('threads.actions.unlike') : __('threads.labels.like'),
                    $likeCount,
                );
                $commentActionLabel = $formatActionLabel(__('threads.actions.reply'), $commentCount);
                $repostActionLabel = $formatActionLabel(__('threads.actions.repost'), $repostCount);
            @endphp

                    <div
                        x-data="likeControl({
                            liked: @js((bool) $displayPost->liked_by_me),
                            likeCount: @js($likeCount),
                            postUid: @js((string) $displayPost->uid),
                            url: @js(route('posts.like', $displayPost->uid)),
                            labels: {
                                like: @js(__('threads.labels.like')),
                                unlike: @js(__('threads.actions.unlike')),
                                liked: @js(__('threads.feedback.liked')),
                                unliked: @js(__('threads.feedback.unliked')),
                                sessionExpired: @js(__('threads.feedback.session_expired')),
                                restricted: @js(__('threads.feedback.restricted')),
                                invalid: @js(__('threads.feedback.invalid')),
                                failed: @js(__('threads.feedback.failed')),
                                network: @js(__('threads.feedback.network')),
                            },
                        })"
                    >
                        <div class="skuy-actions skuy-feed-post__actions">
                            <form
                                action="{{ route('posts.like', $displayPost->uid) }}"
                                method="POST"
                                @submit.prevent="toggleLike($event)"
                            >
                            @csrf
                            <button
                                x-ref="likeButton"
                                type="submit"
                                aria-label="{{ $likeActionLabel }}"
                                title="{{ $likeActionLabel }}"
                                :aria-label="likeButtonLabel()"
                                :title="likeButtonLabel()"
                                aria-pressed="{{ $displayPost->liked_by_me ? 'true' : 'false' }}"
                                :aria-pressed="liked.toString()"
                                :aria-busy="isLikeRequestInFlight.toString()"
                                :disabled="isLikeRequestInFlight"
                                class="skuy-action skuy-action--reaction{{ $likeActionCountIsDense ? ' skuy-action--dense-count' : '' }}"
                                :class="liked ? '!border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : ''"
                            >
                                <svg x-ref="likeIcon" class="h-5 w-5 {{ $displayPost->liked_by_me ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"></path></svg>
                                <span
                                    x-show="likeDisplayCount()"
                                    x-text="likeDisplayCount()"
                                    @if ($likeDisplayCount === '') style="display: none;" @endif
                                >{{ $likeDisplayCount }}</span>
                            </button>
                        </form>

                        <button @click="showComments = !showComments" type="button" aria-label="{{ $commentActionLabel }}" title="{{ $commentActionLabel }}" :aria-expanded="showComments.toString()" aria-controls="comments-{{ $displayPost->uid }}" class="skuy-action{{ $commentActionCountIsDense ? ' skuy-action--dense-count' : '' }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.75L4 20l1.35-3.6A7.3 7.3 0 0 1 4 11.5 7.5 7.5 0 0 1 12 4a7.5 7.5 0 0 1 8 7.5Z"></path></svg>
                            <span>{{ $commentDisplayCount }}</span>
                        </button>

                        <form action="{{ route('posts.repost', $displayPost->uid) }}" method="POST">
                            @csrf
                            <button type="submit" aria-label="{{ $repostActionLabel }}" title="{{ $repostActionLabel }}" aria-pressed="{{ $displayPost->reposted_by_me ? 'true' : 'false' }}" class="skuy-action skuy-action--selected{{ $repostActionCountIsDense ? ' skuy-action--dense-count' : '' }}{{ $displayPost->reposted_by_me ? ' !border-[var(--skuy-secondary)] !bg-[var(--skuy-secondary)] !text-[var(--skuy-on-secondary)]' : '' }}">
                                <svg class="h-5 w-5 {{ $displayPost->reposted_by_me ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path></svg>
                                @if ($repostDisplayCount !== '')
                                    <span>{{ $repostDisplayCount }}</span>
                                @endif
                            </button>
                        </form>

                        @if ($displayPost->bookmarked_by_me)
                            <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark !border-[var(--skuy-secondary)] !bg-[var(--skuy-selected)] !text-[var(--skuy-on-selected)]">
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-3.5L5 21V4a1 1 0 0 1 1-1Z"></path></svg>
                                </button>
                            </form>
                        @else
                            <form action="{{ route('bookmarks.store', $displayPost->uid) }}" method="POST">
                                @csrf
                                <button type="submit" aria-label="{{ __('threads.actions.save') }}" title="{{ __('threads.actions.save') }}" aria-pressed="false" class="skuy-action skuy-action--bookmark">
                                    <svg class="h-5 w-5 fill-none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a1 1 0 0 1 1 1v17l-7-3.5L5 21V4a1 1 0 0 1 1-1Z"></path></svg>
                                </button>
                            </form>
                        @endif

                        <button @click="copyAndToast('{{ route('posts.show', $displayPost->uid) }}', '{{ __('threads.thread_link_copied') }}')" type="button" aria-label="{{ __('threads.actions.share') }}" title="{{ __('threads.actions.share') }}" class="skuy-action">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 18-9-3-9 3 9-18Zm0 0v15"></path></svg>
                        </button>
                        </div>
                        <div class="min-h-5">
                            <p
                                x-show="feedbackMessage"
                                x-text="feedbackMessage"
                                :role="feedbackIsError ? 'alert' : 'status'"
                                :aria-live="feedbackIsError ? 'assertive' : 'polite'"
                                aria-atomic="true"
                                style="display: none;"
                                :class="feedbackIsError ? 'text-[var(--skuy-danger)]' : 'text-[var(--skuy-secondary)]'"
                                class="text-xs font-semibold"
                            ></p>
                        </div>
                    </div>

                    @php
                        $topComment = $displayPost->feedComments->first();
                    @endphp

                    @if ($topComment)
                        <div x-show="!showComments" class="skuy-comment-preview skuy-feed-post__comment-preview">
                            <div class="skuy-comment">
                                @if ($topComment->user->avatar)
                                    <div class="skuy-avatar skuy-avatar--small">
                                        <img src="{{ asset('storage/' . $topComment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $topComment->user->name]) }}">
                                    </div>
                                @else
                                    <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => $topComment->user->name]) }}">
                                        {{ substr($topComment->user->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="skuy-comment__body">
                                    <div class="skuy-comment__identity">
                                        <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $topComment->user->username }}</span>
                                        <span class="skuy-comment__time">{{ $topComment->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</span>
                                    </div>
                                    <p class="skuy-comment__text">{{ $topComment->content }}</p>

                                    @if ($displayPost->root_comments_count > 1)
                                        <button @click="showComments = true" type="button" class="skuy-comment__reply">
                                            {{ trans_choice('threads.comments.more', $displayPost->root_comments_count - 1, ['count' => $displayPost->root_comments_count - 1]) }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div id="comments-{{ $displayPost->uid }}" x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="skuy-comments skuy-feed-post__comments">
                        <div class="space-y-4">
                            <a href="{{ route('posts.show', $displayPost->uid) }}" class="inline-flex min-h-[36px] items-center text-sm font-semibold text-[var(--skuy-secondary)] hover:underline">
                                {{ __('threads.actions.view_full_thread') }}
                            </a>

                            @foreach ($displayPost->feedComments as $comment)
                                <div x-data="{ openReply: false, isSubmitting: false }" class="skuy-comment">
                                    @if ($comment->user->avatar)
                                        <div class="skuy-avatar skuy-avatar--small">
                                            <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}">
                                        </div>
                                    @else
                                        <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}">
                                            {{ substr($comment->user->name, 0, 1) }}
                                        </div>
                                    @endif

                                    <div class="skuy-comment__body">
                                        <div class="skuy-comment__identity">
                                            <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $comment->user->username }}</span>
                                            <span class="skuy-comment__time">{{ $comment->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</span>
                                        </div>
                                        <p class="skuy-comment__text">{{ $comment->content }}</p>
                                        <button @click="openReply = !openReply" type="button" :aria-expanded="openReply.toString()" class="skuy-comment__reply">
                                            {{ __('threads.actions.reply') }}
                                        </button>

                                        <div x-show="openReply" style="display: none;" class="mt-2">
                                            <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="skuy-inline-form" @submit="isSubmitting = true">
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
                                                        @if ($reply->user->avatar)
                                                            <div class="skuy-avatar skuy-avatar--tiny">
                                                                <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}">
                                                            </div>
                                                        @else
                                                            <div class="skuy-avatar skuy-avatar--tiny" aria-label="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}">
                                                                {{ substr($reply->user->name, 0, 1) }}
                                                            </div>
                                                        @endif

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

                        <div x-data="{ isSubmitting: false }" class="skuy-inline-form">
                            @if (Auth::user()->avatar)
                                <div class="skuy-avatar skuy-avatar--small">
                                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                </div>
                            @else
                                <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                            @endif

                            <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="flex min-w-0 flex-1 items-center gap-2" @submit="isSubmitting = true">
                                @csrf
                                <input type="text" name="content" class="skuy-inline-input" placeholder="{{ __('threads.comments.add') }}" required>
                                <button type="submit" :disabled="isSubmitting" class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs">
                                    <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                    <span x-show="isSubmitting">...</span>
                                </button>
                            </form>
                        </div>
                    </div>
            </div>
        </div>
    </article>
@endforeach
