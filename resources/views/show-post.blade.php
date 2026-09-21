<x-app-layout>
    @php
        $isRepost = $post->id !== $displayPost->id;
    @endphp

    <div class="skuy-feed-page">
        <div class="skuy-feed-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <!-- Header Kembali -->
            <div class="mb-4 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('threads.labels.thread') }}</h1>
            </div>

            <!-- Postingan utama -->
            @if ($isRepost)
                <div class="skuy-post__repost">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                </div>
            @endif
            <article class="skuy-post mb-4">
                <!-- Header User -->
                <div class="skuy-post__layout">
                    <div class="skuy-post__avatar-column">
                        @if ($displayPost->user->avatar)
                            <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}" class="skuy-avatar object-cover">
                        @else
                            <div class="skuy-avatar object-cover">
                                {{ substr($displayPost->user->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="skuy-post__body">
                        <div class="skuy-post__body-main">
                            <div class="skuy-post__header">
                                <div class="skuy-post__identity">
                                    <span class="skuy-post__display-name">{{ $displayPost->user->username }}</span>
                            @if($displayPost->user->is_verified)
                                <span class="inline-flex text-[var(--skuy-secondary)]" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path></svg>
                                </span>
                            @endif
                                    <time datetime="{{ $displayPost->created_at->toIso8601String() }}" class="skuy-post__time">
                                        {{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}
                                    </time>
                                </div>
                            </div>

                            <div class="skuy-post__content">
                                <!-- Isi Teks -->
                                <p class="skuy-post__text">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="skuy-hashtag">#$1</a>', e($displayPost->content)) !!}</p>
                
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
                        <div @click="openImage = true; activeImage = '{{ asset('storage/' . $mediaPaths[0]) }}'" class="cursor-pointer overflow-hidden rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)]">
                            <img src="{{ asset('storage/' . $mediaPaths[0]) }}" alt="{{ __('threads.media.photo') }}" class="h-auto w-full object-cover transition-opacity hover:opacity-90">
                        </div>

                        <!-- Modal Gambar Layar Penuh -->
                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 bg-black/90 backdrop-blur-sm">
                            <button @click="openImage = false" aria-label="{{ __('threads.media.close_preview') }}" class="absolute right-4 top-4 z-[110] rounded-full border border-white bg-black/50 p-1 text-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-h-[90vh] max-w-full border border-white object-contain">
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
                        <div @scroll.passive="updateDots" data-media-slider-track="true" class="skuy-media-track">
                            @foreach ($mediaPaths as $mediaPath)
                                <div @click="openImage = true; activeImage = '{{ asset('storage/' . $mediaPath) }}'" data-media-slide="true" class="skuy-media-item w-[85%] sm:w-[48%]">
                                    <img src="{{ asset('storage/' . $mediaPath) }}" alt="{{ __('threads.media.photo') }}" class="w-full h-full object-cover">
                                </div>
                            @endforeach
                        </div>

                        <!-- INDIKATOR TITIK-TITIK (DOTS) -->
                        <div data-media-indicator="true" class="skuy-media-dots mb-2">
                            @foreach ($mediaPaths as $index => $mediaPath)
                                <div data-media-indicator-dot="true" class="skuy-media-dot" :class="activeSlide === {{ $index }} ? 'is-active' : ''"></div>
                            @endforeach
                        </div>

                        <!-- Modal Gambar Layar Penuh -->
                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 bg-black/90 backdrop-blur-sm">
                            <button @click="openImage = false" aria-label="{{ __('threads.media.close_preview') }}" class="absolute right-4 top-4 z-[110] rounded-full border border-white bg-black/50 p-1 text-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-h-[90vh] max-w-full border border-white object-contain">
                        </div>
                    </div>
                @endif
                            </div>
                        </div>

                        <!-- Ikon Interaksi Postingan Utama -->
                        <div class="skuy-actions mt-4 border-t border-[var(--skuy-border-subtle)] pt-3">
                    <!-- Tombol Like -->
                    <form action="{{ route('posts.like', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" aria-pressed="{{ $displayPost->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction{{ $displayPost->liked_by_current_user ? ' !border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : '' }}" title="{{ __('threads.labels.like') }}">
                            <svg class="h-5 w-5 {{ $displayPost->liked_by_current_user ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"></path></svg>
                            <span class="text-xs font-semibold">{{ $displayPost->likes_count > 0 ? $displayPost->likes_count : '' }}</span>
                        </button>
                    </form>
                    <!-- Tombol Komentar -->
                    <div class="skuy-action cursor-default" aria-label="{{ __('threads.actions.reply') }}" title="{{ __('threads.actions.reply') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.75L4 20l1.35-3.6A7.3 7.3 0 0 1 4 11.5 7.5 7.5 0 0 1 12 4a7.5 7.5 0 0 1 8 7.5Z"></path></svg>
                        <span class="text-xs font-semibold">{{ $displayPost->comments_count > 0 ? $displayPost->comments_count : '' }}</span>
                    </div>
                    <!-- Tombol Repost (Berfungsi) -->
                    <form action="{{ route('posts.repost', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" aria-pressed="{{ $displayPost->reposted_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--selected{{ $displayPost->reposted_by_current_user ? ' !border-[var(--skuy-secondary)] !bg-[var(--skuy-secondary)] !text-[var(--skuy-on-secondary)]' : '' }}" title="{{ __('threads.actions.repost') }}">
                            <svg class="h-5 w-5 {{ $displayPost->reposted_by_current_user ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path></svg>
                            <!-- Angka Jumlah Repost -->
                            @if($displayPost->reposts_count > 0)
                                <span class="text-xs font-semibold">{{ $displayPost->reposts_count }}</span>
                            @endif
                        </button>
                    </form>
                    @if ($displayPost->bookmarked_by_current_user)
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
                    <!-- Tombol Share (Berfungsi) -->
                    <button @click="copyAndToast('{{ url()->current() }}', '{{ __('threads.thread_link_copied') }}')" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 18-9-3-9 3 9-18Zm0 0v15"></path></svg>
                    </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- ============================================================ -->
            <!-- FORM KOMENTAR UTAMA (Gaya Threads, di bawah kartu postingan) -->
            <!-- ============================================================ -->
            <div class="skuy-composer mb-6">
                <div class="skuy-composer__identity">
                    @if (Auth::user()->avatar)
                        <div class="skuy-avatar skuy-avatar--small object-cover">
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                        </div>
                    @else
                        <div class="skuy-avatar skuy-avatar--small object-cover" aria-label="{{ __('threads.media.avatar_of', ['name' => Auth::user()->name]) }}">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </div>
                    @endif
                    <div class="skuy-composer__identity-copy">
                        <span class="skuy-composer__username">{{ '@' . Auth::user()->username }}</span>
                    </div>
                </div>

                <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" x-data="{ isSubmitting: false, content: '' }" @submit="isSubmitting = true" class="mt-3 flex min-w-0 items-center gap-2">
                    @csrf
                    <input type="text" name="content" x-model="content" class="skuy-inline-input" placeholder="{{ __('threads.comments.reply_to', ['name' => $displayPost->user->username]) }}" required autocomplete="off">

                    <button type="button" class="skuy-action shrink-0" title="{{ __('threads.image_coming_soon') }}" aria-label="{{ __('threads.image_coming_soon') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </button>
                    <!-- Tombol Post Muncul Jika Ada Teks -->
                    <button type="submit" x-show="content.trim().length > 0" :disabled="isSubmitting" class="skuy-button skuy-button--primary min-h-[40px] shrink-0 px-3 py-1 text-xs" style="display: none;">
                        <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                        <span x-show="isSubmitting">...</span>
                    </button>
                </form>
            </div>

            <!-- DAFTAR KOMENTAR (Berjejer ke bawah) -->
            <div class="space-y-4">
                @if($comments->isNotEmpty())
                    <h2 class="mb-4 border-b border-[var(--skuy-border-subtle)] pb-2 text-lg font-bold text-[var(--skuy-text-primary)]">{{ __('threads.labels.replies') }}</h2>
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
                    <div id="comment-{{ $comment->id }}" x-data="{ openReply: false, isSubmitting: false }" class="skuy-comment relative scroll-mt-20">
                        <div class="flex flex-col items-center flex-shrink-0">
                            @if ($comment->user->avatar)
                                <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}" class="skuy-avatar skuy-avatar--small object-cover">
                            @else
                                <div class="skuy-avatar skuy-avatar--small object-cover">
                                    {{ substr($comment->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="w-0.5 bg-[var(--skuy-border-subtle)]  flex-1 mt-2 mb-1 min-h-[20px]" x-show="openReply || {{ $comment->replies_count > 0 ? 'true' : 'false' }}"></div>
                        </div>
                        <div class="skuy-comment__body pb-4">
                            <div class="skuy-comment__identity">
                                <span class="skuy-comment__user">{{ $comment->user->username }}</span>
                                <span class="skuy-comment__time">{{ $comment->created_at->locale('id')->diffForHumans(null, true) }}</span>
                            </div>
                            <p class="skuy-comment__text mb-1">{{ $comment->content }}</p>
                            
                            <!-- Ikon Interaksi Komentar Utama -->
                            <div class="skuy-actions mt-1.5 mb-2">
                                
                                <!-- 1. Tombol Like (Berfungsi) -->
                                <form action="{{ route('comments.like', $comment->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" aria-pressed="{{ $comment->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction{{ $comment->liked_by_current_user ? ' !border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : '' }}" title="{{ __('threads.labels.like') }}">
                                        <svg class="h-4 w-4 {{ $comment->liked_by_current_user ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                        <!-- Angka Like -->
                                        @if($comment->likes_count > 0)
                                            <span class="text-[10px] font-semibold">{{ $comment->likes_count }}</span>
                                        @endif
                                    </button>
                                </form>

                                <!-- 2. Ikon Balas (Membuka Form) -->
                                <button @click="openReply = !openReply" type="button" class="skuy-action" title="{{ __('threads.actions.reply') }}" aria-label="{{ __('threads.actions.reply') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                </button>
                                
                                <!-- 3. Ikon Repost (Visual Only untuk saat ini) -->
                                <button type="button" class="skuy-action" title="{{ __('threads.repost_coming_soon') }}" aria-label="{{ __('threads.repost_coming_soon') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>

                                <!-- 4. Ikon Share (Berfungsi Copy Link URL Postingan) -->
                                <button @click="copyAndToast('{{ url()->current() }}#comment-{{ $comment->id }}', '{{ __('threads.comment_link_copied') }}')" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </button>
                            </div>
                            
                            <!-- Form Balasan (Tampil saat ikon Balas diklik) -->
                            <div x-show="openReply" style="display: none;" class="mt-3 w-full">
                                <form action="{{ route('comments.store', $displayPost->uid) }}" method="POST" class="skuy-inline-form" @submit="isSubmitting = true">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <input type="text" name="content" class="skuy-inline-input" placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}" required>
                                    <button type="submit" :disabled="isSubmitting" class="skuy-button skuy-button--primary min-h-[40px] shrink-0 px-3 py-1 text-xs">
                                        <span x-show="!isSubmitting">{{ __('threads.actions.send') }}</span>
                                        <span x-show="isSubmitting">...</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Area Balasan Anak -->
                            @if ($visibleReplies->count() > 0)
                                <div class="skuy-comment__replies">
                                    @foreach ($visibleReplies as $reply)
                                        <div id="reply-{{ $reply->id }}" class="skuy-comment scroll-mt-20">
                                            <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                                @if ($reply->user->avatar)
                                                    <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}" class="skuy-avatar skuy-avatar--tiny object-cover">
                                                @else
                                                    <div class="skuy-avatar skuy-avatar--tiny object-cover">
                                                        {{ substr($reply->user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                                @if (!$loop->last)
                                                    <div class="w-0.5 bg-[var(--skuy-border-subtle)]  flex-1 mt-1 min-h-[10px]"></div>
                                                @endif
                                            </div>
                                            <div class="skuy-comment__body pb-1">
                                                <div class="skuy-comment__identity">
                                                    <span class="skuy-comment__user">{{ $reply->user->username }}</span>
                                                    <span class="skuy-comment__time">{{ $reply->created_at->locale('id')->diffForHumans(null, true) }}</span>
                                                </div>
                                                <p class="skuy-comment__text">{{ $reply->content }}</p>
                                                
                                                <!-- Ikon Interaksi Balasan Anak -->
                                                <div class="skuy-actions mt-1.5">
                                                    <!-- Tombol Like Anak -->
                                                    <form action="{{ route('comments.like', $reply->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" aria-pressed="{{ $reply->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction{{ $reply->liked_by_current_user ? ' !border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : '' }}" title="{{ __('threads.labels.like') }}">
                                                            <svg class="h-3.5 w-3.5 {{ $reply->liked_by_current_user ? 'fill-current' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                                            @if($reply->likes_count > 0)
                                                                <span class="text-[9px] font-semibold">{{ $reply->likes_count }}</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                    
                                                    <button @click="openReply = !openReply" type="button" class="skuy-action" title="{{ __('threads.actions.reply') }}" aria-label="{{ __('threads.actions.reply') }}">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                    </button>
                                                    <button type="button" class="skuy-action" title="{{ __('threads.repost_coming_soon') }}" aria-label="{{ __('threads.repost_coming_soon') }}">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                    </button>
                                                    <!-- Share Link -->
                                                    <button @click="copyAndToast(@js(request()->fullUrl().'#reply-'.$reply->id), @js(__('threads.reply_link_copied')))" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ((! $showExpandedReplies && $comment->replies_count > $visibleReplies->count()) || ($showExpandedReplies && $expandedReplies->hasMorePages()))
                                <div class="mt-3">
                                    <a href="{{ $showExpandedReplies ? $expandedReplies->nextPageUrl().'#comment-'.$comment->id : $replyExpansionUrl }}" class="skuy-load-more max-w-full">
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
