<x-app-layout>
    <div class="skuy-feed-page">
        <div class="skuy-feed-shell">
            <section
                class="skuy-feed-content"
                aria-labelledby="feed-title"
                x-data="{
                    openImage: false,
                    activeImage: '',
                    lightboxImages: [],
                    lightboxIndex: 0,
                    nextFeedUrl: '',
                    isLoadingMore: false,
                    feedLoadFailed: false,
                    openLightbox(images, index = 0) {
                        const normalizedImages = images.filter(image => typeof image === 'string' && image.length > 0);
                        if (normalizedImages.length === 0) return;

                        this.lightboxImages = normalizedImages;
                        this.lightboxIndex = Math.min(Math.max(index, 0), normalizedImages.length - 1);
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                        this.openImage = true;
                    },
                    previousImage() {
                        if (this.lightboxImages.length <= 1) return;

                        this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxImages.length) % this.lightboxImages.length;
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                    },
                    nextImage() {
                        if (this.lightboxImages.length <= 1) return;

                        this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxImages.length;
                        this.activeImage = this.lightboxImages[this.lightboxIndex];
                    },
                    closeImage() {
                        this.openImage = false;
                        this.activeImage = '';
                        this.lightboxIndex = 0;
                        this.lightboxImages = [];
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
                    <h1 id="feed-title" class="skuy-feed-page__title">{{ __('navigation.feed') }}</h1>
                </header>

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

                            <div class="skuy-composer__identity">
                                @if (Auth::user()->avatar)
                                    <div class="skuy-avatar">
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                    </div>
                                @else
                                    <div class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                                        {{ substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="skuy-composer__identity-copy">
                                    <span class="skuy-composer__display-name">{{ Auth::user()->name }}</span>
                                    <div class="skuy-composer__identity-meta">
                                        <span class="skuy-composer__username">{{ '@' . Auth::user()->username }}</span>
                                        @if (Auth::user()->is_verified)
                                            <span class="skuy-composer__verified text-[var(--skuy-secondary)]" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div x-data="{
                                topicQuery: '',
                                showSuggestions: false,
                                availableTopics: {{ $availableTopics->toJson() }},
                                get filteredTopics() {
                                    if (this.topicQuery === '') return [];
                                    return this.availableTopics.filter(topic => topic.toLowerCase().includes(this.topicQuery.toLowerCase()));
                                },
                                selectTopic(topic) {
                                    this.topicQuery = topic;
                                    this.showSuggestions = false;
                                }
                            }" class="skuy-composer__topic">
                                <label for="composer-topic" class="skuy-composer__topic-label">{{ __('threads.composer.topic_placeholder') }}</label>
                                <div class="relative min-w-0 flex-1">
                                    <input
                                        type="text"
                                        id="composer-topic"
                                        name="topic"
                                        x-model="topicQuery"
                                        @focus="showSuggestions = true"
                                        @click.away="showSuggestions = false"
                                        class="skuy-composer__topic-input"
                                        maxlength="30"
                                        autocomplete="off"
                                    >
                                    <div x-show="showSuggestions && filteredTopics.length > 0" style="display: none;" class="skuy-composer__topic-suggestions absolute left-0 top-full z-50 mt-2 w-52 max-w-[calc(100vw-4rem)]">
                                        <template x-for="topic in filteredTopics" :key="topic">
                                            <button type="button" @click="selectTopic(topic)" class="skuy-composer__topic-option" x-text="topic"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <label for="feed-composer-body" class="sr-only">{{ __('threads.composer.content_placeholder') }}</label>
                            <textarea id="feed-composer-body" name="content" rows="2" x-model="content" class="skuy-composer__textarea" placeholder="{{ __('threads.composer.content_placeholder') }}" required></textarea>

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
                                                <button type="button" @click="openLightbox(selectedImages.map(item => item.previewUrl), index)" :aria-label="'{{ __('threads.media.view_preview') }} ' + (index + 1)" class="skuy-preview-button block h-full w-full p-0">
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

                                <button type="submit" :disabled="isSubmitting || content.trim() === ''" class="skuy-button skuy-button--primary shrink-0">
                                    <span x-show="!isSubmitting">{{ __('threads.actions.post') }}</span>
                                    <span x-show="isSubmitting">...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="feed-thread-list" x-ref="feedThreads" :aria-busy="isLoadingMore.toString()" class="skuy-feed-list">
                        @include('partials.feed-threads', ['posts' => $posts])
                    </div>
                </div>

                <div x-show="openImage" @click.self="closeImage()" @keydown.window="if (openImage && $event.key === 'ArrowLeft') previousImage(); if (openImage && $event.key === 'ArrowRight') nextImage()" @keydown.escape.window="closeImage()" style="display: none;" role="dialog" aria-modal="true" aria-label="{{ __('threads.media.photo_preview') }}" class="skuy-lightbox fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-8">
                    <div x-show="lightboxImages.length > 1" x-text="(lightboxIndex + 1) + ' / ' + lightboxImages.length" class="skuy-lightbox__counter"></div>
                    <button type="button" x-show="lightboxImages.length > 1" @click.stop="previousImage()" aria-label="{{ __('threads.media.previous_image') }}" class="skuy-lightbox__control absolute left-3 top-1/2 -translate-y-1/2 sm:left-8">
                        <x-fa-icon name="chevron-left" class="text-2xl" />
                    </button>
                    <button type="button" x-show="lightboxImages.length > 1" @click.stop="nextImage()" aria-label="{{ __('threads.media.next_image') }}" class="skuy-lightbox__control absolute right-3 top-1/2 -translate-y-1/2 sm:right-8">
                        <x-fa-icon name="chevron-right" class="text-2xl" />
                    </button>
                    <button type="button" @click="closeImage()" aria-label="{{ __('threads.media.close_preview') }}" class="skuy-lightbox__control absolute right-3 top-3 sm:right-8 sm:top-8">
                        <x-fa-icon name="xmark" weight="solid" class="text-2xl" />
                    </button>
                    <img :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="skuy-lightbox__image">
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
                    <h2 class="skuy-context-card__title">{{ __('threads.trending.title') }}</h2>
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
