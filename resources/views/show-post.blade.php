<x-app-layout>
    @php
        $isRepost = $post->id !== $displayPost->id;
    @endphp

    <div class="skuy-feed-page">
        <div class="skuy-feed-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <!-- Header Kembali -->
            <div class="mb-4 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <h1 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('threads.labels.thread') }}</h1>
            </div>

            <!-- Postingan utama -->
            @if ($isRepost)
                <div class="skuy-post__repost">
                    <x-fa-icon name="retweet" class="text-base" />
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
                                <x-fa-icon name="xmark" weight="solid" class="text-[32px]" />
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
                                <x-fa-icon name="xmark" weight="solid" class="text-[32px]" />
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" alt="{{ __('threads.media.full_photo') }}" class="max-h-[90vh] max-w-full border border-white object-contain">
                        </div>
                    </div>
                @endif
                            </div>
                        </div>

                        <!-- Ikon Interaksi Postingan Utama -->
                        <div class="skuy-actions skuy-actions--primary mt-4 border-t border-[var(--skuy-border-subtle)] pt-3">
                    <!-- Tombol Like -->
                    <form action="{{ route('posts.like', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" aria-pressed="{{ $displayPost->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction" title="{{ __('threads.labels.like') }}">
                            <x-fa-icon name="heart" :weight="$displayPost->liked_by_current_user ? 'solid' : 'regular'" class="text-xl" />
                            <span class="text-xs font-semibold">{{ $displayPost->likes_count > 0 ? $displayPost->likes_count : '' }}</span>
                        </button>
                    </form>
                    <!-- Tombol Komentar -->
                    <div class="skuy-action cursor-default" aria-label="{{ __('threads.actions.reply') }}" title="{{ __('threads.actions.reply') }}">
                        <x-fa-icon name="comment" class="text-xl" />
                        <span class="text-xs font-semibold">{{ $displayPost->comments_count > 0 ? $displayPost->comments_count : '' }}</span>
                    </div>
                    <!-- Tombol Repost (Berfungsi) -->
                    <form action="{{ route('posts.repost', $displayPost->uid) }}" method="POST">
                        @csrf
                        <button type="submit" aria-pressed="{{ $displayPost->reposted_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--selected" title="{{ __('threads.actions.repost') }}">
                            <x-fa-icon name="retweet" :weight="$displayPost->reposted_by_current_user ? 'solid' : 'regular'" class="text-xl" />
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
                    <!-- Tombol Share (Berfungsi) -->
                    <button @click="copyAndToast('{{ url()->current() }}', '{{ __('threads.thread_link_copied') }}')" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                        <x-fa-icon name="share-nodes" class="text-xl" />
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
                        <x-fa-icon name="image" class="text-xl" />
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
                    <div id="comment-{{ $comment->id }}" x-data="{ openReply: false, isSubmitting: false }" class="skuy-comment skuy-post-detail__reply skuy-post-detail__reply--root relative scroll-mt-20">
                        <div class="skuy-comment__avatar-column flex flex-col items-center flex-shrink-0">
                            @if ($comment->user->avatar)
                                <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $comment->user->name]) }}" class="skuy-avatar skuy-avatar--small object-cover">
                            @else
                                <div class="skuy-avatar skuy-avatar--small object-cover">
                                    {{ substr($comment->user->name, 0, 1) }}
                                </div>
                            @endif
                            @if ($comment->replies_count > 0)
                                <div class="skuy-post-detail__thread-stem"></div>
                            @endif
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
                                    <button type="submit" aria-pressed="{{ $comment->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction" title="{{ __('threads.labels.like') }}">
                                        <x-fa-icon name="heart" :weight="$comment->liked_by_current_user ? 'solid' : 'regular'" class="text-base" />
                                        <!-- Angka Like -->
                                        @if($comment->likes_count > 0)
                                            <span class="text-[10px] font-semibold">{{ $comment->likes_count }}</span>
                                        @endif
                                    </button>
                                </form>

                                <!-- 2. Ikon Balas (Membuka Form) -->
                                <button @click="openReply = !openReply" type="button" class="skuy-action" title="{{ __('threads.actions.reply') }}" aria-label="{{ __('threads.actions.reply') }}">
                                    <x-fa-icon name="comment-dots" class="text-base" />
                                    @if ($comment->replies_count > 0)
                                        <span data-comment-reply-count="{{ $comment->replies_count }}" class="text-[10px] font-semibold">{{ $comment->replies_count }}</span>
                                    @endif
                                </button>
                                
                                <!-- 3. Ikon Repost (Visual Only untuk saat ini) -->
                                <button type="button" class="skuy-action" title="{{ __('threads.repost_coming_soon') }}" aria-label="{{ __('threads.repost_coming_soon') }}">
                                    <x-fa-icon name="retweet" class="text-base" />
                                </button>

                                <!-- 4. Ikon Share (Berfungsi Copy Link URL Postingan) -->
                                <button @click="copyAndToast('{{ url()->current() }}#comment-{{ $comment->id }}', '{{ __('threads.comment_link_copied') }}')" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                                    <x-fa-icon name="share-nodes" class="text-base" />
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
                                <div class="skuy-comment__replies skuy-comment__replies--has-items" data-reply-thread-root="{{ $comment->id }}">
                                    @foreach ($visibleReplies as $reply)
                                        <div id="reply-{{ $reply->id }}" class="skuy-comment skuy-post-detail__reply skuy-post-detail__reply--nested relative scroll-mt-20">
                                            <div class="skuy-comment__avatar-column flex flex-col items-center flex-shrink-0 mt-0.5">
                                                @if ($reply->user->avatar)
                                                    <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}" class="skuy-avatar skuy-avatar--tiny object-cover">
                                                @else
                                                    <div class="skuy-avatar skuy-avatar--tiny object-cover">
                                                        {{ substr($reply->user->name, 0, 1) }}
                                                    </div>
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
                                                        <button type="submit" aria-pressed="{{ $reply->liked_by_current_user ? 'true' : 'false' }}" class="skuy-action skuy-action--reaction" title="{{ __('threads.labels.like') }}">
                                                            <x-fa-icon name="heart" :weight="$reply->liked_by_current_user ? 'solid' : 'regular'" class="text-sm" />
                                                            @if($reply->likes_count > 0)
                                                                <span class="text-[9px] font-semibold">{{ $reply->likes_count }}</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                    
                                                    <button @click="openReply = !openReply" type="button" class="skuy-action" title="{{ __('threads.actions.reply') }}" aria-label="{{ __('threads.actions.reply') }}">
                                                        <x-fa-icon name="comment-dots" class="text-sm" />
                                                    </button>
                                                    <button type="button" class="skuy-action" title="{{ __('threads.repost_coming_soon') }}" aria-label="{{ __('threads.repost_coming_soon') }}">
                                                        <x-fa-icon name="retweet" class="text-sm" />
                                                    </button>
                                                    <!-- Share Link -->
                                                    <button @click="copyAndToast(@js(request()->fullUrl().'#reply-'.$reply->id), @js(__('threads.reply_link_copied')))" type="button" class="skuy-action" title="{{ __('threads.actions.share') }}" aria-label="{{ __('threads.actions.share') }}">
                                                        <x-fa-icon name="share-nodes" class="text-sm" />
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
