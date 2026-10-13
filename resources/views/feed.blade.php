@php
    $feedCreators = collect($posts->items())
        ->map(fn ($post) => $post->repost_id !== null ? $post->originalPost?->user : $post->user)
        ->filter(fn ($creator) => $creator && $creator->id !== auth()->id())
        ->unique('id')
        ->take(5)
        ->values();
@endphp

<x-app-layout>
    <div class="skuy-feed-page" data-skuy-prism-feed data-flow-feed>
        <div class="skuy-feed-shell">
            <section
                class="skuy-feed-content"
                aria-labelledby="feed-title"
                x-data="{
                    openImage: false,
                    activeImage: '',
                    lightboxImages: [],
                    lightboxIndex: 0,
                    lightboxTrigger: null,
                    lightboxPointerStart: null,
                    previousBodyOverflow: '',
                    nextFeedUrl: '',
                    isLoadingMore: false,
                    feedLoadFailed: false,
                    openLightbox(images, index = 0, event = null) {
                        const normalizedImages = images.filter(image => typeof image === 'string' && image.length > 0);
                        if (normalizedImages.length === 0) return;

                        this.lightboxImages = normalizedImages;
                        this.lightboxIndex = Math.min(Math.max(index, 0), normalizedImages.length - 1);
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                        this.lightboxTrigger = event?.currentTarget ?? null;
                        this.previousBodyOverflow = document.body.style.overflow;
                        document.body.style.overflow = 'hidden';
                        this.openImage = true;
                        this.$nextTick(() => this.$refs.lightboxClose?.focus());
                    },
                    previousImage() {
                        if (this.lightboxImages.length <= 1 || this.lightboxIndex <= 0) return;

                        this.lightboxIndex -= 1;
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                    },
                    nextImage() {
                        if (this.lightboxImages.length <= 1 || this.lightboxIndex >= this.lightboxImages.length - 1) return;

                        this.lightboxIndex += 1;
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                    },
                    startLightboxPointer(event) {
                        this.lightboxPointerStart = { x: event.clientX, y: event.clientY };
                    },
                    endLightboxPointer(event) {
                        const start = this.lightboxPointerStart;
                        this.lightboxPointerStart = null;
                        if (!start || this.lightboxImages.length <= 1) return;

                        const deltaX = event.clientX - start.x;
                        const deltaY = event.clientY - start.y;
                        if (Math.abs(deltaX) < 48 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) return;

                        event.preventDefault();
                        event.stopPropagation();
                        if (deltaX < 0) this.nextImage();
                        else this.previousImage();
                    },
                    handleLightboxKeydown(event) {
                        if (!this.openImage) return;

                        if (event.key === 'Escape') {
                            event.preventDefault();
                            this.closeImage();
                            return;
                        }

                        if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            this.previousImage();
                            return;
                        }

                        if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            this.nextImage();
                            return;
                        }

                        if (event.key !== 'Tab') return;

                        const dialog = this.$refs.lightboxDialog;
                        const focusable = [...dialog.querySelectorAll('button:not(:disabled), [href], [tabindex]')]
                            .filter(element => element instanceof HTMLElement && element.tabIndex >= 0 && element.getClientRects().length > 0);
                        if (focusable.length === 0) {
                            event.preventDefault();
                            dialog.focus();
                            return;
                        }

                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];
                        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                            event.preventDefault();
                            last.focus();
                        } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                            event.preventDefault();
                            first.focus();
                        }
                    },
                    closeImage() {
                        this.openImage = false;
                        this.activeImage = '';
                        this.lightboxIndex = 0;
                        this.lightboxImages = [];
                        this.lightboxPointerStart = null;
                        document.body.style.overflow = this.previousBodyOverflow;
                        const trigger = this.lightboxTrigger;
                        this.lightboxTrigger = null;
                        this.$nextTick(() => trigger?.isConnected && trigger.focus());
                    },
                    closeImageIfActive(imageUrl) {
                        if (this.lightboxImages.includes(imageUrl)) this.closeImage();
                    },
                    appendFeedThreads(html) {
                        const template = document.createElement('template');
                        template.innerHTML = html.trim();

                        const incomingThreads = Array.from(template.content.children);
                        if (incomingThreads.some(thread => !thread.hasAttribute('data-feed-thread-key'))) {
                            throw new Error('Invalid Feed fragment.');
                        }

                        const loadedThreadKeys = new Set(
                            Array.from(this.$refs.feedThreads.children)
                                .map(thread => thread.dataset.feedThreadKey)
                                .filter(Boolean)
                        );

                        incomingThreads.forEach(thread => {
                            const threadKey = thread.dataset.feedThreadKey;
                            if (loadedThreadKeys.has(threadKey)) return;

                            this.$refs.feedThreads.appendChild(thread);
                            loadedThreadKeys.add(threadKey);
                        });
                    },
                    async loadMoreThreads(event) {
                        event.preventDefault();

                        if (this.isLoadingMore || !this.nextFeedUrl) return;

                        this.feedLoadFailed = false;
                        this.isLoadingMore = true;

                        try {
                            const nextUrl = new URL(this.nextFeedUrl, window.location.href);
                            if (nextUrl.origin !== window.location.origin) throw new Error('Invalid Feed URL.');

                            const response = await fetch(nextUrl, {
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Feed-Fragment': '1',
                                },
                            });

                            if (!response.ok) throw new Error('Feed request failed.');

                            const batch = await response.json();
                            if (
                                !batch
                                || typeof batch.html !== 'string'
                                || typeof batch.has_more !== 'boolean'
                                || (batch.has_more && (typeof batch.next_url !== 'string' || batch.next_url === ''))
                            ) {
                                throw new Error('Invalid Feed response.');
                            }

                            this.appendFeedThreads(batch.html);
                            this.nextFeedUrl = batch.has_more ? batch.next_url : '';
                            this.feedLoadFailed = false;
                        } catch (error) {
                            this.feedLoadFailed = true;
                        } finally {
                            this.isLoadingMore = false;
                        }
                    }
                }"
                data-feed-next-url="{{ $posts->nextPageUrl() }}"
                x-init="nextFeedUrl = $el.dataset.feedNextUrl || ''"
            >
                <header class="skuy-feed-page__header">
                    <h1 id="feed-title" class="sr-only">{{ __('navigation.feed') }}</h1>
                    <span class="skuy-feed-page__title" aria-hidden="true">{{ __('navigation.home') }}</span>
                    <nav class="skuy-flow-feed-tabs" data-flow-feed-tabs aria-label="{{ __('navigation.feed_filters') }}">
                        <a
                            href="{{ route('feed') }}"
                            data-flow-tab="for-you"
                            @if (! $isFollowingFeed) aria-current="page" @endif
                        >{{ __('navigation.for_you') }}</a>
                        <a
                            href="{{ route('feed', ['feed' => 'following']) }}"
                            data-flow-tab="following"
                            @if ($isFollowingFeed) aria-current="page" @endif
                        >{{ __('navigation.following_feed') }}</a>
                    </nav>
                </header>

                @include('partials.feed-stories-placeholder')

                <div class="skuy-feed-timeline">
                    <div id="composer" data-composer class="skuy-composer skuy-feed-composer">
                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="skuy-feed-composer__form" x-data="{
                            isSubmitting: false,
                            content: '',
                            selectedImages: [],
                            mediaMessage: '',
                            imageIdentity(file) {
                                return [file.name, file.size, file.type, file.lastModified].join('|');
                            },
                            syncImageInput() {
                                const transfer = new DataTransfer();
                                this.selectedImages.forEach(image => transfer.items.add(image.file));
                                this.$refs.imagesInput.files = transfer.files;
                            },
                            handleImageSelection(event) {
                                const newFiles = Array.from(event.target.files || []);
                                let skippedForLimit = false;

                                newFiles.forEach(file => {
                                    const identity = this.imageIdentity(file);

                                    if (this.selectedImages.some(image => image.identity === identity)) return;

                                    if (this.selectedImages.length >= 4) {
                                        skippedForLimit = true;
                                        return;
                                    }

                                    this.selectedImages.push({
                                        file,
                                        identity,
                                        previewUrl: URL.createObjectURL(file),
                                    });
                                });

                                this.syncImageInput();
                                this.mediaMessage = skippedForLimit ? '{{ __('threads.composer.max_images') }}' : '';
                            },
                            removeImage(index) {
                                const image = this.selectedImages[index];
                                if (!image) return;

                                URL.revokeObjectURL(image.previewUrl);
                                this.selectedImages.splice(index, 1);
                                this.syncImageInput();

                                if (this.selectedImages.length < 4) this.mediaMessage = '';
                            },
                            cleanupImagePreviews() {
                                this.selectedImages.forEach(image => URL.revokeObjectURL(image.previewUrl));
                            }
                        }" @submit="isSubmitting = true" @beforeunload.window="cleanupImagePreviews()">
                            @csrf

                            <div class="skuy-feed-composer__entry">
                                @if (Auth::user()->avatar)
                                    <div class="skuy-avatar skuy-feed-composer__avatar">
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                    </div>
                                @else
                                    <div class="skuy-avatar skuy-feed-composer__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="skuy-feed-composer__field">
                                    <label for="feed-composer-body" class="sr-only">{{ __('threads.composer.content_placeholder') }}</label>
                                    <textarea id="feed-composer-body" name="content" rows="2" x-model="content" class="skuy-composer__textarea" placeholder="{{ __('threads.composer.content_placeholder') }}" required></textarea>
                                </div>
                            </div>

                            <div
                                x-data="feedTopicAutocomplete(@js($availableTopics->values()->all()))"
                                @click.outside="close()"
                                class="skuy-composer__topic"
                            >
                                <label for="composer-topic" class="skuy-composer__topic-label">{{ __('threads.composer.topic_placeholder') }}</label>
                                <div class="relative min-w-0 flex-1">
                                    <input
                                        type="text"
                                        id="composer-topic"
                                        name="topic"
                                        x-model="topicQuery"
                                        @focus="open()"
                                        @input="handleInput()"
                                        @keydown="handleKeydown($event)"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                        aria-controls="composer-topic-suggestions"
                                        :aria-expanded="expanded.toString()"
                                        :aria-activedescendant="activeDescendant"
                                        class="skuy-composer__topic-input"
                                        maxlength="30"
                                        autocomplete="off"
                                    >
                                    <div
                                        id="composer-topic-suggestions"
                                        x-show="expanded"
                                        style="display: none;"
                                        role="listbox"
                                        aria-label="{{ __('threads.composer.topic_placeholder') }}"
                                        class="skuy-composer__topic-suggestions"
                                    >
                                        <template x-for="(topic, index) in filteredTopics" :key="topic">
                                            <button
                                                type="button"
                                                :id="`composer-topic-option-${index}`"
                                                role="option"
                                                tabindex="-1"
                                                :aria-selected="(activeIndex === index).toString()"
                                                :class="{ 'is-active': activeIndex === index }"
                                                @mouseenter="activeIndex = index"
                                                @mousedown.prevent
                                                @click.prevent.stop="selectTopic(topic)"
                                                class="skuy-composer__topic-option"
                                                x-text="topic"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            @if ($errors->any())
                                <div class="skuy-form-error" role="alert">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="skuy-composer__tools skuy-feed-composer__tools">
                                <div class="skuy-feed-composer__attachments min-w-0 flex-1">
                                    <input id="feed-composer-images" type="file" name="images[]" multiple accept="image/*" x-ref="imagesInput" @change="handleImageSelection($event)" class="skuy-upload skuy-feed-composer__file-input">
                                    <label for="feed-composer-images" class="skuy-feed-composer__attach">
                                        <x-fa-icon name="image" class="text-base" />
                                        <span>{{ __('threads.media.photo') }}</span>
                                    </label>

                                    <div x-show="selectedImages.length > 0" style="display: none;" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="{{ __('threads.media.preview') }}">
                                        <template x-for="(image, index) in selectedImages" :key="image.identity">
                                            <div class="relative aspect-square overflow-hidden rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)]">
                                                <button type="button" @click="openLightbox(selectedImages.map(item => item.previewUrl), index, $event)" :aria-label="'{{ __('threads.media.view_preview') }} ' + (index + 1)" class="skuy-preview-button block h-full w-full p-0">
                                                    <img :src="image.previewUrl" :alt="'{{ __('threads.media.preview') }} ' + (index + 1)" class="h-full w-full object-cover">
                                                </button>
                                                <button type="button" @click="closeImageIfActive(image.previewUrl); removeImage(index)" :disabled="isSubmitting" :aria-label="'{{ __('threads.media.remove_image') }} ' + (index + 1)" :title="'{{ __('threads.media.remove_image') }} ' + (index + 1)" class="skuy-preview-remove absolute right-1 top-1">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="mediaMessage" x-text="mediaMessage" style="display: none;" role="status" aria-live="polite" class="mt-2 text-sm font-semibold text-[var(--skuy-danger)]"></div>
                                </div>

                                <button type="submit" :disabled="isSubmitting || content.trim() === ''" :aria-busy="isSubmitting.toString()" aria-label="{{ __('threads.actions.post') }}" title="{{ __('threads.actions.post') }}" class="skuy-feed-composer__submit">
                                    <x-fa-icon name="arrow-up" weight="solid" class="text-lg" />
                                    <span x-show="!isSubmitting">{{ __('threads.actions.post') }}</span>
                                    <span class="sr-only" x-show="isSubmitting" style="display: none;">...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="feed-thread-list" x-ref="feedThreads" :aria-busy="isLoadingMore.toString()" class="skuy-feed-list">
                            @if ($posts->getCollection()->isEmpty())
                                <div class="skuy-flow-feed-empty" data-flow-empty role="status">
                                    <p>{{ $isFollowingFeed ? __('navigation.empty_following_feed') : __('navigation.empty_feed') }}</p>
                                    @if ($isFollowingFeed)
                                        <a href="{{ route('feed') }}">{{ __('navigation.return_to_for_you') }}</a>
                                    @endif
                                </div>
                            @else
                                @include('partials.feed-threads', ['posts' => $posts])
                            @endif
                    </div>
                </div>

                <div
                    x-show="openImage"
                    x-ref="lightboxDialog"
                    @click.self="closeImage()"
                    @keydown.window="handleLightboxKeydown($event)"
                    style="display: none;"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ __('threads.media.photo_preview') }}"
                    tabindex="-1"
                    class="skuy-lightbox fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-8"
                >
                    <span
                        x-show="lightboxImages.length > 1"
                        x-text="'{{ __('threads.media.image_position', ['current' => '__CURRENT__', 'total' => '__TOTAL__']) }}'.replace('__CURRENT__', lightboxIndex + 1).replace('__TOTAL__', lightboxImages.length)"
                        class="skuy-lightbox__counter"
                        role="status"
                        aria-live="polite"
                    ></span>
                    <button
                        type="button"
                        x-show="lightboxImages.length > 1"
                        @click.stop="previousImage()"
                        :disabled="lightboxIndex === 0"
                        aria-label="{{ __('threads.media.previous_image') }}"
                        class="skuy-lightbox__control absolute left-3 top-1/2 -translate-y-1/2 sm:left-8"
                    >
                        <x-fa-icon name="chevron-left" class="text-2xl" />
                    </button>
                    <button
                        type="button"
                        x-show="lightboxImages.length > 1"
                        @click.stop="nextImage()"
                        :disabled="lightboxIndex >= lightboxImages.length - 1"
                        aria-label="{{ __('threads.media.next_image') }}"
                        class="skuy-lightbox__control absolute right-3 top-1/2 -translate-y-1/2 sm:right-8"
                    >
                        <x-fa-icon name="chevron-right" class="text-2xl" />
                    </button>
                    <button
                        type="button"
                        x-ref="lightboxClose"
                        @click="closeImage()"
                        aria-label="{{ __('threads.media.close_preview') }}"
                        class="skuy-lightbox__control absolute right-3 top-3 sm:right-8 sm:top-8"
                    >
                        <x-fa-icon name="xmark" weight="solid" class="text-2xl" />
                    </button>
                    <img
                        :src="activeImage"
                        alt="{{ __('threads.media.full_photo') }}"
                        @pointerdown="startLightboxPointer($event)"
                        @pointerup="endLightboxPointer($event)"
                        @pointercancel="lightboxPointerStart = null"
                        class="skuy-lightbox__image"
                    >
                </div>

                @if ($posts->hasMorePages())
                    <div x-show="nextFeedUrl" class="mt-6 flex flex-col items-center">
                        <a href="{{ $posts->nextPageUrl() }}" :href="nextFeedUrl" rel="next" aria-controls="feed-thread-list" :aria-busy="isLoadingMore.toString()" :aria-disabled="isLoadingMore.toString()" @click="loadMoreThreads($event)" :class="isLoadingMore ? 'cursor-wait opacity-70' : ''" class="skuy-load-more min-h-[44px]">
                            <span x-show="!isLoadingMore">{{ __('threads.actions.view_more_threads') }}</span>
                            <span x-show="isLoadingMore" style="display: none;">{{ __('threads.actions.loading_threads') }}</span>
                        </a>
                        <span class="sr-only" role="status" aria-live="polite">
                            <span x-show="isLoadingMore" style="display: none;">{{ __('threads.actions.loading_threads') }}</span>
                        </span>
                        <p x-show="feedLoadFailed" style="display: none;" role="alert" class="mt-3 max-w-lg text-center text-sm font-semibold text-[var(--skuy-danger)]">
                            {{ __('threads.actions.load_more_failed') }}
                        </p>
                    </div>
                @endif
            </section>

            <aside class="skuy-context skuy-feed-context" aria-label="{{ __('threads.trending.title') }}">
                <div class="skuy-context-card skuy-feed-context-card">
                    <h2 class="skuy-context-card__title"><span class="skuy-flow-widget-icon skuy-flow-widget-icon--trending"><x-fa-icon name="fire" weight="solid" /></span>{{ __('threads.trending.title') }}</h2>
                    <div class="skuy-context-card__list">
                        @forelse($trendingHashtags ?? [] as $tag => $count)
                            <a href="{{ route('search', ['q' => '#' . $tag]) }}" class="skuy-context-topic">
                                <span class="skuy-context-topic__name">#{{ $tag }}</span>
                                <span class="skuy-context-topic__count">{{ trans_choice('threads.trending.thread_count', $count, ['count' => $count]) }}</span>
                            </a>
                        @empty
                            <p class="text-sm text-[var(--skuy-text-muted)]">{{ __('threads.trending.empty') }}</p>
                        @endforelse
                    </div>
                </div>

                @if ($feedCreators->isNotEmpty())
                    <div class="skuy-context-card skuy-feed-context-card skuy-feed-context-creators" aria-labelledby="feed-creators-title">
                        <h2 id="feed-creators-title" class="skuy-context-card__title"><span class="skuy-flow-widget-icon skuy-flow-widget-icon--people"><x-fa-icon name="user-group" weight="solid" /></span>{{ __('navigation.feed_creators') }}</h2>
                        <div class="skuy-feed-context-creators__list">
                            @foreach ($feedCreators as $creator)
                                <a href="{{ route('user.profile', $creator->username) }}" class="skuy-feed-context-creator">
                                    <span class="skuy-feed-context-creator__avatar" aria-hidden="true">
                                        @if ($creator->avatar)
                                            <img src="{{ asset('storage/' . $creator->avatar) }}" alt="">
                                        @else
                                            {{ mb_substr($creator->name, 0, 1) }}
                                        @endif
                                    </span>
                                    <span class="skuy-feed-context-creator__identity">
                                        <strong>{{ $creator->name }}</strong>
                                        <span>{{ '@' . $creator->username }}</span>
                                    </span>
                                    <x-fa-icon name="arrow-up-right-from-square" class="skuy-feed-context-creator__icon" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="skuy-context-card skuy-feed-context-card skuy-flow-points-widget">
                    <h2 class="skuy-context-card__title"><span class="skuy-flow-widget-icon skuy-flow-widget-icon--points"><x-fa-icon name="coins" weight="solid" /></span>{{ __('points.wallet.heading') }}</h2>
                    <p>{{ __('points.wallet.description') }}</p>
                    <a href="{{ route('points.history') }}">{{ __('points.wallet.history_link') }}</a>
                </div>

                <div class="skuy-context-card skuy-context-card--accent skuy-feed-context-card skuy-feed-context-card--community">
                    <h2 class="skuy-context-card__title">SkuySocial</h2>
                    <p>{{ __('threads.community.message') }}</p>
                    <a href="{{ route('user.profile', auth()->user()->username) }}">
                        {{ __('threads.community.view_profile') }}
                    </a>
                </div>
            </aside>
        </div>

    </div>
</x-app-layout>
