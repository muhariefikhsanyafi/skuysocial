<x-app-layout>
    <!-- Background utama (#101010) -->
    <div class="py-8 sm:py-12 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        <div class="w-full 2xl:max-w-[1600px] mx-auto px-4 sm:px-8 lg:px-12 flex flex-col md:flex-row gap-8 lg:gap-20 items-start">
            
            <!-- SIDEBAR KIRI -->
<div x-data="{ sidebarOpen: false }" @toggle-sidebar.window="sidebarOpen = !sidebarOpen" class="w-full md:w-52 flex-shrink-0 md:sticky md:top-28 z-20 mb-6 md:mb-0">
    
    <!-- Menu Items (Disembunyikan di HP secara default, selalu tampil di Desktop) -->
    <div x-show="sidebarOpen" x-transition class="md:!flex flex-col gap-2.5" style="display: none;">
        
        <a href="{{ route('feed') }}" class="flex items-center gap-3 bg-yellow-300 border-2 border-black dark:border-[#333333] px-3 py-2.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black font-black uppercase text-sm group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            Beranda
        </a>

        <a href="#" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-2.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-pink-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            Cari
        </a>

        <a href="#" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-2.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-cyan-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
            Aktivitas
        </a>

        <a href="{{ route('user.profile', auth()->id()) }}" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-2.5 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-emerald-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            Profil
        </a>
        
    </div>
</div>

            <!-- AREA KONTEN TENGAH (Feed) -->
            <div class="flex-1 w-full max-w-3xl xl:max-w-4xl mx-auto">
                
                <!-- WADAH KUNING RAKSASA -->
                <div class="bg-yellow-300 dark:bg-[#181818] border-4 border-black dark:border-[#333333] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-6 lg:p-8 transition-colors duration-300">
                    
                    <!-- Form Input Utas -->
                    <div class="bg-white dark:bg-[#101010] border-4 border-black dark:border-[#333333] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 mb-8 transition-colors duration-300">
                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" x-data="{ isSubmitting: false, content: '' }" @submit="isSubmitting = true">
                            @csrf
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    @if (Auth::user()->avatar)
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="Avatar" class="h-12 w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                    @else
                                        <div class="h-12 w-12 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-xl shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                            {{ substr(Auth::user()->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <!-- Wrapper Alpine.js untuk Autocomplete -->
                                    <div x-data="{
                                            topicQuery: '',
                                            showSuggestions: false,
                                            availableTopics: {{ \App\Models\Post::whereNotNull('topic')->distinct()->pluck('topic')->toJson() }},
                                            
                                            get filteredTopics() {
                                                if (this.topicQuery === '') return [];
                                                return this.availableTopics.filter(t => t.toLowerCase().includes(this.topicQuery.toLowerCase()));
                                            },
                                            selectTopic(t) {
                                                this.topicQuery = t;
                                                this.showSuggestions = false;
                                            }
                                        }" 
                                        class="font-black text-sm uppercase text-black dark:text-white mb-1 flex items-center gap-1.5 flex-wrap">
                                        
                                        <!-- Nama & Badge -->
                                        {{ Auth::user()->name }}
                                        @if(Auth::user()->is_verified)
                                            <span class="bg-yellow-300 border border-black text-black text-[8px] px-1 font-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] inline-flex items-center" title="Terverifikasi Admin">✓</span>
                                        @endif
                                        
                                        <!-- Simbol Pemisah -->
                                        <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5">›</span>
                                        
                                        <!-- Input Autocomplete -->
                                        <div class="relative">
                                            <input type="text" name="topic" 
                                                   x-model="topicQuery" 
                                                   @focus="showSuggestions = true" 
                                                   @click.away="showSuggestions = false" 
                                                   class="bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-sm placeholder-gray-500 dark:placeholder-gray-600 w-32 sm:w-48" 
                                                   placeholder="Topik (opsional)..." 
                                                   maxlength="30" 
                                                   autocomplete="off">

                                            <!-- Dropdown Saran Topik (Neo-Brutalist) -->
                                            <div x-show="showSuggestions && filteredTopics.length > 0" 
                                                 style="display: none;" 
                                                 class="absolute top-full left-0 mt-2 w-48 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] z-50">
                                                
                                                <template x-for="t in filteredTopics" :key="t">
                                                    <div @click="selectTopic(t)" 
                                                         class="px-3 py-2 border-b-2 last:border-b-0 border-black dark:border-[#333333] hover:bg-yellow-300 dark:hover:bg-[#242424] cursor-pointer font-bold text-sm text-black dark:text-white transition-colors" 
                                                         x-text="t">
                                                    </div>
                                                </template>
                                                
                                            </div>
                                        </div>
                                    </div>
                                    <textarea name="content" rows="2" x-model="content" class="w-full bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-medium text-lg placeholder-gray-500 dark:placeholder-gray-400 resize-none" placeholder="Mulai utas baru..." required></textarea>
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mt-3 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed gap-3">
                                        <div class="flex-1 overflow-hidden">
                                            <input type="file" name="image" accept="image/*" class="text-xs w-full file:mr-2 file:py-1 file:px-2 file:border-2 file:border-black dark:file:border-[#333333] file:bg-yellow-300 file:text-black file:font-black file:uppercase file:cursor-pointer file:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:file:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:file:shadow-none dark:hover:file:shadow-none hover:file:translate-y-[2px] hover:file:translate-x-[2px] transition-all">
                                        </div>
                                        <button type="submit" :disabled="isSubmitting || content.trim() === ''" :class="(isSubmitting || content.trim() === '') ? 'bg-gray-200 dark:bg-[#242424] text-gray-500 dark:text-gray-500 shadow-none cursor-not-allowed border-gray-400 dark:border-[#333333]' : 'bg-cyan-400 text-black hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] border-black dark:border-[#333333]'" class="border-2 transition-all font-black uppercase px-6 py-1.5 flex-shrink-0 text-sm">
                                            <span x-show="!isSubmitting">Posting</span>
                                            <span x-show="isSubmitting">...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Area Daftar Status / Timeline -->
                    <div class="space-y-6">
                        @foreach ($posts as $post)
                            <!-- x-data="{ showComments: false }" DITAMBAHKAN DI SINI -->
                            <div x-data="{ showComments: false }" class="bg-white dark:bg-[#101010] border-4 border-black dark:border-[#333333] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 transition-colors duration-300">
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center flex-shrink-0">
                                        <a href="{{ route('user.profile', $post->user->id) }}">
                                            @if ($post->user->avatar)
                                                <img src="{{ asset('storage/' . $post->user->avatar) }}" alt="Avatar" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:shadow-none transition-all hover:translate-x-[2px] hover:translate-y-[2px]">
                                            @else
                                                <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-lg shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:shadow-none transition-all hover:translate-x-[2px] hover:translate-y-[2px]">
                                                    {{ substr($post->user->name, 0, 1) }}
                                                </div>
                                            @endif
                                        </a>
                                        <div class="w-1.5 bg-black dark:bg-[#333333] flex-1 mt-3 min-h-[40px]"></div>
                                    </div>
                                    <div class="flex-1 min-w-0 pb-2">
                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="{{ route('user.profile', $post->user->id) }}" class="font-black text-sm sm:text-base text-black dark:text-white uppercase hover:underline hover:decoration-2 hover:underline-offset-2">
                                                    {{ $post->user->name }}
                                                </a>
                                                @if($post->user->is_verified)
                                                    <span class="bg-yellow-300 border border-black text-black text-[9px] px-1 font-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] flex items-center" title="Terverifikasi Admin">✓</span>
                                                @endif
                                                
                                                <!-- Menampilkan Topik ala Threads (Bisa Diklik!) -->
                                                @if($post->topic)
                                                    <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5 text-sm sm:text-base">›</span>
                                                    <a href="/topik/{{ $post->topic }}" class="font-bold text-sm sm:text-base text-blue-600 dark:text-blue-400 hover:underline decoration-2 uppercase transition-all hover:opacity-80">
                                                        {{ $post->topic }}
                                                    </a>
                                                @endif

                                                <span class="text-xs font-bold text-gray-500 dark:text-gray-500 ml-1">{{ $post->created_at->diffForHumans(null, true, true) }}</span>
                                            </div>
                                            @if ($post->user_id === auth()->id())
                                                <div class="flex gap-3">
                                                    <a href="{{ route('posts.edit', $post->id) }}" class="text-[10px] font-black uppercase text-black dark:text-white border-b-2 border-black dark:border-[#333333] hover:bg-yellow-300 dark:hover:bg-yellow-500 transition-colors">Edit</a>
                                                    <form action="{{ route('posts.destroy', $post->id) }}" method="POST" onsubmit="return confirm('Hapus utas ini?');" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-[10px] font-black uppercase text-red-500 border-b-2 border-red-500 hover:bg-red-200 transition-colors">Hapus</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                        <p class="text-black dark:text-white font-medium text-sm sm:text-base leading-relaxed whitespace-pre-wrap mb-3 mt-1">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-blue-600 dark:text-blue-400 font-bold hover:underline decoration-2">#$1</a>', e($post->content)) !!}</p>
                                        @if ($post->image)
                                            <div class="mb-3 border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] rounded-2xl overflow-hidden max-w-xl">
                                                <img src="{{ asset('storage/' . $post->image) }}" alt="Foto" class="w-full h-auto object-cover">
                                            </div>
                                        @endif
                                        
                                        <!-- ICON INTERAKSI ALA THREADS (4 Icon) -->
                                        <div class="flex items-center gap-4 sm:gap-6 mt-3 mb-2 text-black dark:text-white">
                                            <!-- Tombol Like -->
                                            <form action="{{ route('posts.like', $post->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="flex items-center gap-1.5 group transition-all">
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform {{ $post->isLikedBy(auth()->user()) ? 'text-red-500 fill-red-500' : 'text-black dark:text-white fill-none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                    </svg>
                                                    <span class="text-xs sm:text-sm font-bold {{ $post->isLikedBy(auth()->user()) ? 'text-red-500' : 'text-black dark:text-white' }}">{{ $post->likes->count() > 0 ? $post->likes->count() : '' }}</span>
                                                </button>
                                            </form>
                                            
                                            <!-- Tombol Komentar (@click showComments) -->
                                            <button @click="showComments = !showComments" type="button" class="flex items-center gap-1.5 group transition-all hover:text-gray-500 dark:hover:text-gray-400">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                                </svg>
                                                <span class="text-xs sm:text-sm font-bold">{{ $post->comments->count() > 0 ? $post->comments->count() : '' }}</span>
                                            </button>

                                            <!-- Tombol Repost (Visual) -->
                                            <button type="button" class="flex items-center gap-1.5 group transition-all hover:text-gray-500 dark:hover:text-gray-400">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>

                                            <!-- Tombol Share (Visual) -->
                                            <button type="button" class="flex items-center gap-1.5 group transition-all hover:text-gray-500 dark:hover:text-gray-400">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Area Komentar (X-SHOW DITAMBAHKAN DI SINI) -->
                                        <div x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="mt-4 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed"> 
                                            <div class="space-y-4 mb-3">
                                                @foreach ($post->comments->whereNull('parent_id') as $comment)
                                                    <div x-data="{ openReply: false, isSubmitting: false }" class="relative">
                                                        <div class="flex gap-3">
                                                            <div class="flex flex-col items-center flex-shrink-0">
                                                                @if ($comment->user->avatar)
                                                                    <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="Avatar" class="h-8 w-8 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                                                @else
                                                                    <div class="h-8 w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 dark:bg-cyan-500 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                                                        {{ substr($comment->user->name, 0, 1) }}
                                                                    </div>
                                                                @endif
                                                                <div class="w-1 bg-black dark:bg-[#333333] flex-1 mt-2 mb-1 min-h-[20px]" x-show="openReply || {{ $comment->replies->count() > 0 ? 'true' : 'false' }}"></div>
                                                            </div>
                                                            <div class="flex-1 min-w-0 pb-1">
                                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                                    <span class="font-black text-[13px] text-black dark:text-white uppercase hover:underline cursor-pointer">{{ $comment->user->name }}</span>
                                                                    @if($comment->user->is_verified)
                                                                        <span class="bg-yellow-300 border border-black text-black text-[8px] px-1 font-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] flex items-center" title="Terverifikasi Admin">✓</span>
                                                                    @endif
                                                                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 ml-1">{{ $comment->created_at->diffForHumans(null, true, true) }}</span>
                                                                </div>
                                                                <p class="text-black dark:text-white font-medium text-sm leading-relaxed">{{ $comment->content }}</p>
                                                                <button @click="openReply = !openReply" type="button" class="mt-1 text-[11px] font-black uppercase text-gray-500 dark:text-gray-400 hover:text-black dark:hover:text-white transition-colors">
                                                                    Balas
                                                                </button>
                                                                <!-- Perhatikan class w-full ditambahkan pada div dan form -->
																<div x-show="openReply" style="display: none;" class="mt-2 w-full">
																<form action="{{ route('comments.store', $post->id) }}" method="POST" class="w-full flex gap-2 items-center" @submit="isSubmitting = true">
																@csrf
																<input type="hidden" name="parent_id" value="{{ $comment->id }}">
        
																<!-- Tambahkan w-full di sini juga -->
																<input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-b-2 border-black dark:border-[#333333] focus:ring-0 focus:outline-none text-xs font-bold p-0 pb-1 text-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500" placeholder="Balas ke {{ $comment->user->name }}..." required>
        
																<button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] text-black font-black uppercase border-2 border-black dark:border-[#333333] bg-yellow-300 px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400 disabled:shadow-none">
																<span x-show="!isSubmitting">Kirim</span>
																<span x-show="isSubmitting">...</span>
																</button>
																</form>
																</div>
                                                                @if ($comment->replies->count() > 0)
                                                                    <div class="mt-3 space-y-3">
                                                                        @foreach ($comment->replies as $reply)
                                                                            <div class="flex gap-2">
                                                                                <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                                                                    @if ($reply->user->avatar)
                                                                                        <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="Avatar" class="h-6 w-6 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                                                                    @else
                                                                                        <div class="h-6 w-6 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-[10px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                                                                                            {{ substr($reply->user->name, 0, 1) }}
                                                                                        </div>
                                                                                    @endif
                                                                                    @if (!$loop->last)
                                                                                    <div class="w-0.5 bg-black dark:bg-[#333333] flex-1 mt-1 min-h-[10px]"></div>
                                                                                    @endif
                                                                                </div>
                                                                                <div class="flex-1 min-w-0 pb-1">
                                                                                    <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                                                        <span class="font-black text-xs text-black dark:text-white uppercase hover:underline cursor-pointer">{{ $reply->user->name }}</span>
                                                                                        @if($reply->user->is_verified)
                                                                                            <span class="bg-yellow-300 border border-black text-black text-[8px] px-1 font-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] flex items-center" title="Terverifikasi Admin">✓</span>
                                                                                        @endif
                                                                                        <span class="text-[9px] font-bold text-gray-500 dark:text-gray-400 ml-1">{{ $reply->created_at->diffForHumans(null, true, true) }}</span>
                                                                                    </div>
                                                                                    <p class="text-black dark:text-white font-medium text-sm leading-relaxed">{{ $reply->content }}</p>
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

                                            <!-- Tambahkan w-full dan min-w-0 pada div pembungkus utama -->
<div x-data="{ isSubmitting: false }" class="mt-2 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed flex gap-3 items-center w-full min-w-0">
    <div class="flex-shrink-0">
        @if (Auth::user()->avatar)
            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="h-8 w-8 rounded-full border-2 border-black dark:border-[#333333] object-cover shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
        @else
            <div class="h-8 w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 dark:bg-cyan-500 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)]">
                {{ substr(Auth::user()->name, 0, 1) }}
            </div>
        @endif
    </div>
    
    <!-- Tambahkan min-w-0 pada tag form -->
    <form action="{{ route('comments.store', $post->id) }}" method="POST" class="flex-1 flex gap-2 items-center min-w-0" @submit="isSubmitting = true">
        @csrf
        <!-- Tambahkan w-full pada tag input -->
        <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-none focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-sm placeholder-gray-400 dark:placeholder-gray-500" placeholder="Tambahkan komentar..." required>
        
        <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[11px] text-black font-black uppercase border-2 border-black dark:border-[#333333] bg-cyan-400 px-3 py-1 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400 disabled:shadow-none">
            <span x-show="!isSubmitting">Kirim</span>
            <span x-show="isSubmitting">...</span>
        </button>
    </form>
</div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                </div> <!-- PENUTUP WADAH KUNING RAKSASA -->

            </div>
        </div>
    </div>
</x-app-layout>