<x-app-layout>
    @php
        $isRepost = $post->id !== $displayPost->id;
    @endphp

    <div class="pt-4 sm:pt-12 pb-12 bg-[var(--skuy-background)]  min-h-screen transition-colors duration-300 relative">
        <div class="w-full max-w-2xl mx-auto px-2 sm:px-6">
            
            <!-- Header Kembali (Gaya Threads) -->
            <div class="flex items-center gap-4 mb-4 px-2">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="p-2 hover:bg-[var(--skuy-surface-subtle)]  rounded-full transition-colors text-[var(--skuy-text-primary)] ">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="font-black text-xl text-[var(--skuy-text-primary)]  uppercase">{{ __('threads.labels.thread') }}</h1>
            </div>

            <!-- KARTU POSTINGAN UTAMA -->
            @if ($isRepost)
                <div class="flex items-center gap-2 mb-3 px-2 text-[var(--skuy-text-muted)]  font-black text-[11px] uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                </div>
            @endif
            <div class="bg-[var(--skuy-surface)]  border-x-0 sm:border-4 border-y-2 sm:border-[var(--skuy-border-strong)]  sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 mb-4">
                <!-- Header User -->
                <div class="flex gap-3 sm:gap-4 mb-3">
                    <div class="flex-shrink-0">
                        @if ($displayPost->user->avatar)
                            <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-[var(--skuy-border-strong)] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                        @else
                            <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                {{ substr($displayPost->user->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-black text-base text-[var(--skuy-text-primary)] ">{{ $displayPost->user->username }}</span>
                            @if($displayPost->user->is_verified)
                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--skuy-secondary)] border-2 border-[var(--skuy-border-strong)]" title="{{ __('threads.verified') }}">
                                    <svg class="w-2.5 h-2.5 text-[var(--skuy-on-secondary)] stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </span>
                            @endif
                            <span class="text-xs font-bold text-[var(--skuy-text-muted)] ml-1">{{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Isi Teks -->
                <p class="text-[var(--skuy-text-primary)]  font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-4">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-[var(--skuy-secondary)] font-bold hover:underline decoration-2">#$1</a>', e($displayPost->content)) !!}</p>
                
                <!-- Carousel Gambar (Sama seperti Feed) -->
                @php
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
                @if (count($mediaPaths) === 1)
                    <div x-data="{ openImage: false, activeImage: '' }" class="mb-4">
                        <div @click="openImage = true; activeImage = '{{ asset('storage/' . $mediaPaths[0]) }}'" class="border-2 sm:border-4 border-[var(--skuy-border-strong)]  shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] sm:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] rounded-xl sm:rounded-2xl overflow-hidden group cursor-pointer">
                            <img src="{{ asset('storage/' . $mediaPaths[0]) }}" alt="{{ __('threads.media.photo') }}" class="w-full h-auto object-cover group-hover:opacity-85 transition-opacity">
                        </div>

                        <!-- Modal Gambar Layar Penuh -->
                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 bg-black/90 backdrop-blur-sm">
                            <button @click="openImage = false" aria-label="{{ __('threads.media.close_preview') }}" class="absolute top-4 right-4 text-white z-[110] bg-black/50 rounded-full p-1 border-2 border-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-w-full max-h-[90vh] object-contain border-2 border-white shadow-[4px_4px_0px_0px_rgba(255,255,255,1)]">
                        </div>
                    </div>
                @elseif (count($mediaPaths) > 1)
                    <div x-data="{
                            openImage: false,
                            activeImage: '',
                            activeSlide: 0,
                            updateDots(e) {
                                let maxIndex = {{ count($mediaPaths) - 1 }};
                                if (maxIndex === 0) return;
                                let maxScroll = e.target.scrollWidth - e.target.clientWidth;
                                if (maxScroll === 0) return;
                                this.activeSlide = Math.round((e.target.scrollLeft / maxScroll) * maxIndex);
                            }
                        }" data-media-slider="true" class="mb-4">
                        <div @scroll.passive="updateDots" data-media-slider-track="true" class="flex gap-1.5 sm:gap-2 overflow-x-auto snap-x snap-mandatory pb-3 pt-1 w-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                            @foreach ($mediaPaths as $mediaPath)
                                <div @click="openImage = true; activeImage = '{{ asset('storage/' . $mediaPath) }}'" data-media-slide="true" class="snap-center shrink-0 w-[85%] sm:w-[48%] aspect-[4/5] cursor-pointer border-2 sm:border-4 border-[var(--skuy-border-strong)]  shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] sm:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] rounded-xl sm:rounded-2xl overflow-hidden group transition-all hover:translate-y-[-2px]">
                                    <img src="{{ asset('storage/' . $mediaPath) }}" alt="{{ __('threads.media.photo') }}" class="w-full h-full object-cover group-hover:opacity-85 transition-opacity">
                                </div>
                            @endforeach
                        </div>

                        <!-- INDIKATOR TITIK-TITIK (DOTS) -->
                        <div data-media-indicator="true" class="flex justify-center items-center gap-1.5 mt-1 sm:mt-2 mb-2">
                            @foreach ($mediaPaths as $index => $mediaPath)
                                <div data-media-indicator-dot="true" class="h-1.5 sm:h-2 rounded-full border border-[var(--skuy-border-strong)]  transition-all duration-300" :class="activeSlide === {{ $index }} ? 'w-4 sm:w-5 bg-[var(--skuy-secondary)]' : 'w-1.5 sm:w-2 bg-[var(--skuy-border-subtle)]'"></div>
                            @endforeach
                        </div>

                        <!-- Modal Gambar Layar Penuh -->
                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 bg-black/90 backdrop-blur-sm">
                            <button @click="openImage = false" aria-label="{{ __('threads.media.close_preview') }}" class="absolute top-4 right-4 text-white z-[110] bg-black/50 rounded-full p-1 border-2 border-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-w-full max-h-[90vh] object-contain border-2 border-white shadow-[4px_4px_0px_0px_rgba(255,255,255,1)]">
                        </div>
                    </div>
                @endif

                <!-- Ikon Interaksi Postingan Utama -->
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap border-t-2 border-dashed border-[var(--skuy-border-subtle)]  pt-4">
                    <!-- Tombol Like -->
                    <form action="{{ route('posts.like', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $displayPost->liked_by_current_user ? 'bg-[var(--skuy-reaction)] text-[var(--skuy-on-reaction)]' : 'bg-[var(--skuy-surface)] text-[var(--skuy-text-primary)] hover:bg-[var(--skuy-reaction)] hover:text-[var(--skuy-on-reaction)]' }}">
                            <svg class="w-5 h-5 {{ $displayPost->liked_by_current_user ? 'fill-[var(--skuy-on-reaction)]' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <span class="text-xs font-black">{{ $displayPost->likes_count > 0 ? $displayPost->likes_count : '' }}</span>
                        </button>
                    </form>
                    <!-- Tombol Komentar -->
                    <div class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-[var(--skuy-on-selected)] cursor-default">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        <span class="text-xs font-black">{{ $displayPost->comments_count > 0 ? $displayPost->comments_count : '' }}</span>
                    </div>
                    <!-- Tombol Repost (Berfungsi) -->
                    <form action="{{ route('posts.repost', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $displayPost->reposted_by_current_user ? 'bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)]' : 'bg-[var(--skuy-surface)] text-[var(--skuy-text-primary)] hover:bg-[var(--skuy-secondary)] hover:text-[var(--skuy-on-secondary)]' }} group" title="{{ __('threads.actions.repost') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform {{ $displayPost->reposted_by_current_user ? 'fill-[var(--skuy-on-secondary)]' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <!-- Angka Jumlah Repost -->
                            @if($displayPost->reposts_count > 0)
                                <span class="text-xs font-black">{{ $displayPost->reposts_count }}</span>
                            @endif
                        </button>
                    </form>
                    @if ($displayPost->bookmarked_by_current_user)
                        <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-[var(--skuy-selected)] text-[var(--skuy-on-selected)] group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                            </button>
                        </form>
                    @else
                        <form action="{{ route('bookmarks.store', $displayPost->uid) }}" method="POST">
                            @csrf
                            <button type="submit" aria-label="{{ __('threads.actions.save') }}" title="{{ __('threads.actions.save') }}" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-[var(--skuy-surface)]  text-[var(--skuy-text-primary)]  hover:bg-[var(--skuy-selected)] hover:text-[var(--skuy-on-selected)] group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                            </button>
                        </form>
                    @endif
                    <!-- Tombol Share (Berfungsi) -->
                    <button @click="copyAndToast('{{ url()->current() }}', '{{ __('threads.thread_link_copied') }}')" type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-[var(--skuy-surface)]  text-[var(--skuy-text-primary)]  hover:bg-[var(--skuy-selected)] hover:text-[var(--skuy-on-selected)] group" title="{{ __('threads.actions.share') }}">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FORM KOMENTAR UTAMA (Gaya Threads, di bawah kartu postingan) -->
            <!-- ============================================================ -->
            <div class="mb-6 px-2 sm:px-0">
                <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" x-data="{ isSubmitting: false, content: '' }" @submit="isSubmitting = true" class="flex items-center gap-3 bg-[var(--skuy-surface-subtle)]  border-2 border-[var(--skuy-border-strong)]  rounded-full px-2 py-1.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] transition-colors focus-within:bg-[var(--skuy-surface)] ">
                    @csrf
                    <!-- Avatar Current User -->
                    <div class="flex-shrink-0 ml-1">
                        @if (Auth::user()->avatar)
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}" class="h-8 w-8 rounded-full object-cover border border-[var(--skuy-border-subtle)] ">
                        @else
                            <div class="h-8 w-8 bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-xs border border-[var(--skuy-border-subtle)] ">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    
                    <!-- Input Text (Tanpa Border) -->
                    <input type="text" name="content" x-model="content" class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-bold text-[var(--skuy-text-primary)]  placeholder-[var(--skuy-text-muted)] p-0" placeholder="{{ __('threads.comments.reply_to', ['name' => $displayPost->user->username]) }}" required autocomplete="off">
                    
                    <!-- Tombol Kirim / Ikon Tambahan -->
                    <div class="flex items-center gap-2 mr-2 text-[var(--skuy-text-muted)]">
                        <button type="button" class="hover:text-[var(--skuy-secondary)] transition-colors" title="{{ __('threads.image_coming_soon') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </button>
                        <!-- Tombol Post Muncul Jika Ada Teks -->
                        <button type="submit" x-show="content.trim().length > 0" :disabled="isSubmitting" class="ml-1 bg-[var(--skuy-brand)] text-[var(--skuy-on-brand)]  rounded-full px-4 py-1 text-xs font-black uppercase hover:scale-105 transition-transform" style="display: none;">
                            <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                            <span x-show="isSubmitting">...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- DAFTAR KOMENTAR (Berjejer ke bawah) -->
            <div class="space-y-4 px-2 sm:px-0">
                @if($comments->isNotEmpty())
                    <h3 class="font-black uppercase text-[var(--skuy-text-muted)] mb-4 border-b-2 border-[var(--skuy-border-strong)]  pb-2">{{ __('threads.labels.replies') }}</h3>
                @endif

                @foreach ($comments as $comment)
                    @php
                        $showExpandedReplies = $expandedReplyRootId === $comment->id && $expandedReplies !== null;
                        $visibleReplies = $showExpandedReplies ? $expandedReplies : $comment->feedReplies;
                        $replyExpansionUrl = route('posts.show', [
                            'post' => $post,
                            'comments_page' => $comments->currentPage(),
                            'replies_for' => $comment->id,
                        ]).'#comment-'.$comment->id;
                    @endphp
                    <div id="comment-{{ $comment->id }}" x-data="{ openReply: false, isSubmitting: false }" class="flex gap-3 relative scroll-mt-20">
                        <div class="flex flex-col items-center flex-shrink-0">
                            @if ($comment->user->avatar)
                                <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}" class="h-8 w-8 object-cover border-2 border-[var(--skuy-border-strong)] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                            @else
                                <div class="h-8 w-8 border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                    {{ substr($comment->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="w-0.5 bg-[var(--skuy-border-subtle)]  flex-1 mt-2 mb-1 min-h-[20px]" x-show="openReply || {{ $comment->replies_count > 0 ? 'true' : 'false' }}"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                <span class="font-black text-[13px] text-[var(--skuy-text-primary)] ">{{ $comment->user->username }}</span>
                                <span class="text-[10px] font-bold text-[var(--skuy-text-muted)]">{{ $comment->created_at->locale('id')->diffForHumans(null, true) }}</span>
                            </div>
                            <p class="text-[var(--skuy-text-primary)]  font-medium text-sm leading-relaxed mb-1">{{ $comment->content }}</p>
                            
                            <!-- Ikon Interaksi Komentar Utama -->
                            <div class="flex items-center gap-4 mt-1.5 mb-2 text-[var(--skuy-text-muted)] ">
                                
                                <!-- 1. Tombol Like (Berfungsi) -->
                                <form action="{{ route('comments.like', $comment->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="flex items-center gap-1 group transition-colors {{ $comment->liked_by_current_user ? 'text-[var(--skuy-reaction)]' : 'hover:text-[var(--skuy-reaction)]' }}" title="{{ __('threads.labels.like') }}">
                                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform {{ $comment->liked_by_current_user ? 'fill-[var(--skuy-reaction)]' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                        <!-- Angka Like -->
                                        @if($comment->likes_count > 0)
                                            <span class="text-[10px] font-black">{{ $comment->likes_count }}</span>
                                        @endif
                                    </button>
                                </form>

                                <!-- 2. Ikon Balas (Membuka Form) -->
                                <button @click="openReply = !openReply" type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.actions.reply') }}">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                </button>
                                
                                <!-- 3. Ikon Repost (Visual Only untuk saat ini) -->
                                <button type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.repost_coming_soon') }}">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>

                                <!-- 4. Ikon Share (Berfungsi Copy Link URL Postingan) -->
                                <button @click="copyAndToast('{{ url()->current() }}#comment-{{ $comment->id }}', '{{ __('threads.comment_link_copied') }}')" type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.actions.share') }}">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </button>
                            </div>
                            
                            <!-- Form Balasan (Tampil saat ikon Balas diklik) -->
                            <div x-show="openReply" style="display: none;" class="mt-3 w-full">
                                <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="w-full flex gap-2 items-center" @submit="isSubmitting = true">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-b-2 border-[var(--skuy-border-strong)]  focus:ring-0 focus:outline-none text-xs font-bold p-0 pb-1 text-[var(--skuy-text-primary)]  placeholder-[var(--skuy-text-muted)]" placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}" required>
                                    <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] text-[var(--skuy-on-brand)] font-black uppercase border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-brand)] hover:bg-[var(--skuy-brand-hover)] px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-[var(--skuy-border-strong)]">
                                        <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                        <span x-show="isSubmitting">...</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Area Balasan Anak -->
                            @if ($visibleReplies->count() > 0)
                                <div class="mt-4 space-y-4">
                                    @foreach ($visibleReplies as $reply)
                                        <div id="reply-{{ $reply->id }}" class="flex gap-2 scroll-mt-20">
                                            <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                                @if ($reply->user->avatar)
                                                    <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}" class="h-6 w-6 object-cover border-2 border-[var(--skuy-border-strong)] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                @else
                                                    <div class="h-6 w-6 border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-[10px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                        {{ substr($reply->user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                                @if (!$loop->last)
                                                    <div class="w-0.5 bg-[var(--skuy-border-subtle)]  flex-1 mt-1 min-h-[10px]"></div>
                                                @endif
                                            </div>
                                            <div class="flex-1 pb-1">
                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                    <span class="font-black text-xs text-[var(--skuy-text-primary)] ">{{ $reply->user->username }}</span>
                                                    <span class="text-[9px] font-bold text-[var(--skuy-text-muted)]">{{ $reply->created_at->locale('id')->diffForHumans(null, true) }}</span>
                                                </div>
                                                <p class="text-[var(--skuy-text-primary)]  font-medium text-[13px] leading-relaxed">{{ $reply->content }}</p>
                                                
                                                <!-- Ikon Interaksi Balasan Anak -->
                                                <div class="flex items-center gap-4 mt-1.5 text-[var(--skuy-text-muted)] ">
                                                    <!-- Tombol Like Anak -->
                                                    <form action="{{ route('comments.like', $reply->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="flex items-center gap-1 group transition-colors {{ $reply->liked_by_current_user ? 'text-[var(--skuy-reaction)]' : 'hover:text-[var(--skuy-reaction)]' }}" title="{{ __('threads.labels.like') }}">
                                                            <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform {{ $reply->liked_by_current_user ? 'fill-[var(--skuy-reaction)]' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                                            @if($reply->likes_count > 0)
                                                                <span class="text-[9px] font-black">{{ $reply->likes_count }}</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                    
                                                    <button @click="openReply = !openReply" type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.actions.reply') }}">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                    </button>
                                                    <button type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.repost_coming_soon') }}">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                    </button>
                                                    <!-- Share Link -->
                                                    <button @click="copyAndToast(@js(request()->fullUrl().'#reply-'.$reply->id), @js(__('threads.reply_link_copied')))" type="button" class="hover:text-[var(--skuy-secondary)] transition-colors group" title="{{ __('threads.actions.share') }}">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ((! $showExpandedReplies && $comment->replies_count > $visibleReplies->count()) || ($showExpandedReplies && $expandedReplies->hasMorePages()))
                                <div class="mt-3">
                                    <a href="{{ $showExpandedReplies ? $expandedReplies->nextPageUrl().'#comment-'.$comment->id : $replyExpansionUrl }}" class="inline-flex min-h-[44px] max-w-full items-center justify-center border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] px-3 py-2 text-center text-xs font-black uppercase leading-tight text-[var(--skuy-on-selected)] shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] transition-all hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--skuy-focus)] focus-visible:ring-offset-2  focus-visible:ring-offset-[var(--skuy-background)]">
                                        {{ __('threads.actions.view_more_replies') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($comments->hasPages())
                <div class="mt-6">
                    {{ $comments->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
