@foreach ($posts as $post)
    @php
        $isRepost = $post->repost_id !== null;
        $displayPost = $isRepost ? $post->originalPost : $post;
        $mediaImages = collect(is_array($displayPost->images) ? $displayPost->images : [])
            ->filter(fn ($image) => is_string($image) && trim($image) !== '')
            ->unique()
            ->values();

        if ($mediaImages->isEmpty() && is_string($displayPost->image) && trim($displayPost->image) !== '') {
            $mediaImages = collect([$displayPost->image]);
        }
    @endphp

    <article
        data-feed-thread-key="{{ $post->uid }}"
        data-feed-post-uid="{{ $displayPost->uid }}"
        data-feed-comment-count-value="{{ (int) ($displayPost->comments_count ?? 0) }}"
        data-feed-root-comment-count-value="{{ (int) ($displayPost->root_comments_count ?? 0) }}"
        x-data="feedCommentControl({
            postUid: @js((string) $displayPost->uid),
            commentCount: @js((int) ($displayPost->comments_count ?? 0)),
            rootCommentCount: @js((int) ($displayPost->root_comments_count ?? 0)),
            replyLabel: @js(__('threads.actions.reply')),
            moreCommentsLabel: @js(trans_choice('threads.comments.more', 2, ['count' => '__COUNT__'])),
        })"
        @feed-comment-created="commentCreated($event)"
        @click="if(!$event.target.closest('button, a, form, img, input, textarea, select, [contenteditable]')) window.location='{{ route('posts.show', $displayPost->uid) }}'"
        class="skuy-post skuy-feed-post skuy-social-post cursor-pointer"
    >
        @if ($isRepost)
            <div class="skuy-post__repost skuy-feed-post__repost">
                <x-fa-icon name="retweet" class="text-base" />
                <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
            </div>
        @endif

        <div class="skuy-post__body">
            <div class="skuy-post__body-main">
                <div class="skuy-post__header skuy-feed-post__header">
                    <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-avatar skuy-feed-post__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                        @if ($displayPost->user->avatar)
                            <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                        @else
                            {{ mb_substr($displayPost->user->name, 0, 1) }}
                        @endif
                    </a>
                    <div class="skuy-post__identity">
                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__display-name">
                            {{ $displayPost->user->name }}
                        </a>
                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__username">
                            <span aria-hidden="true">@</span>{{ $displayPost->user->username }}
                        </a>

                        @if ($displayPost->user->is_verified)
                            <x-verified-badge :flow="(bool) ($flowVerifiedBadge ?? false)" />
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
                                postUid: @js((string) $displayPost->uid),
                                bookmarkState: @js((bool) $displayPost->bookmarked_by_me),
                                closeOptions(restoreFocus) {
                                    this.openOptions = false;

                                    if (restoreFocus) {
                                        this.$nextTick(() => {
                                            if (this.$refs.optionsTrigger) this.$refs.optionsTrigger.focus();
                                        });
                                    }
                                },
                                syncBookmarkState(event) {
                                    const state = event?.detail;
                                    if (state?.postUid !== this.postUid || typeof state.bookmarked !== 'boolean') return;

                                    const changed = this.bookmarkState !== state.bookmarked;
                                    this.bookmarkState = state.bookmarked;
                                    if (changed) this.closeOptions(true);
                                }
                            }"
                            @keydown.escape.window="if (openOptions) closeOptions(true)"
                            @skuy-post-action-state.window="syncBookmarkState($event)"
                            class="skuy-post__options"
                            data-mobile-post-options="{{ $displayPost->uid }}"
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
                                <x-fa-icon name="ellipsis" class="text-xl" />
                            </button>

                            <div id="post-options-{{ $displayPost->uid }}" x-show="openOptions" @click.away="closeOptions()" style="display: none;" role="menu" aria-labelledby="post-options-trigger-{{ $displayPost->uid }}" class="skuy-menu-surface absolute right-0 z-50 mt-2 w-44">
                                <form
                                    action="{{ $displayPost->bookmarked_by_me ? route('bookmarks.destroy', $displayPost->uid) : route('bookmarks.store', $displayPost->uid) }}"
                                    method="POST"
                                    class="skuy-bookmark-menu-action"
                                    x-data="likeControl({
                                        liked: @js((bool) $displayPost->liked_by_me),
                                        likeCount: @js((int) ($displayPost->likes_count ?? 0)),
                                        reposted: @js((bool) $displayPost->reposted_by_me),
                                        repostCount: @js((int) ($displayPost->reposts_count ?? 0)),
                                        bookmarked: @js((bool) $displayPost->bookmarked_by_me),
                                        postUid: @js((string) $displayPost->uid),
                                        urls: {
                                            bookmarkStore: @js(route('bookmarks.store', $displayPost->uid)),
                                            bookmarkDestroy: @js(route('bookmarks.destroy', $displayPost->uid)),
                                        },
                                        labels: {
                                            save: @js(__('threads.actions.save')),
                                            removeFromSaved: @js(__('threads.actions.remove_from_saved')),
                                            sessionExpired: @js(__('threads.feedback.session_expired')),
                                            restricted: @js(__('threads.feedback.restricted')),
                                            invalid: @js(__('threads.feedback.invalid')),
                                            failed: @js(__('threads.feedback.failed')),
                                            network: @js(__('threads.feedback.network')),
                                        },
                                    })"
                                    x-on:skuy-post-action-state.window="syncPeerState($event)"
                                    x-on:submit.prevent="toggleBookmark($event)"
                                    :action="bookmarkFormAction()"
                                >
                                    @csrf
                                    <input type="hidden" name="_method" value="DELETE" x-bind:disabled="!bookmarked" @if (! $displayPost->bookmarked_by_me) disabled @endif>
                                    <button
                                        type="submit"
                                        class="skuy-menu-link flex w-full items-center gap-2"
                                        role="menuitem"
                                        aria-label="{{ $displayPost->bookmarked_by_me ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}"
                                        :aria-label="bookmarkButtonLabel()"
                                        aria-pressed="{{ $displayPost->bookmarked_by_me ? 'true' : 'false' }}"
                                        :aria-pressed="bookmarked.toString()"
                                        :aria-busy="isBookmarkRequestInFlight.toString()"
                                        :disabled="isBookmarkRequestInFlight"
                                    >
                                        <x-fa-icon x-ref="bookmarkIcon" name="bookmark" :weight="$displayPost->bookmarked_by_me ? 'solid' : 'regular'" class="text-base" />
                                        <span x-text="bookmarkButtonLabel()">{{ $displayPost->bookmarked_by_me ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}</span>
                                    </button>
                                    <p
                                        x-show="feedbackMessage"
                                        x-text="feedbackMessage"
                                        :role="feedbackIsError ? 'alert' : 'status'"
                                        :aria-live="feedbackIsError ? 'assertive' : 'polite'"
                                        aria-atomic="true"
                                        style="display: none;"
                                        class="px-3 py-2 text-xs font-semibold text-[var(--skuy-danger)]"
                                    ></p>
                                </form>
                                <a href="{{ route('posts.edit', $displayPost->uid) }}" class="skuy-menu-link flex items-center gap-2" role="menuitem">
                                    <x-fa-icon name="pen-to-square" class="text-base" />
                                    {{ __('threads.actions.edit') }}
                                </a>
                                <form action="{{ route('posts.destroy', $displayPost->uid) }}" method="POST" data-confirm="{{ __('threads.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="skuy-menu-link flex items-center gap-2 text-[var(--skuy-danger)]" role="menuitem">
                                        <x-fa-icon name="trash-can" weight="solid" class="text-base" />
                                        {{ __('threads.actions.delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        @include('partials.post-mobile-bookmark-menu', ['displayPost' => $displayPost])
                    @endif
                </div>

                <div class="skuy-post__content">
                    <p class="skuy-post__text">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="skuy-hashtag">#$1</a>', e($displayPost->content)) !!}</p>

                    @if ($mediaImages->isNotEmpty())
                        @php
                            $postImageUrls = $mediaImages
                                ->map(fn ($image) => asset('storage/' . $image))
                                ->values();
                        @endphp

                        @if ($mediaImages->count() === 1)
                            <div class="skuy-feed-post__media skuy-feed-post__media--single">
                                <div class="skuy-media-track skuy-feed-media-track--single">
                                    <button
                                        type="button"
                                        @click.stop="openLightbox(@js($postImageUrls), 0, $event)"
                                        aria-label="{{ __('threads.media.image_position', ['current' => 1, 'total' => 1]) }}"
                                        class="skuy-media-item"
                                    >
                                        <img src="{{ asset('storage/' . $mediaImages->first()) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                    </button>
                                </div>
                            </div>
                        @else
                        <div
                            x-data="{
                                activeSlide: 0,
                                updateDots(event) {
                                    const slider = event.target;
                                    const slides = Array.from(slider.querySelectorAll('[data-media-slide=true]'));
                                    if (slides.length < 2) return;

                                    const sliderCenter = slider.getBoundingClientRect().left + (slider.clientWidth / 2);
                                    let nearestDistance = Number.POSITIVE_INFINITY;
                                    let nearestIndex = 0;

                                    slides.forEach((slide, index) => {
                                        const slideRect = slide.getBoundingClientRect();
                                        const distance = Math.abs((slideRect.left + (slideRect.width / 2)) - sliderCenter);
                                        if (distance < nearestDistance) {
                                            nearestDistance = distance;
                                            nearestIndex = index;
                                        }
                                    });

                                    this.activeSlide = nearestIndex;
                                },
                                moveSlide(direction) {
                                    const slider = this.$refs.mediaTrack;
                                    const slides = Array.from(slider?.querySelectorAll('[data-media-slide=true]') ?? []);
                                    if (slides.length < 2) return;

                                    const targetIndex = Math.max(0, Math.min(slides.length - 1, this.activeSlide + direction));
                                    const slide = slides[targetIndex];
                                    const targetLeft = slider.scrollLeft
                                        + slide.getBoundingClientRect().left
                                        - slider.getBoundingClientRect().left
                                        - ((slider.clientWidth - slide.clientWidth) / 2);
                                    const maxScroll = Math.max(0, slider.scrollWidth - slider.clientWidth);
                                    const left = Math.max(0, Math.min(maxScroll, targetLeft));
                                    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                                    if (typeof slider.scrollTo === 'function') {
                                        slider.scrollTo({ left, behavior: reduceMotion ? 'auto' : 'smooth' });
                                    } else {
                                        slider.scrollLeft = left;
                                    }
                                    this.activeSlide = targetIndex;
                                }
                            }"
                            class="skuy-feed-post__media skuy-feed-post__media--{{ $mediaImages->count() === 1 ? 'single' : 'carousel' }}"
                        >
                            <div
                                data-media-slider="true"
                                x-ref="mediaTrack"
                                @scroll.passive="updateDots"
                                @if ($mediaImages->count() > 1)
                                    role="region"
                                    aria-label="{{ __('threads.media.photo_gallery') }}"
                                    aria-roledescription="carousel"
                                    tabindex="0"
                                    @keydown.arrow-left.prevent.stop="moveSlide(-1)"
                                    @keydown.arrow-right.prevent.stop="moveSlide(1)"
                                @endif
                                class="skuy-media-track{{ $mediaImages->count() === 1 ? ' skuy-feed-media-track--single' : ' skuy-feed-media-track--carousel' }}"
                            >
                                @foreach ($mediaImages as $index => $imgUrl)
                                    <button
                                        type="button"
                                        data-media-slide="true"
                                        @click.stop="openLightbox(@js($postImageUrls), {{ $loop->index }}, $event)"
                                        :aria-current="activeSlide === {{ $loop->index }} ? 'true' : null"
                                        aria-label="{{ __('threads.media.image_position', ['current' => $loop->iteration, 'total' => $mediaImages->count()]) }}"
                                        class="skuy-media-item{{ $mediaImages->count() > 1 ? ' skuy-feed-media-item--portrait-preview' : '' }}"
                                    >
                                        <img src="{{ asset('storage/' . $imgUrl) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                    </button>
                                @endforeach
                            </div>

                            @if ($mediaImages->count() > 1)
                                <div class="skuy-media-gallery__controls" role="group" aria-label="{{ __('threads.media.photo_gallery') }}">
                                    <button type="button" data-feed-media-previous="true" @click.stop="moveSlide(-1)" :disabled="activeSlide === 0" aria-label="{{ __('threads.media.previous_image') }}" class="skuy-media-gallery__control">
                                        <x-fa-icon name="chevron-left" />
                                    </button>
                                    <button type="button" data-feed-media-next="true" @click.stop="moveSlide(1)" :disabled="activeSlide === {{ $mediaImages->count() - 1 }}" aria-label="{{ __('threads.media.next_image') }}" class="skuy-media-gallery__control">
                                        <x-fa-icon name="chevron-right" />
                                    </button>
                                </div>
                                <div data-media-indicator="true" class="skuy-media-dots" aria-hidden="true">
                                    @foreach ($mediaImages as $index => $img)
                                        <div data-media-indicator-dot="true" class="skuy-media-dot" :class="activeSlide === {{ $index }} ? 'is-active' : ''"></div>
                                    @endforeach
                                </div>
                                <span class="sr-only" role="status" aria-live="polite" x-text="'{{ __('threads.media.image_position', ['current' => '__CURRENT__', 'total' => $mediaImages->count()]) }}'.replace('__CURRENT__', activeSlide + 1)"></span>
                            @endif
                        </div>
                        @endif
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
                            reposted: @js((bool) $displayPost->reposted_by_me),
                            repostCount: @js($repostCount),
                            bookmarked: @js((bool) $displayPost->bookmarked_by_me),
                            postUid: @js((string) $displayPost->uid),
                            urls: {
                                like: @js(route('posts.like', $displayPost->uid)),
                                repost: @js(route('posts.repost', $displayPost->uid)),
                                bookmarkStore: @js(route('bookmarks.store', $displayPost->uid)),
                                bookmarkDestroy: @js(route('bookmarks.destroy', $displayPost->uid)),
                            },
                            labels: {
                                like: @js(__('threads.labels.like')),
                                unlike: @js(__('threads.actions.unlike')),
                                reply: @js(__('threads.actions.reply')),
                                repost: @js(__('threads.actions.repost')),
                                save: @js(__('threads.actions.save')),
                                removeFromSaved: @js(__('threads.actions.remove_from_saved')),
                                liked: @js(__('threads.feedback.liked')),
                                unliked: @js(__('threads.feedback.unliked')),
                                sessionExpired: @js(__('threads.feedback.session_expired')),
                                restricted: @js(__('threads.feedback.restricted')),
                                invalid: @js(__('threads.feedback.invalid')),
                                failed: @js(__('threads.feedback.failed')),
                                network: @js(__('threads.feedback.network')),
                            },
                        })"
                        x-on:skuy-post-action-state.window="syncPeerState($event)"
                    >
                        <div class="skuy-actions skuy-actions--primary skuy-feed-post__actions">
                            <form
                                action="{{ route('posts.like', $displayPost->uid) }}"
                                method="POST"
                                @submit.prevent="toggleLike($event)"
                            >
                                @csrf
                                <button
                                    x-ref="likeButton"
                                    data-feed-post-action="like"
                                    type="submit"
                                    aria-label="{{ $likeActionLabel }}"
                                    title="{{ $likeActionLabel }}"
                                    :aria-label="likeButtonLabel()"
                                    :title="likeButtonLabel()"
                                    aria-pressed="{{ $displayPost->liked_by_me ? 'true' : 'false' }}"
                                    :aria-pressed="liked.toString()"
                                    :aria-busy="isLikeRequestInFlight.toString()"
                                    :disabled="isLikeRequestInFlight"
                                    data-mobile-primary-action="like"
                                    class="skuy-action skuy-action--reaction{{ $likeActionCountIsDense ? ' skuy-action--dense-count' : '' }}"
                                >
                                    <x-fa-icon x-ref="likeIcon" name="heart" :weight="$displayPost->liked_by_me ? 'solid' : 'regular'" class="text-xl" />
                                    <span
                                        x-show="likeDisplayCount()"
                                        x-text="likeDisplayCount()"
                                        @if ($likeDisplayCount === '') style="display: none;" @endif
                                    >{{ $likeDisplayCount }}</span>
                                </button>
                            </form>

                            @include('partials.post-appreciation', ['displayPost' => $displayPost, 'surface' => 'feed'])

                            <button data-mobile-primary-action="comment" data-feed-comment-action @click="showComments = !showComments" type="button" aria-label="{{ $commentActionLabel }}" title="{{ $commentActionLabel }}" :aria-expanded="showComments.toString()" aria-controls="comments-{{ $displayPost->uid }}" class="skuy-action{{ $commentActionCountIsDense ? ' skuy-action--dense-count' : '' }}">
                                <x-fa-icon name="comment" class="text-xl" />
                                <span data-feed-comment-count @if ($commentDisplayCount === '') style="display: none;" @endif>{{ $commentDisplayCount }}</span>
                            </button>

                            <form
                                action="{{ route('posts.repost', $displayPost->uid) }}"
                                method="POST"
                                @submit.prevent="toggleRepost($event)"
                            >
                                @csrf
                                <button
                                    x-ref="repostButton"
                                    data-feed-post-action="repost"
                                    type="submit"
                                    aria-label="{{ $repostActionLabel }}"
                                    title="{{ $repostActionLabel }}"
                                    :aria-label="repostButtonLabel()"
                                    :title="repostButtonLabel()"
                                    aria-pressed="{{ $displayPost->reposted_by_me ? 'true' : 'false' }}"
                                    :aria-pressed="reposted.toString()"
                                    :aria-busy="isRepostRequestInFlight.toString()"
                                    :disabled="isRepostRequestInFlight"
                                    data-mobile-primary-action="repost"
                                    class="skuy-action skuy-action--selected{{ $repostActionCountIsDense ? ' skuy-action--dense-count' : '' }}"
                                >
                                    <x-fa-icon x-ref="repostIcon" name="retweet" :weight="$displayPost->reposted_by_me ? 'solid' : 'regular'" class="text-xl" />
                                    <span
                                        x-show="repostDisplayCount() !== ''"
                                        x-text="repostDisplayCount()"
                                        @if ($repostDisplayCount === '') style="display: none;" @endif
                                    >{{ $repostDisplayCount }}</span>
                                </button>
                            </form>

                            <form
                                class="skuy-post-actions__desktop-bookmark"
                                action="{{ $displayPost->bookmarked_by_me ? route('bookmarks.destroy', $displayPost->uid) : route('bookmarks.store', $displayPost->uid) }}"
                                method="POST"
                                :action="bookmarkFormAction()"
                                x-on:submit.prevent="toggleBookmark($event)"
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="_method"
                                    value="DELETE"
                                    x-bind:disabled="!bookmarked"
                                    @if (! $displayPost->bookmarked_by_me) disabled @endif
                                >
                                <button
                                    x-ref="bookmarkButton"
                                    data-feed-post-action="bookmark"
                                    type="submit"
                                    aria-label="{{ $displayPost->bookmarked_by_me ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}"
                                    title="{{ $displayPost->bookmarked_by_me ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}"
                                    :aria-label="bookmarkButtonLabel()"
                                    :title="bookmarkButtonLabel()"
                                    aria-pressed="{{ $displayPost->bookmarked_by_me ? 'true' : 'false' }}"
                                    :aria-pressed="bookmarked.toString()"
                                    :aria-busy="isBookmarkRequestInFlight.toString()"
                                    :disabled="isBookmarkRequestInFlight"
                                    class="skuy-action skuy-action--bookmark"
                                >
                                    <x-fa-icon x-ref="bookmarkIcon" name="bookmark" :weight="$displayPost->bookmarked_by_me ? 'solid' : 'regular'" class="text-xl" />
                                </button>
                            </form>

                            <button data-mobile-primary-action="share" @click="copyAndToast('{{ route('posts.show', $displayPost->uid) }}', '{{ __('threads.thread_link_copied') }}')" type="button" aria-label="{{ __('threads.actions.share') }}" title="{{ __('threads.actions.share') }}" class="skuy-action">
                                <x-fa-icon name="share-nodes" class="text-xl" />
                            </button>
                        </div>
                        <div class="skuy-feed-post__feedback-region">
                            @include('partials.post-appreciation-feedback', ['displayPost' => $displayPost])
                            <p
                                x-show="feedbackMessage"
                                x-text="feedbackMessage"
                                :role="feedbackIsError ? 'alert' : 'status'"
                                :aria-live="feedbackIsError ? 'assertive' : 'polite'"
                                aria-atomic="true"
                                style="display: none; font-size: 0.75rem; font-weight: 600;"
                                :class="feedbackIsError ? 'text-[var(--skuy-danger)]' : 'text-[var(--skuy-secondary)]'"
                                class="min-h-5"
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

                                    <button
                                        data-feed-more-comments
                                        @if ($displayPost->root_comments_count <= 1) style="display: none;" @endif
                                        @click="showComments = true"
                                        type="button"
                                        class="skuy-comment__reply"
                                    >
                                        {{ trans_choice('threads.comments.more', max(1, $displayPost->root_comments_count - 1), ['count' => max(1, $displayPost->root_comments_count - 1)]) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div id="comments-{{ $displayPost->uid }}" x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="skuy-comments skuy-feed-post__comments">
                        <div class="space-y-4">
                            <a href="{{ route('posts.show', $displayPost->uid) }}" class="inline-flex min-h-[36px] items-center text-sm font-semibold text-[var(--skuy-secondary)] hover:underline">
                                {{ __('threads.actions.view_full_thread') }}
                            </a>

                            <div data-feed-created-roots></div>

                            @foreach ($displayPost->feedComments as $comment)
                                <div x-data="{ openReply: false }" data-feed-comment-id="{{ $comment->id }}" class="skuy-comment">
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
                                        @include('partials.comment-edit-control', ['comment' => $comment])
                                        <button @click="openReply = !openReply" type="button" :aria-expanded="openReply.toString()" class="skuy-comment__reply">
                                            {{ __('threads.actions.reply') }}
                                        </button>

                                        <div x-show="openReply" style="display: none;" class="mt-2">
                                            <form
                                                action="{{ route('comments.store', $displayPost->uid) }}"
                                                method="POST"
                                                class="skuy-inline-form"
                                                x-data="feedCommentComposer({
                                                    url: @js(route('comments.store', $displayPost->uid)),
                                                    postUid: @js((string) $displayPost->uid),
                                                    parentId: @js((int) $comment->id),
                                                    labels: {
                                                        sessionExpired: @js(__('threads.comment_feedback.session_expired')),
                                                        restricted: @js(__('moderation.denials.social_write')),
                                                        invalid: @js(__('threads.comment_feedback.invalid')),
                                                        failed: @js(__('threads.comment_feedback.failed')),
                                                        network: @js(__('threads.comment_feedback.network')),
                                                    },
                                                })"
                                                @submit.prevent="submit($event)"
                                            >
                                                @csrf
                                                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                <input
                                                    x-model="content"
                                                    type="text"
                                                    name="content"
                                                    class="skuy-inline-input"
                                                    placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}"
                                                    required
                                                    maxlength="500"
                                                    autocomplete="off"
                                                    :disabled="pending"
                                                >
                                                <button type="submit" :disabled="pending || content.trim() === ''" :aria-busy="pending.toString()" class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs">
                                                    <span x-show="!pending">{{ __('threads.actions.send') }}</span>
                                                    <span x-show="pending">...</span>
                                                </button>
                                                <p x-show="error" x-text="error" style="display: none;" class="basis-full text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive"></p>
                                            </form>
                                        </div>

                                        @if ($comment->feedReplies->count() > 0)
                                            <div class="skuy-comment__replies" data-feed-reply-list>
                                                @foreach ($comment->feedReplies as $reply)
                                                    <div data-feed-comment-id="{{ $reply->id }}" class="skuy-comment">
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
                                                            @include('partials.comment-edit-control', ['comment' => $reply])
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="skuy-inline-form">
                            @if (Auth::user()->avatar)
                                <div class="skuy-avatar skuy-avatar--small">
                                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                </div>
                            @else
                                <div class="skuy-avatar skuy-avatar--small" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                            @endif

                            <form
                                action="{{ route('comments.store', $displayPost->uid) }}"
                                method="POST"
                                class="flex min-w-0 flex-1 items-center gap-2"
                                x-data="feedCommentComposer({
                                    url: @js(route('comments.store', $displayPost->uid)),
                                    postUid: @js((string) $displayPost->uid),
                                    parentId: null,
                                    labels: {
                                        sessionExpired: @js(__('threads.comment_feedback.session_expired')),
                                        restricted: @js(__('moderation.denials.social_write')),
                                        invalid: @js(__('threads.comment_feedback.invalid')),
                                        failed: @js(__('threads.comment_feedback.failed')),
                                        network: @js(__('threads.comment_feedback.network')),
                                    },
                                })"
                                @submit.prevent="submit($event)"
                            >
                                @csrf
                                <input
                                    x-model="content"
                                    type="text"
                                    name="content"
                                    class="skuy-inline-input"
                                    placeholder="{{ __('threads.comments.add') }}"
                                    required
                                    maxlength="500"
                                    autocomplete="off"
                                    :disabled="pending"
                                >
                                <button type="submit" :disabled="pending || content.trim() === ''" :aria-busy="pending.toString()" class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs">
                                    <span x-show="!pending">{{ __('threads.actions.send') }}</span>
                                    <span x-show="pending">...</span>
                                </button>
                                <p x-show="error" x-text="error" style="display: none;" class="basis-full text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive"></p>
                            </form>
                        </div>
                    </div>
            </div>
    </article>
@endforeach
