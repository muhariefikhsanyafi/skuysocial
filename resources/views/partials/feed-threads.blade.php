                        @foreach ($posts as $post)
						@php
    // Cek apakah ini repost. Jika ya, alihkan isi kontennya ke postingan asli.
    $isRepost = $post->repost_id !== null;
    $displayPost = $isRepost ? $post->originalPost : $post;
@endphp
                            <!-- Tambahan logika cerdas: Jika yang diklik adalah tombol/link/form/gambar, JANGAN pindah halaman. Jika area kosong, PINDAH halaman. -->
<div data-feed-thread-key="{{ $post->uid }}" x-data="{ showComments: false }"
     @click="if(!$event.target.closest('button, a, form, img')) window.location='{{ route('posts.show', $displayPost->uid) }}'"
     class="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#141414] bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 transition-colors duration-300">

								<!-- LABEL REPOST -->
        @if($isRepost)
            <div class="flex items-center gap-1.5 mb-3 text-gray-500 font-black text-[11px] uppercase tracking-wider pb-2 border-b-2 border-dashed border-gray-200 dark:border-[#333333]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span> <!-- $post tetap digunakan di sini untuk menampilkan nama orang yang merepost -->
            </div>
        @endif

								<div class="flex gap-3 sm:gap-4">

                                    <div class="flex flex-col items-center flex-shrink-0">
                                        <a href="{{ route('user.profile', $displayPost->user->username) }}">
                                            @if ($displayPost->user->avatar)
                                                <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none transition-all">
                                            @else
                                                <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-base sm:text-lg shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none transition-all">
                                                    {{ substr($displayPost->user->name, 0, 1) }}
                                                </div>
                                            @endif
                                        </a>
                                        <div class="w-1 sm:w-1.5 bg-black dark:bg-[#333333] flex-1 mt-2 sm:mt-3 min-h-[30px]"></div>
                                    </div>

                                    <div class="flex-1 min-w-0 pb-1 sm:pb-2">

                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="font-black text-sm sm:text-base text-black dark:text-white hover:underline hover:decoration-2 hover:underline-offset-2">
                                                    {{ $displayPost->user->username }}
                                                </a>

                                                @if($displayPost->user->is_verified)
                                                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] ml-0.5 cursor-help transition-transform hover:scale-110" title="{{ __('threads.verified') }}">
                                                        <svg class="w-2 h-2 sm:w-2.5 sm:h-2.5 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                    </span>
                                                @endif

                                                @if($displayPost->topic)
                                                    <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5 text-xs sm:text-base">›</span>
                                                    <a href="/topik/{{ $displayPost->topic }}" class="font-bold text-xs sm:text-base text-blue-600 dark:text-blue-400 hover:underline uppercase transition-all">
                                                        {{ $displayPost->topic }}
                                                    </a>
                                                @endif

                                                <span class="text-[11px] sm:text-xs font-bold text-gray-500 dark:text-gray-500 ml-1">{{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}</span>
                                            </div>

                                            @if ($displayPost->user_id === auth()->id())
                                                <div x-data="{ openOptions: false }" class="relative">
                                                    <button @click="openOptions = !openOptions" type="button" class="p-1 flex items-center justify-center border-2 border-transparent hover:border-black dark:hover:border-[#333333] hover:bg-yellow-300 dark:hover:bg-[#242424] transition-all text-black dark:text-white rounded-sm">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle>
                                                        </svg>
                                                    </button>
                                                    <div x-show="openOptions" @click.away="openOptions = false" style="display: none;" class="absolute right-0 mt-1 sm:mt-2 w-40 sm:w-44 bg-white dark:bg-[#101010] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] z-50 flex flex-col">
                                                        <a href="{{ route('posts.edit', $displayPost->uid) }}" class="px-3 py-2.5 text-[11px] sm:text-xs font-black uppercase text-black dark:text-white border-b-2 border-black dark:border-[#333333] hover:bg-cyan-400 transition-colors flex items-center gap-2">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                            {{ __('threads.actions.edit') }}
                                                        </a>
                                                        <form action="{{ route('posts.destroy', $displayPost->uid) }}" method="POST" data-confirm="{{ __('threads.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm);" class="w-full m-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full text-left px-3 py-2.5 text-[11px] sm:text-xs font-black uppercase text-red-600 hover:bg-pink-400 hover:text-black transition-colors flex items-center gap-2">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                {{ __('threads.actions.delete') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Teks Postingan diperbesar -->
                                        <p class="text-black dark:text-white font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-3 mt-1.5">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-blue-600 dark:text-blue-400 font-bold hover:underline decoration-2">#$1</a>', e($displayPost->content)) !!}</p>

                                        @if ($displayPost->images && count($displayPost->images) > 0)
                                                    @php
                                                        $postImageUrls = collect($displayPost->images)
                                                            ->map(fn ($image) => asset('storage/' . $image))
                                                            ->values();
                                                    @endphp
                                                    <div x-data="{
                                                    activeSlide: 0,
                                                    updateDots(e) {
                                                        let maxIndex = {{ count($displayPost->images) - 1 }};
                                                        if (maxIndex === 0) return;
                                                        let maxScroll = e.target.scrollWidth - e.target.clientWidth;
                                                        if (maxScroll === 0) return;
                                                        this.activeSlide = Math.round((e.target.scrollLeft / maxScroll) * maxIndex);
                                                    }
                                                 }" class="mb-3">

                                                <!-- Jarak gap diperkecil (gap-1.5) agar sambungan gambar terasa lebih rapat dan menyatu -->
                                                <div @scroll.passive="updateDots" class="flex gap-1.5 sm:gap-2 overflow-x-auto snap-x snap-mandatory pb-3 pt-1 w-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                                    @foreach ($displayPost->images as $imgUrl)
                                                        <!-- Ukuran dipaksa seragam dengan 'aspect-[4/5]' & bisa berdampingan pas di desktop dengan 'sm:w-[48%]' -->
                                                        <div @click="openLightbox(@js($postImageUrls), {{ $loop->index }})" class="snap-center shrink-0 w-[85%] sm:w-[48%] aspect-[4/5] cursor-pointer border-2 sm:border-4 border-black dark:border-[#333333] shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] sm:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] rounded-xl sm:rounded-2xl overflow-hidden group transition-all hover:translate-y-[-2px]">
                                                            <!-- h-full & object-cover akan memastikan gambar mengisi seluruh kotak seragam tanpa merusak rasio aslinya -->
                                                            <img src="{{ asset('storage/' . $imgUrl) }}" alt="{{ __('threads.media.photo') }}" class="w-full h-full object-cover group-hover:opacity-85 transition-opacity">
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <!-- INDIKATOR TITIK-TITIK (DOTS) -->
                                                @if (count($displayPost->images) > 1)
                                                    <div class="flex justify-center items-center gap-1.5 mt-1 sm:mt-2 mb-2">
                                                        @foreach ($displayPost->images as $index => $img)
                                                            <div class="h-1.5 sm:h-2 rounded-full border border-black dark:border-[#333333] transition-all duration-300"
                                                                 :class="activeSlide === {{ $index }} ? 'w-4 sm:w-5 bg-black dark:bg-white' : 'w-1.5 sm:w-2 bg-gray-300 dark:bg-[#242424]'">
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                            </div>
                                        @endif

                                        <!-- ICON INTERAKSI diperbesar -->
                                        <div class="flex items-center gap-2 sm:gap-3 mt-3 sm:mt-4 mb-2 flex-wrap">
                                            <form action="{{ route('posts.like', $displayPost->uid) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $displayPost->liked_by_me ? 'bg-pink-400 text-black' : 'bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-pink-300' }}">
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 {{ $displayPost->liked_by_me ? 'fill-black' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                                    <span class="text-xs sm:text-sm font-black">{{ $displayPost->likes_count > 0 ? $displayPost->likes_count : '' }}</span>
                                                </button>
                                            </form>
                                            <button @click="showComments = !showComments" type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] bg-white dark:bg-[#101010] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all hover:bg-cyan-300 text-black dark:text-white group">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                <span class="text-xs sm:text-sm font-black">{{ $displayPost->comments_count > 0 ? $displayPost->comments_count : '' }}</span>
                                            </button>
                                            <!-- Tombol Repost (Berfungsi) -->
                                            <form action="{{ route('posts.repost', $displayPost->uid) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $displayPost->reposted_by_me ? 'bg-emerald-400 text-black' : 'bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-emerald-300' }} group" title="{{ __('threads.actions.repost') }}">
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform {{ $displayPost->reposted_by_me ? 'fill-black' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                    @if($displayPost->reposts_count > 0)
                                                        <span class="text-xs sm:text-sm font-black">{{ $displayPost->reposts_count }}</span>
                                                    @endif
                                                </button>
                                            </form>

                                            @if ($displayPost->bookmarked_by_me)
                                                <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-yellow-300 text-black group">
                                                        <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('bookmarks.store', $displayPost->uid) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" aria-label="{{ __('threads.actions.save') }}" title="{{ __('threads.actions.save') }}" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-yellow-300 group">
                                                        <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- Tombol Share (URL Spesifik) -->
                                            <button @click="copyAndToast('{{ route('posts.show', $displayPost->uid) }}', '{{ __('threads.thread_link_copied') }}')" type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-yellow-300 group" title="{{ __('threads.actions.share') }}">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                            </button>
                                        </div>

										<!-- PREVIEW KOMENTAR TERBARU (Gaya Threads) -->
                                        @php
                                            // Mengambil 1 komentar induk paling terbaru
                                            $topComment = $displayPost->feedComments->first();
                                        @endphp

                                        @if ($topComment)
                                            <!-- x-show="!showComments" memastikan preview ini sembunyi saat area komentar penuh dibuka -->
                                            <div x-show="!showComments" class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t-2 border-black dark:border-[#333333] border-dashed">
                                                <div class="flex gap-2 sm:gap-3">
                                                    <div class="flex flex-col items-center flex-shrink-0">
                                                        @if ($topComment->user->avatar)
                                                            <img src="{{ asset('storage/' . $topComment->user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-6 w-6 sm:h-8 sm:w-8 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                        @else
                                                            <div class="h-6 w-6 sm:h-8 sm:w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-[10px] sm:text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                {{ substr($topComment->user->name, 0, 1) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="flex-1 min-w-0 pb-1">
                                                        <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                            <span class="font-black text-[11px] sm:text-[13px] text-black dark:text-white hover:underline cursor-pointer">{{ $topComment->user->username }}</span>
                                                            @if($topComment->user->is_verified)
                                                                <span class="inline-flex items-center justify-center w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)]" title="{{ __('threads.verified') }}">
                                                                    <svg class="w-2 h-2 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                                </span>
                                                            @endif
                                                            <span class="text-[9px] sm:text-[10px] font-bold text-gray-500 ml-1">{{ $topComment->created_at->diffForHumans(null, true, true) }}</span>
                                                        </div>
                                                        <p class="text-black dark:text-white font-medium text-[12px] sm:text-sm leading-relaxed">{{ $topComment->content }}</p>

                                                        <!-- Tampilkan tombol "Lihat balasan lainnya" jika komentar lebih dari 1 -->
                                                        @if ($displayPost->root_comments_count > 1)
                                                            <button @click="showComments = true" type="button" class="mt-1 sm:mt-1.5 text-[10px] sm:text-[11px] font-black text-gray-500 hover:text-black dark:hover:text-white transition-colors flex items-center gap-1">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                                                                {{ trans_choice('threads.comments.more', $displayPost->root_comments_count - 1, ['count' => $displayPost->root_comments_count - 1]) }}
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Area Komentar -->
                                        <div x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="mt-4 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed">
                                            <div class="space-y-4 mb-3">
                                                @foreach ($displayPost->feedComments as $comment)
                                                    <div x-data="{ openReply: false, isSubmitting: false }" class="relative">
                                                        <div class="flex gap-3">
                                                            <div class="flex flex-col items-center flex-shrink-0">
                                                                @if ($comment->user->avatar)
                                                                    <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-8 w-8 sm:h-8 sm:w-8 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                @else
                                                                    <div class="h-8 w-8 sm:h-8 sm:w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 dark:bg-cyan-500 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                        {{ substr($comment->user->name, 0, 1) }}
                                                                    </div>
                                                                @endif
                                                                <div class="w-0.5 sm:w-1 bg-black dark:bg-[#333333] flex-1 mt-1.5 sm:mt-2 mb-1 min-h-[16px]" x-show="openReply || {{ $comment->feedReplies->count() > 0 ? 'true' : 'false' }}"></div>
                                                            </div>
                                                            <div class="flex-1 min-w-0 pb-1">
                                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                                    <span class="font-black text-[12px] sm:text-[13px] text-black dark:text-white hover:underline cursor-pointer">{{ $comment->user->username }}</span>
                                                                    @if($comment->user->is_verified)
                                                                        <span class="inline-flex items-center justify-center w-3 h-3 sm:w-3 sm:h-3 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)]" title="{{ __('threads.verified') }}">
                                                                            <svg class="w-2 h-2 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                                        </span>
                                                                    @endif
                                                                    <span class="text-[10px] font-bold text-gray-500 ml-1">{{ $comment->created_at->diffForHumans(null, true, true) }}</span>
                                                                </div>
                                                                <p class="text-black dark:text-white font-medium text-[13px] sm:text-sm leading-relaxed">{{ $comment->content }}</p>
                                                                <button @click="openReply = !openReply" type="button" class="mt-1 text-[11px] font-black uppercase text-gray-500 hover:text-black transition-colors">{{ __('threads.actions.reply') }}</button>

                                                                <div x-show="openReply" style="display: none;" class="mt-2 w-full">
                                                                    <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="w-full flex gap-2 items-center" @submit="isSubmitting = true">
                                                                        @csrf
                                                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                                        <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-b-2 border-black dark:border-[#333333] focus:ring-0 focus:outline-none text-[11px] sm:text-xs font-bold p-0 pb-1 text-black dark:text-white placeholder-gray-400" placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}" required>
                                                                        <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] text-black font-black uppercase border-2 border-black bg-yellow-300 px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
                                                                            <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                                                            <span x-show="isSubmitting">...</span>
                                                                        </button>
                                                                    </form>
                                                                </div>

                                                                @if ($comment->feedReplies->count() > 0)
                                                                    <div class="mt-3 space-y-3">
                                                                        @foreach ($comment->feedReplies as $reply)
                                                                            <div class="flex gap-2">
                                                                                <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                                                                    @if ($reply->user->avatar)
                                                                                        <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-6 w-6 sm:h-6 sm:w-6 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                                    @else
                                                                                        <div class="h-6 w-6 sm:h-6 sm:w-6 border-2 border-black dark:border-[#333333] bg-pink-400 rounded-full flex items-center justify-center text-black font-black uppercase text-[10px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                                            {{ substr($reply->user->name, 0, 1) }}
                                                                                        </div>
                                                                                    @endif
                                                                                    @if (!$loop->last)
                                                                                        <div class="w-0.5 bg-black flex-1 mt-1 min-h-[10px]"></div>
                                                                                    @endif
                                                                                </div>
                                                                                <div class="flex-1 min-w-0 pb-1">
                                                                                    <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                                                        <span class="font-black text-[11px] sm:text-xs text-black dark:text-white hover:underline cursor-pointer">{{ $reply->user->username }}</span>
                                                                                        <span class="text-[9px] font-bold text-gray-500 ml-1">{{ $reply->created_at->diffForHumans(null, true, true) }}</span>
                                                                                    </div>
                                                                                    <p class="text-black dark:text-white font-medium text-[12px] sm:text-sm leading-relaxed">{{ $reply->content }}</p>
                                                                                </div>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div x-data="{ isSubmitting: false }" class="mt-3 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed flex gap-3 items-center w-full min-w-0">
                                                <div class="flex-shrink-0">
                                                    @if (Auth::user()->avatar)
                                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}" class="h-8 w-8 rounded-full border-2 border-black dark:border-[#333333] object-cover shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                    @else
                                                        <div class="h-8 w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                            {{ substr(Auth::user()->name, 0, 1) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="flex-1 flex gap-2 items-center min-w-0" @submit="isSubmitting = true">
                                                    @csrf
                                                    <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-none focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-xs sm:text-sm placeholder-gray-400" placeholder="{{ __('threads.comments.add') }}" required>
                                                    <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] sm:text-[11px] text-black font-black uppercase border-2 border-black bg-cyan-400 px-3 py-1 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
                                                        <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                                        <span x-show="isSubmitting">...</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
