<x-app-layout>
    <div class="skuy-feed-page" data-skuy-prism-feed data-skuy-arah-b-feed data-feed-mode="{{ $feedTab }}">
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
                    <h1 id="feed-title" class="skuy-feed-page__title">{{ __('navigation.home') }}</h1>
                </header>

                <nav class="skuy-feed-tabs" aria-label="{{ __('threads.feed_filters.label') }}" data-feed-tabs>
                    <a href="{{ route('feed') }}" data-feed-tab="for-you" aria-current="{{ $feedTab === 'for-you' ? 'page' : 'false' }}">{{ __('threads.feed_filters.for_you') }}</a>
                    <a href="{{ route('feed', ['feed' => 'following']) }}" data-feed-tab="following" aria-current="{{ $feedTab === 'following' ? 'page' : 'false' }}">{{ __('threads.feed_filters.following') }}</a>
                </nav>

                @include('partials.feed-stories-placeholder')

                <div class="skuy-feed-timeline">
                    <div id="composer" data-composer class="skuy-composer skuy-feed-composer">
                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="skuy-feed-composer__form" x-data="{
                            isSubmitting: false,
                            content: @js(filled(request()->query('mention')) ? '@'.ltrim((string) request()->query('mention'), '@').' ' : ''),
                            selectedImages: [],
                            mediaMessage: '',
                            pollOpen: false,
                            pollQuestion: '',
                            pollOptions: ['', ''],
                            addPollOption() {
                                if (this.pollOptions.length < 4) this.pollOptions.push('');
                            },
                            removePollOption(index) {
                                if (this.pollOptions.length > 2) this.pollOptions.splice(index, 1);
                            },
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
                                        {{ \App\Support\NameInitials::from(Auth::user()->name) }}
                                    </div>
                                @endif

                                <div class="skuy-feed-composer__field">
                                    <span class="skuy-feed-composer__identity">{{ Auth::user()->username }}</span>
                                    <label for="feed-composer-body" class="sr-only">{{ __('threads.composer.content_placeholder') }}</label>
                                    <textarea id="feed-composer-body" name="content" rows="2" x-model="content" x-init="const syncPlaceholder = () => { $el.placeholder = window.matchMedia('(max-width: 767px)').matches ? $el.dataset.mobilePlaceholder : $el.dataset.desktopPlaceholder; }; syncPlaceholder(); window.addEventListener('resize', syncPlaceholder, { passive: true });" data-mobile-placeholder="{{ __('threads.composer.mobile_content_placeholder') }}" data-desktop-placeholder="{{ __('threads.composer.content_placeholder') }}" class="skuy-composer__textarea" placeholder="{{ __('threads.composer.content_placeholder') }}" required></textarea>
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
                                    <label for="feed-composer-images" class="skuy-feed-composer__attach" aria-label="{{ __('threads.media.photo') }}">
                                        <x-fa-icon name="image" class="text-base" />
                                        <span class="sr-only">{{ __('threads.media.photo') }}</span>
                                    </label>
                                    <button type="button" aria-label="{{ __('polls.create') }}" class="skuy-feed-composer__attach skuy-feed-composer__poll-toggle" @click="pollOpen = !pollOpen" :aria-pressed="pollOpen.toString()">
                                        <x-fa-icon name="square-poll-vertical" class="text-base" />
                                        <span class="sr-only">{{ __('polls.create') }}</span>
                                    </button>

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

                                    <fieldset x-show="pollOpen" style="display: none;" class="skuy-poll-composer" :disabled="!pollOpen">
                                        <legend class="skuy-poll-composer__legend">{{ __('polls.create') }}</legend>
                                        <label for="feed-poll-question" class="sr-only">{{ __('polls.question_placeholder') }}</label>
                                        <input id="feed-poll-question" type="text" name="poll_question" x-model="pollQuestion" :required="pollOpen" maxlength="180" placeholder="{{ __('polls.question_placeholder') }}" class="skuy-poll-composer__question">
                                        <div class="skuy-poll-composer__options">
                                            <template x-for="(option, index) in pollOptions" :key="index">
                                                <div class="skuy-poll-composer__option">
                                                    <label class="sr-only" :for="`feed-poll-option-${index}`" x-text="'{{ __('polls.option_placeholder', ['number' => '__NUMBER__']) }}'.replace('__NUMBER__', index + 1)"></label>
                                                    <input :id="`feed-poll-option-${index}`" type="text" name="poll_options[]" x-model="pollOptions[index]" :required="pollOpen" maxlength="80" :placeholder="'{{ __('polls.option_placeholder', ['number' => '__NUMBER__']) }}'.replace('__NUMBER__', index + 1)" class="skuy-poll-composer__option-input">
                                                    <button type="button" @click="removePollOption(index)" :disabled="pollOptions.length <= 2" :aria-label="'{{ __('polls.remove_option', ['number' => '__NUMBER__']) }}'.replace('__NUMBER__', index + 1)" class="skuy-poll-composer__remove"><x-fa-icon name="xmark" /></button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="addPollOption()" :disabled="pollOptions.length >= 4" class="skuy-poll-composer__add">{{ __('polls.add_option') }}</button>
                                    </fieldset>

                                    <div x-show="mediaMessage" x-text="mediaMessage" style="display: none;" role="status" aria-live="polite" class="mt-2 text-sm font-semibold text-[var(--skuy-danger)]"></div>
                                </div>

                                <button type="submit" :disabled="isSubmitting || content.trim() === ''" :aria-busy="isSubmitting.toString()" aria-label="{{ __('threads.actions.post') }}" title="{{ __('threads.actions.post') }}" class="skuy-feed-composer__submit">
                                    <x-fa-icon name="arrow-up" weight="solid" class="skuy-feed-composer__submit-icon text-lg" />
                                    <span class="skuy-feed-composer__submit-label" x-show="!isSubmitting">{{ __('threads.actions.post') }}</span>
                                    <span class="sr-only" x-show="isSubmitting" style="display: none;">...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="feed-thread-list" x-ref="feedThreads" :aria-busy="isLoadingMore.toString()" class="skuy-feed-list">
                        @include('partials.feed-threads', ['posts' => $posts])
                    </div>

                    <div
                        x-show="isLoadingMore"
                        style="display: none;"
                        class="skuy-feed-loading-state"
                        role="status"
                        aria-live="polite"
                        aria-label="{{ __('threads.actions.loading_threads') }}"
                    >
                        <span class="sr-only">{{ __('threads.actions.loading_threads') }}</span>
                        @for ($skeletonIndex = 0; $skeletonIndex < 2; $skeletonIndex++)
                            <div class="skuy-feed-loading-state__row" aria-hidden="true">
                                <span class="skuy-feed-loading-state__avatar sk-skeleton"></span>
                                <span class="skuy-feed-loading-state__lines">
                                    <span class="skuy-feed-loading-state__line sk-skeleton"></span>
                                    <span class="skuy-feed-loading-state__line sk-skeleton"></span>
                                </span>
                            </div>
                        @endfor
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

            <aside class="skuy-context skuy-feed-context" aria-label="{{ __('threads.suggestions.title') . ', ' . __('threads.trending.title') }}">
                <div class="skuy-context-card skuy-feed-context-card skuy-feed-context-creators" aria-labelledby="feed-creators-title" data-feed-suggestions>
                    <h2 id="feed-creators-title" class="skuy-context-card__title">{{ __('threads.suggestions.title') }}</h2>
                    @if ($feedCreators->isNotEmpty())
                        <div class="skuy-feed-context-creators__list">
                            @foreach ($feedCreators as $creator)
                                <div class="skuy-feed-context-suggestion" data-feed-suggestion-user="{{ $creator->id }}">
                                    <a href="{{ route('user.profile', $creator->username) }}" class="skuy-feed-context-creator skuy-feed-suggestion__profile">
                                        <span class="skuy-feed-context-creator__avatar" data-avatar-tone="{{ abs(crc32($creator->username)) % 3 }}" aria-hidden="true">
                                            @if ($creator->avatar)
                                                <img src="{{ asset('storage/' . $creator->avatar) }}" alt="">
                                            @else
                                                {{ \App\Support\NameInitials::from($creator->name) }}
                                            @endif
                                        </span>
                                        <span class="skuy-feed-context-creator__identity">
                                            <strong>{{ $creator->username }}</strong>
                                            @if (filled($creator->bio))
                                                <span class="skuy-feed-context-creator__bio">{{ $creator->bio }}</span>
                                            @endif
                                        </span>
                                    </a>
                                    <form action="{{ route('user.follow', $creator) }}" method="POST" class="skuy-feed-suggestion__form" data-feed-suggestion-action>
                                        @csrf
                                        <button type="submit" class="skuy-feed-suggestion__follow" aria-label="{{ __('profile.follow') . ' @' . $creator->username }}">
                                            {{ __('profile.follow') }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="skuy-feed-suggestions-empty">{{ __('threads.suggestions.empty') }}</p>
                    @endif
                </div>

                <div class="skuy-context-card skuy-feed-context-card">
                    <h2 class="skuy-context-card__title">{{ __('threads.trending.title') }}</h2>
                    <div class="skuy-context-card__list">
                        @forelse($trendingHashtags ?? [] as $tag => $count)
                            <a href="{{ route('search', ['q' => '#' . $tag]) }}" class="skuy-context-topic">
                                <span class="skuy-context-topic__name">#{{ $tag }}</span>
                                <span class="skuy-context-topic__count">{{ __('threads.trending.caption') }}</span>
                            </a>
                        @empty
                            <p class="text-sm text-[var(--skuy-text-muted)]">{{ __('threads.trending.empty') }}</p>
                        @endforelse
                    </div>
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
