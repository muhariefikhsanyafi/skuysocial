<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-12 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300 relative">
        <div class="w-full max-w-2xl mx-auto px-2 sm:px-6">
            
            <!-- Header Kembali (Gaya Threads) -->
            <div class="flex items-center gap-4 mb-4 px-2">
                <a href="{{ route('feed') }}" class="p-2 hover:bg-gray-200 dark:hover:bg-[#242424] rounded-full transition-colors text-black dark:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="font-black text-xl text-black dark:text-white uppercase">Thread</h1>
            </div>

            <!-- KARTU POSTINGAN UTAMA -->
            <div class="bg-white dark:bg-[#101010] border-x-0 sm:border-4 border-y-2 sm:border-black dark:border-[#333333] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 mb-4">
                <!-- Header User -->
                <div class="flex gap-3 sm:gap-4 mb-3">
                    <div class="flex-shrink-0">
                        @if ($post->user->avatar)
                            <img src="{{ asset('storage/' . $post->user->avatar) }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                        @else
                            <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black bg-pink-400 rounded-full flex items-center justify-center text-black font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                {{ substr($post->user->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-black text-base text-black dark:text-white">{{ $post->user->username }}</span>
                            @if($post->user->is_verified)
                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-500 border-2 border-black" title="Terverifikasi">
                                    <svg class="w-2.5 h-2.5 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </span>
                            @endif
                            <span class="text-xs font-bold text-gray-500 ml-1">{{ $post->created_at->locale('id')->diffForHumans(null, true) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Isi Teks -->
                <p class="text-black dark:text-white font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-4">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-blue-600 dark:text-blue-400 font-bold hover:underline decoration-2">#$1</a>', e($post->content)) !!}</p>
                
                <!-- Carousel Gambar (Sama seperti Feed) -->
                @if ($post->images && count($post->images) > 0)
                    <div x-data="{ openImage: false, activeImage: '' }" class="mb-4">
                        <div class="flex gap-1.5 sm:gap-2 overflow-x-auto snap-x snap-mandatory pb-3 w-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                            @foreach ($post->images as $imgUrl)
                                <div @click="openImage = true; activeImage = '{{ asset('storage/' . $imgUrl) }}'" class="snap-center shrink-0 w-[85%] sm:w-[48%] aspect-[4/5] cursor-pointer border-2 sm:border-4 border-black dark:border-[#333333] shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] rounded-xl overflow-hidden group">
                                    <img src="{{ asset('storage/' . $imgUrl) }}" class="w-full h-full object-cover group-hover:opacity-85 transition-opacity">
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Modal Gambar Layar Penuh -->
                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 bg-black/90 backdrop-blur-sm">
                            <button @click="openImage = false" class="absolute top-4 right-4 text-white z-[110] bg-black/50 rounded-full p-1 border-2 border-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img @click.away="openImage = false" :src="activeImage" class="max-w-full max-h-[90vh] object-contain border-2 border-white shadow-[4px_4px_0px_0px_rgba(255,255,255,1)]">
                        </div>
                    </div>
                @endif

                <!-- Ikon Interaksi Postingan Utama -->
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap border-t-2 border-dashed border-gray-300 dark:border-[#333333] pt-4">
                    <!-- Tombol Like -->
                    <form action="{{ route('posts.like', $post->uid) }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $post->isLikedBy(auth()->user()) ? 'bg-pink-400 text-black' : 'bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-pink-300' }}">
                            <svg class="w-5 h-5 {{ $post->isLikedBy(auth()->user()) ? 'fill-black' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <span class="text-xs font-black">{{ $post->likes->count() > 0 ? $post->likes->count() : '' }}</span>
                        </button>
                    </form>
                    <!-- Tombol Komentar -->
                    <div class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black bg-cyan-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-black cursor-default">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        <span class="text-xs font-black">{{ $post->comments->count() > 0 ? $post->comments->count() : '' }}</span>
                    </div>
                    <!-- Tombol Repost (Berfungsi) -->
                    <form action="{{ route('posts.repost', $post->uid) }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $post->isRepostedBy(auth()->user()) ? 'bg-emerald-400 text-black' : 'bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-emerald-300' }} group" title="Posting Ulang">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform {{ $post->isRepostedBy(auth()->user()) ? 'fill-black' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <!-- Angka Jumlah Repost -->
                            @if($post->reposts()->count() > 0)
                                <span class="text-xs font-black">{{ $post->reposts()->count() }}</span>
                            @endif
                        </button>
                    </form>
                    <!-- Tombol Share (Berfungsi) -->
                    <button @click="copyAndToast('{{ url()->current() }}', 'Tautan utas berhasil disalin!')" type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-yellow-300 group" title="Bagikan">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FORM KOMENTAR UTAMA (Gaya Threads, di bawah kartu postingan) -->
            <!-- ============================================================ -->
            <div class="mb-6 px-2 sm:px-0">
                <form action="{{ route('comments.store', $post->uid) }}" method="POST" x-data="{ isSubmitting: false, content: '' }" @submit="isSubmitting = true" class="flex items-center gap-3 bg-gray-100 dark:bg-[#1f1f1f] border-2 border-black dark:border-[#333333] rounded-full px-2 py-1.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] transition-colors focus-within:bg-white dark:focus-within:bg-[#181818]">
                    @csrf
                    <!-- Avatar Current User -->
                    <div class="flex-shrink-0 ml-1">
                        @if (Auth::user()->avatar)
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="h-8 w-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                        @else
                            <div class="h-8 w-8 bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-xs border border-gray-300 dark:border-gray-600">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    
                    <!-- Input Text (Tanpa Border) -->
                    <input type="text" name="content" x-model="content" class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-bold text-black dark:text-white placeholder-gray-500 p-0" placeholder="Balas ke {{ $post->user->username }}..." required autocomplete="off">
                    
                    <!-- Tombol Kirim / Ikon Tambahan -->
                    <div class="flex items-center gap-2 mr-2 text-gray-500">
                        <button type="button" class="hover:text-black dark:hover:text-white transition-colors" title="Fitur gambar menyusul">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </button>
                        <!-- Tombol Post Muncul Jika Ada Teks -->
                        <button type="submit" x-show="content.trim().length > 0" :disabled="isSubmitting" class="ml-1 bg-black dark:bg-white text-yellow-300 dark:text-black rounded-full px-4 py-1 text-xs font-black uppercase hover:scale-105 transition-transform" style="display: none;">
                            <span x-show="!isSubmitting">Kirim</span>
                            <span x-show="isSubmitting">...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- DAFTAR KOMENTAR (Berjejer ke bawah) -->
            <div class="space-y-4 px-2 sm:px-0">
                @if($post->comments->whereNull('parent_id')->count() > 0)
                    <h3 class="font-black uppercase text-gray-500 mb-4 border-b-2 border-black dark:border-[#333333] pb-2">Balasan</h3>
                @endif
                
                @foreach ($post->comments->whereNull('parent_id') as $comment)
                    <div id="comment-{{ $comment->id }}" x-data="{ openReply: false, isSubmitting: false }" class="flex gap-3 relative scroll-mt-20">
                        <div class="flex flex-col items-center flex-shrink-0">
                            @if ($comment->user->avatar)
                                <img src="{{ asset('storage/' . $comment->user->avatar) }}" class="h-8 w-8 object-cover border-2 border-black rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                            @else
                                <div class="h-8 w-8 border-2 border-black bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                    {{ substr($comment->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="w-0.5 bg-gray-300 dark:bg-[#333333] flex-1 mt-2 mb-1 min-h-[20px]" x-show="openReply || {{ $comment->replies->count() > 0 ? 'true' : 'false' }}"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                <span class="font-black text-[13px] text-black dark:text-white">{{ $comment->user->username }}</span>
                                <span class="text-[10px] font-bold text-gray-500">{{ $comment->created_at->locale('id')->diffForHumans(null, true) }}</span>
                            </div>
                            <p class="text-black dark:text-white font-medium text-sm leading-relaxed mb-1">{{ $comment->content }}</p>
                            
                            <!-- Ikon Interaksi Komentar Utama -->
                            <div class="flex items-center gap-4 mt-1.5 mb-2 text-gray-500 dark:text-gray-400">
                                
                                <!-- 1. Tombol Like (Berfungsi) -->
                                <form action="{{ route('comments.like', $comment->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="flex items-center gap-1 group transition-colors {{ $comment->isLikedBy(auth()->user()) ? 'text-pink-500' : 'hover:text-pink-500' }}" title="Suka">
                                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform {{ $comment->isLikedBy(auth()->user()) ? 'fill-pink-500' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                        <!-- Angka Like -->
                                        @if($comment->likes()->count() > 0)
                                            <span class="text-[10px] font-black">{{ $comment->likes()->count() }}</span>
                                        @endif
                                    </button>
                                </form>

                                <!-- 2. Ikon Balas (Membuka Form) -->
                                <button @click="openReply = !openReply" type="button" class="hover:text-cyan-500 transition-colors group" title="Balas">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                </button>
                                
                                <!-- 3. Ikon Repost (Visual Only untuk saat ini) -->
                                <button type="button" class="hover:text-emerald-500 transition-colors group" title="Posting Ulang (Segera Hadir)">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>

                                <!-- 4. Ikon Share (Berfungsi Copy Link URL Postingan) -->
                                <button @click="copyAndToast('{{ url()->current() }}#comment-{{ $comment->id }}', 'Tautan komentar berhasil disalin!')" type="button" class="hover:text-yellow-500 transition-colors group" title="Bagikan">
                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </button>
                            </div>
                            
                            <!-- Form Balasan (Tampil saat ikon Balas diklik) -->
                            <div x-show="openReply" style="display: none;" class="mt-3 w-full">
                                <form action="{{ route('comments.store', $post->uid) }}" method="POST" class="w-full flex gap-2 items-center" @submit="isSubmitting = true">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-b-2 border-black dark:border-[#333333] focus:ring-0 focus:outline-none text-xs font-bold p-0 pb-1 text-black dark:text-white placeholder-gray-400" placeholder="Balas ke {{ $comment->user->name }}..." required>
                                    <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] text-black font-black uppercase border-2 border-black bg-yellow-300 px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
                                        <span x-show="!isSubmitting">Kirim</span>
                                        <span x-show="isSubmitting">...</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Area Balasan Anak -->
                            @if ($comment->replies->count() > 0)
                                <div class="mt-4 space-y-4">
                                    @foreach ($comment->replies as $reply)
                                        <div id="reply-{{ $reply->id }}" class="flex gap-2 scroll-mt-20">
                                            <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                                @if ($reply->user->avatar)
                                                    <img src="{{ asset('storage/' . $reply->user->avatar) }}" class="h-6 w-6 object-cover border-2 border-black rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                @else
                                                    <div class="h-6 w-6 border-2 border-black bg-pink-400 rounded-full flex items-center justify-center text-black font-black uppercase text-[10px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                        {{ substr($reply->user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                                @if (!$loop->last)
                                                    <div class="w-0.5 bg-gray-300 dark:bg-[#333333] flex-1 mt-1 min-h-[10px]"></div>
                                                @endif
                                            </div>
                                            <div class="flex-1 pb-1">
                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                    <span class="font-black text-xs text-black dark:text-white">{{ $reply->user->username }}</span>
                                                    <span class="text-[9px] font-bold text-gray-500">{{ $reply->created_at->locale('id')->diffForHumans(null, true) }}</span>
                                                </div>
                                                <p class="text-black dark:text-white font-medium text-[13px] leading-relaxed">{{ $reply->content }}</p>
                                                
                                                <!-- Ikon Interaksi Balasan Anak -->
                                                <div class="flex items-center gap-4 mt-1.5 text-gray-500 dark:text-gray-400">
                                                    <!-- Tombol Like Anak -->
                                                    <form action="{{ route('comments.like', $reply->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="flex items-center gap-1 group transition-colors {{ $reply->isLikedBy(auth()->user()) ? 'text-pink-500' : 'hover:text-pink-500' }}" title="Suka">
                                                            <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform {{ $reply->isLikedBy(auth()->user()) ? 'fill-pink-500' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                                            @if($reply->likes()->count() > 0)
                                                                <span class="text-[9px] font-black">{{ $reply->likes()->count() }}</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                    
                                                    <button @click="openReply = !openReply" type="button" class="hover:text-cyan-500 transition-colors group">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                    </button>
                                                    <button type="button" class="hover:text-emerald-500 transition-colors group">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                    </button>
                                                    <!-- Share Link -->
                                                    <button @click="copyAndToast('{{ url()->current() }}#reply-{{ $reply->id }}', 'Tautan balasan berhasil disalin!')" type="button" class="hover:text-yellow-500 transition-colors group" title="Bagikan">
                                                        <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>