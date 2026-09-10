<x-app-layout>
    <!-- 1. Background utama (pt-8 dipangkas jadi pt-3 untuk HP) -->
    <div class="pt-3 sm:pt-12 pb-28 md:pb-12 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">

        <!-- 2. Wadah 3 Kolom (Padding kiri-kanan diperlebar jadi px-2 khusus di HP) -->
        <div class="w-full max-w-[1600px] mx-auto px-2 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between gap-0 md:gap-6 lg:gap-8 items-start min-h-screen">
            
            <!-- 3. SIDEBAR KIRI (mb-6 dihapus dari pembungkus utama) -->
            <div x-data="{ sidebarOpen: false }" @toggle-sidebar.window="sidebarOpen = $event.detail" class="w-full md:w-52 lg:w-[250px] xl:w-[280px] flex-shrink-0 md:sticky md:top-28 z-20 md:mb-0">
                
                <!-- Menu Items (mb-5 dipindahkan ke dalam agar hanya memakan ruang SAAT menu dibuka) -->
                <div id="mobile-navigation" x-show="sidebarOpen" x-transition class="md:!flex flex-col gap-3 mb-5 md:mb-0" style="display: none;">
                    
                    <a href="{{ route('feed') }}" aria-current="{{ request()->routeIs('feed') ? 'page' : 'false' }}" class="flex items-center gap-3 bg-yellow-300 border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black font-black text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        {{ __('navigation.home') }}
                    </a>

                    <a href="{{ route('search') }}" aria-current="{{ request()->routeIs('search') ? 'page' : 'false' }}" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-pink-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        {{ __('navigation.search') }}
                    </a>

                    <a href="{{ route('activity.index') }}" aria-current="{{ request()->routeIs('activity.index') ? 'page' : 'false' }}" class="flex items-center justify-between w-full bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 text-black dark:text-white shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-emerald-400 transition-colors group">
    
    <!-- Pembungkus Ikon dan Teks agar tetap di kiri -->
    <div class="flex items-center gap-3">
        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        <span class="font-bold">{{ __('navigation.activity') }}</span>
    </div>

    <!-- LOGIKA DOT MERAH (BADGE) DI KANAN -->
    @if($unreadNotificationCount > 0)
        <span class="bg-red-500 text-white text-xs font-black px-2 py-0.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
            {{ $unreadNotificationCount }}
        </span>
    @endif
</a>

                    <a href="{{ route('bookmarks.index') }}" aria-current="{{ request()->routeIs('bookmarks.index') ? 'page' : 'false' }}" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-pink-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                        {{ __('navigation.saved') }}
                    </a>

                    <a href="{{ route('user.profile', auth()->user()->username) }}" aria-current="{{ request()->routeIs('user.profile') && request()->route('username') === auth()->user()->username ? 'page' : 'false' }}" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-emerald-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        {{ __('navigation.profile') }}
                    </a>
                    
                </div>
            </div>


            <!-- ========================================== -->
            <!-- 2. AREA KONTEN TENGAH (Feed) -->
            <!-- ========================================== -->
            <div class="flex-1 w-full max-w-2xl lg:max-w-3xl xl:max-w-4xl mx-auto" x-data="{
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
            }" data-feed-next-url="{{ $posts->nextPageUrl() }}" x-init="nextFeedUrl = $el.dataset.feedNextUrl || ''">
                
                <!-- WADAH KUNING RAKSASA (Transparan khusus di HP agar postingan melebar) -->
                <div class="bg-transparent sm:bg-yellow-300 dark:bg-transparent sm:dark:bg-[#181818] border-0 sm:border-4 border-black dark:border-[#333333] shadow-none sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-0 sm:p-6 lg:p-8 transition-colors duration-300">
                    
                    <!-- Form Input Utas -->
                    <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-4 sm:p-5 mb-6 sm:mb-8 transition-colors duration-300">
                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" x-data="{
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
                            <div class="flex gap-3 sm:gap-4">
                                <div class="flex-shrink-0">
                                    @if (Auth::user()->avatar)
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                    @else
                                        <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-base sm:text-xl shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                            {{ substr(Auth::user()->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div x-data="{
                                            topicQuery: '',
                                            showSuggestions: false,
                                            availableTopics: {{ $availableTopics->toJson() }},
                                            
                                            get filteredTopics() {
                                                if (this.topicQuery === '') return [];
                                                return this.availableTopics.filter(t => t.toLowerCase().includes(this.topicQuery.toLowerCase()));
                                            },
                                            selectTopic(t) {
                                                this.topicQuery = t;
                                                this.showSuggestions = false;
                                            }
                                        }" 
                                        class="font-black text-sm text-black dark:text-white mb-1 flex items-center gap-1.5 flex-wrap">
                                        
                                        {{ Auth::user()->username }}
                                        @if(Auth::user()->is_verified)
                                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] cursor-help" title="{{ __('threads.verified') }}">
                                                <svg class="w-2 h-2 sm:w-2.5 sm:h-2.5 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                            </span>
                                        @endif
                                        
                                        <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5">›</span>
                                        
                                        <div class="relative">
                                            <input type="text" name="topic" x-model="topicQuery" @focus="showSuggestions = true" @click.away="showSuggestions = false" class="bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-sm placeholder-gray-500 w-28 sm:w-48" placeholder="{{ __('threads.composer.topic_placeholder') }}" maxlength="30" autocomplete="off">
                                            <div x-show="showSuggestions && filteredTopics.length > 0" style="display: none;" class="absolute top-full left-0 mt-2 w-48 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] z-50">
                                                <template x-for="t in filteredTopics" :key="t">
                                                    <div @click="selectTopic(t)" class="px-3 py-2 border-b-2 last:border-b-0 border-black dark:border-[#333333] hover:bg-yellow-300 dark:hover:bg-[#242424] cursor-pointer font-bold text-sm text-black dark:text-white transition-colors" x-text="t"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea name="content" rows="2" x-model="content" class="w-full bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-medium text-base sm:text-lg placeholder-gray-500 resize-none" placeholder="{{ __('threads.composer.content_placeholder') }}" required></textarea>
                                    <!-- KEMBALIKAN PESAN ERROR DI SINI -->
                                    @if ($errors->any())
                                        <div class="mt-2 p-2 bg-red-100 border-2 border-red-500 shadow-[2px_2px_0px_0px_rgba(239,68,68,1)]">
                                            <ul class="list-disc list-inside text-red-600 text-xs font-black uppercase">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
									<div class="flex flex-col mt-3 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed gap-3">
										<div class="w-full overflow-hidden">
                                            <input type="file" name="images[]" multiple accept="image/*" x-ref="imagesInput" @change="handleImageSelection($event)" class="text-xs w-full file:mr-2 file:py-1 file:px-2 file:border-2 file:border-black dark:file:border-[#333333] file:bg-yellow-300 file:text-black file:font-black file:uppercase file:cursor-pointer file:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:file:shadow-none hover:file:translate-y-[2px] transition-all">
                                            <div x-show="selectedImages.length > 0" style="display: none;" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2" aria-label="{{ __('threads.media.preview') }}">
                                                <template x-for="(image, index) in selectedImages" :key="image.identity">
                                                    <div class="relative aspect-square overflow-hidden border-2 border-black dark:border-[#333333] bg-gray-100 dark:bg-[#181818] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                                        <button type="button" @click="openLightbox(selectedImages.map(item => item.previewUrl), index)" :aria-label="'{{ __('threads.media.view_preview') }} ' + (index + 1)" class="block w-full h-full p-0 border-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-400">
                                                            <img :src="image.previewUrl" :alt="'{{ __('threads.media.preview') }} ' + (index + 1)" class="w-full h-full object-cover">
                                                        </button>
                                                        <button type="button" @click="closeImageIfActive(image.previewUrl); removeImage(index)" :disabled="isSubmitting" :aria-label="'{{ __('threads.media.remove_image') }} ' + (index + 1)" :title="'{{ __('threads.media.remove_image') }} ' + (index + 1)" class="absolute top-1 right-1 w-7 h-7 flex items-center justify-center bg-red-500 text-white border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all font-black text-lg leading-none focus:outline-none focus:ring-2 focus:ring-cyan-400">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <div x-show="mediaMessage" x-text="mediaMessage" style="display: none;" role="status" aria-live="polite" class="mt-2 text-red-600 dark:text-red-400 text-xs font-black uppercase"></div>
                                        </div>
										<div class="flex justify-end w-full">
											<button type="submit" :disabled="isSubmitting || content.trim() === ''" :class="(isSubmitting || content.trim() === '') ? 'bg-gray-200 dark:bg-[#242424] text-gray-500 shadow-none cursor-not-allowed' : 'bg-cyan-400 text-black hover:shadow-none hover:translate-y-[2px] hover:translate-x-[2px] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]'" class="border-2 border-black dark:border-[#333333] transition-all font-black uppercase px-5 sm:px-6 py-1.5 flex-shrink-0 text-sm">
												<span x-show="!isSubmitting">{{ __('threads.actions.post') }}</span>
												<span x-show="isSubmitting">...</span>
											</button>
										</div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Area Daftar Status / Timeline -->
                    <div id="feed-thread-list" x-ref="feedThreads" :aria-busy="isLoadingMore.toString()" class="space-y-5 sm:space-y-6">
                        @include('partials.feed-threads', ['posts' => $posts])

                    </div>

                    <div x-show="openImage" @click.self="closeImage()" @keydown.window="if (openImage && $event.key === 'ArrowLeft') previousImage(); if (openImage && $event.key === 'ArrowRight') nextImage()" @keydown.escape.window="closeImage()" style="display: none;" role="dialog" aria-modal="true" aria-label="{{ __('threads.media.photo_preview') }}" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-8 bg-black/90 backdrop-blur-sm" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                        <div x-show="lightboxImages.length > 1" x-text="(lightboxIndex + 1) + ' / ' + lightboxImages.length" class="absolute top-4 left-4 sm:top-8 sm:left-8 z-[110] bg-black/70 text-white border-2 border-white px-2 py-1 font-black text-xs sm:text-sm"></div>
                        <button type="button" x-show="lightboxImages.length > 1" @click.stop="previousImage()" aria-label="{{ __('threads.media.previous_image') }}" class="absolute left-2 sm:left-8 top-1/2 -translate-y-1/2 z-[110] bg-black/70 text-white border-2 border-white shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-300 hover:text-black p-2 sm:p-3 transition-all">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M15 18l-6-6 6-6"></path></svg>
                        </button>
                        <button type="button" x-show="lightboxImages.length > 1" @click.stop="nextImage()" aria-label="{{ __('threads.media.next_image') }}" class="absolute right-2 sm:right-8 top-1/2 -translate-y-1/2 z-[110] bg-black/70 text-white border-2 border-white shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-300 hover:text-black p-2 sm:p-3 transition-all">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M9 18l6-6-6-6"></path></svg>
                        </button>
                        <button type="button" @click="closeImage()" aria-label="{{ __('threads.media.close_preview') }}" class="absolute top-4 right-4 sm:top-8 sm:right-8 text-white hover:text-yellow-400 transition-colors z-[110] bg-black/50 rounded-full p-1 border-2 border-transparent hover:border-white">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        <img :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-w-full max-h-[90vh] object-contain border-2 sm:border-4 border-white shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)]">
                    </div>

                    @if ($posts->hasMorePages())
                        <div x-show="nextFeedUrl" class="mt-6 flex flex-col items-center px-2 sm:px-0">
                            <a href="{{ $posts->nextPageUrl() }}" :href="nextFeedUrl" rel="next" aria-controls="feed-thread-list" :aria-busy="isLoadingMore.toString()" :aria-disabled="isLoadingMore.toString()" @click="loadMoreThreads($event)" :class="isLoadingMore ? 'cursor-wait opacity-70' : ''" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center border-2 sm:border-4 border-black bg-yellow-300 sm:bg-white px-4 sm:px-6 py-2.5 text-center text-sm sm:text-base font-black uppercase leading-tight text-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all hover:bg-yellow-200 sm:hover:bg-gray-100 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-black focus-visible:ring-offset-2 dark:focus-visible:ring-yellow-300 dark:focus-visible:ring-offset-[#101010]">
                                <span x-show="!isLoadingMore">{{ __('threads.actions.view_more_threads') }}</span>
                                <span x-show="isLoadingMore" style="display: none;">{{ __('threads.actions.loading_threads') }}</span>
                            </a>
                            <span class="sr-only" role="status" aria-live="polite">
                                <span x-show="isLoadingMore" style="display: none;">{{ __('threads.actions.loading_threads') }}</span>
                            </span>
                            <p x-show="feedLoadFailed" style="display: none;" role="alert" class="mt-3 max-w-lg text-center text-sm font-bold text-black dark:text-white">
                                {{ __('threads.actions.load_more_failed') }}
                            </p>
                        </div>
                    @endif
                    
                </div> <!-- PENUTUP WADAH KUNING RAKSASA -->
            </div>


            <!-- ========================================== -->
            <!-- 3. SIDEBAR KANAN (Widget Tambahan) -->
            <!-- ========================================== -->
            <!-- Hanya muncul di layar Desktop menengah ke atas (xl:flex) -->
            <div class="hidden xl:flex flex-col w-[280px] flex-shrink-0 md:sticky md:top-28 z-20 gap-6">
                
                <!-- Widget 1: Topik Hangat -->
                <div class="p-4 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
    <h2 class="text-lg font-black uppercase mb-4 flex items-center gap-2 border-b-4 border-black pb-2">
        🔥 {{ __('threads.trending.title') }}
    </h2>

    <div class="space-y-3">
        @forelse($trendingHashtags ?? [] as $tag => $count)
            <a href="{{ route('search', ['q' => '#' . $tag]) }}" class="block p-2 border-2 border-black bg-gray-50 hover:bg-yellow-300 transition-colors shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                <p class="font-black text-black text-sm">#{{ $tag }}</p>
                <p class="text-xs font-bold text-gray-500">{{ trans_choice('threads.trending.thread_count', $count, ['count' => $count]) }}</p>
            </a>
        @empty
            <p class="text-xs font-bold text-gray-500 italic">{{ __('threads.trending.empty') }}</p>
        @endforelse
    </div>
</div>

                <!-- Widget 2: Kartu Dukungan -->
                <div class="bg-pink-400 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] p-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 text-6xl opacity-20 transform rotate-12">🚀</div>
                    <h3 class="font-black text-black uppercase text-lg mb-2 relative z-10">SkuySocial V.2</h3>
                    <p class="font-bold text-sm text-black mb-5 leading-tight relative z-10">{{ __('threads.community.message') }}</p>
                    <a href="{{ route('user.profile', auth()->user()->username) }}" class="block text-center w-full bg-white border-2 border-black font-black uppercase text-sm py-2 hover:bg-yellow-300 hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] transition-all text-black relative z-10">
                        {{ __('threads.community.view_profile') }}
                    </a>
                </div>

            </div>

        </div>
    </div>
	
	<!-- BOTTOM NAVIGATION BAR (HANYA MUNCUL DI HP) -->
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white dark:bg-[#101010] border-t-4 border-black dark:border-[#333333] flex justify-around items-center p-3 z-50 pb-safe">
        
        <!-- Beranda -->
        <a href="{{ route('feed') }}" aria-label="{{ __('navigation.home') }}" aria-current="{{ request()->routeIs('feed') ? 'page' : 'false' }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3l8 6v12h-5v-7h-6v7H4V9l8-6z"></path></svg>
        </a>

        <!-- Pencarian -->
        <a href="{{ route('search') }}" aria-label="{{ __('navigation.search') }}" aria-current="{{ request()->routeIs('search') ? 'page' : 'false' }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </a>

        <!-- Buat Thread / Postingan (Tombol Tengah Mencolok) -->
        <button @click="window.scrollTo({top: 0, behavior: 'smooth'})" aria-label="{{ __('navigation.create_thread') }}" type="button" class="bg-yellow-300 border-2 border-black p-2.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
        </button>

        <!-- Aktivitas / Notifikasi -->
        <a href="{{ route('activity.index') }}" aria-label="{{ __('navigation.activity') }}" aria-current="{{ request()->routeIs('activity.index') ? 'page' : 'false' }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </a>

        <!-- Profil (BUGS FIXED DI SINI) -->
        <a href="{{ route('user.profile', auth()->user()->username) }}" aria-label="{{ __('navigation.profile') }}" aria-current="{{ request()->routeIs('user.profile') && request()->route('username') === auth()->user()->username ? 'page' : 'false' }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </a>

    </div>
</x-app-layout>
