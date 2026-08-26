<x-app-layout>
    <!-- 1. Background utama (pt-8 dipangkas jadi pt-3 untuk HP) -->
    <div class="pt-3 sm:pt-12 pb-28 md:pb-12 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        
        <!-- 2. Wadah 3 Kolom (Padding kiri-kanan diperlebar jadi px-2 khusus di HP) -->
        <div class="w-full max-w-[1600px] mx-auto px-2 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between gap-0 md:gap-6 lg:gap-8 items-start min-h-screen">
            
            <!-- 3. SIDEBAR KIRI (mb-6 dihapus dari pembungkus utama) -->
            <div x-data="{ sidebarOpen: false }" @toggle-sidebar.window="sidebarOpen = !sidebarOpen" class="w-full md:w-52 lg:w-[250px] xl:w-[280px] flex-shrink-0 md:sticky md:top-28 z-20 md:mb-0">
                
                <!-- Menu Items (mb-5 dipindahkan ke dalam agar hanya memakan ruang SAAT menu dibuka) -->
                <div x-show="sidebarOpen" x-transition class="md:!flex flex-col gap-3 mb-5 md:mb-0" style="display: none;">
                    
                    <a href="{{ route('feed') }}" class="flex items-center gap-3 bg-yellow-300 border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black font-black uppercase text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Beranda
                    </a>

                    <a href="#" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-pink-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Cari
                    </a>

                    <a href="#" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-cyan-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        Aktivitas
                    </a>

                    <a href="{{ route('user.profile', auth()->user()->username) }}" class="flex items-center gap-3 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-3 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] hover:bg-emerald-400 hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white font-black uppercase text-sm lg:text-base group">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Profil
                    </a>
                    
                </div>
            </div>


            <!-- ========================================== -->
            <!-- 2. AREA KONTEN TENGAH (Feed) -->
            <!-- ========================================== -->
            <div class="flex-1 w-full max-w-2xl lg:max-w-3xl xl:max-w-4xl mx-auto">
                
                <!-- WADAH KUNING RAKSASA (Transparan khusus di HP agar postingan melebar) -->
                <div class="bg-transparent sm:bg-yellow-300 dark:bg-transparent sm:dark:bg-[#181818] border-0 sm:border-4 border-black dark:border-[#333333] shadow-none sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-0 sm:p-6 lg:p-8 transition-colors duration-300">
                    
                    <!-- Form Input Utas -->
                    <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-4 sm:p-5 mb-6 sm:mb-8 transition-colors duration-300">
                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" x-data="{ isSubmitting: false, content: '' }" @submit="isSubmitting = true">
                            @csrf
                            <div class="flex gap-3 sm:gap-4">
                                <div class="flex-shrink-0">
                                    @if (Auth::user()->avatar)
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="Avatar" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
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
                                        class="font-black text-sm text-black dark:text-white mb-1 flex items-center gap-1.5 flex-wrap">
                                        
                                        {{ Auth::user()->username }}
                                        @if(Auth::user()->is_verified)
                                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] cursor-help" title="Terverifikasi">
                                                <svg class="w-2 h-2 sm:w-2.5 sm:h-2.5 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                            </span>
                                        @endif
                                        
                                        <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5">›</span>
                                        
                                        <div class="relative">
                                            <input type="text" name="topic" x-model="topicQuery" @focus="showSuggestions = true" @click.away="showSuggestions = false" class="bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-sm placeholder-gray-500 w-28 sm:w-48" placeholder="Topik..." maxlength="30" autocomplete="off">
                                            <div x-show="showSuggestions && filteredTopics.length > 0" style="display: none;" class="absolute top-full left-0 mt-2 w-48 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] z-50">
                                                <template x-for="t in filteredTopics" :key="t">
                                                    <div @click="selectTopic(t)" class="px-3 py-2 border-b-2 last:border-b-0 border-black dark:border-[#333333] hover:bg-yellow-300 dark:hover:bg-[#242424] cursor-pointer font-bold text-sm text-black dark:text-white transition-colors" x-text="t"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea name="content" rows="2" x-model="content" class="w-full bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-black dark:text-white font-medium text-base sm:text-lg placeholder-gray-500 resize-none" placeholder="Mulai utas baru..." required></textarea>
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
									<div class="flex flex-col sm:flex-row sm:items-center justify-between mt-3 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed gap-3">
                                        <div class="flex-1 overflow-hidden">
                                            <input type="file" name="images[]" multiple accept="image/*" class="text-xs w-full file:mr-2 file:py-1 file:px-2 file:border-2 file:border-black dark:file:border-[#333333] file:bg-yellow-300 file:text-black file:font-black file:uppercase file:cursor-pointer file:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:file:shadow-none hover:file:translate-y-[2px] transition-all">
                                        </div>
                                        <button type="submit" :disabled="isSubmitting || content.trim() === ''" :class="(isSubmitting || content.trim() === '') ? 'bg-gray-200 dark:bg-[#242424] text-gray-500 shadow-none cursor-not-allowed' : 'bg-cyan-400 text-black hover:shadow-none hover:translate-y-[2px] hover:translate-x-[2px] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]'" class="border-2 border-black dark:border-[#333333] transition-all font-black uppercase px-5 sm:px-6 py-1.5 flex-shrink-0 text-sm">
                                            <span x-show="!isSubmitting">Posting</span>
                                            <span x-show="isSubmitting">...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Area Daftar Status / Timeline -->
                    <div class="space-y-5 sm:space-y-6">
                        @foreach ($posts as $post)
                            <div x-data="{ showComments: false }" class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 transition-colors duration-300">
                                <div class="flex gap-3 sm:gap-4">
                                    
                                    <div class="flex flex-col items-center flex-shrink-0">
                                        <a href="{{ route('user.profile', $post->user->username) }}">
                                            @if ($post->user->avatar)
                                                <img src="{{ asset('storage/' . $post->user->avatar) }}" alt="Avatar" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none transition-all">
                                            @else
                                                <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-base sm:text-lg shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none transition-all">
                                                    {{ substr($post->user->name, 0, 1) }}
                                                </div>
                                            @endif
                                        </a>
                                        <div class="w-1 sm:w-1.5 bg-black dark:bg-[#333333] flex-1 mt-2 sm:mt-3 min-h-[30px]"></div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0 pb-1 sm:pb-2">
                                        
                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="{{ route('user.profile', $post->user->username) }}" class="font-black text-sm sm:text-base text-black dark:text-white hover:underline hover:decoration-2 hover:underline-offset-2">
                                                    {{ $post->user->username }}
                                                </a>
                                                
                                                @if($post->user->is_verified)
                                                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)] ml-0.5 cursor-help transition-transform hover:scale-110" title="Terverifikasi">
                                                        <svg class="w-2 h-2 sm:w-2.5 sm:h-2.5 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                    </span>
                                                @endif
                                                
                                                @if($post->topic)
                                                    <span class="text-gray-400 dark:text-gray-500 font-bold mx-0.5 text-xs sm:text-base">›</span>
                                                    <a href="/topik/{{ $post->topic }}" class="font-bold text-xs sm:text-base text-blue-600 dark:text-blue-400 hover:underline uppercase transition-all">
                                                        {{ $post->topic }}
                                                    </a>
                                                @endif

                                                <span class="text-[11px] sm:text-xs font-bold text-gray-500 dark:text-gray-500 ml-1">{{ $post->created_at->diffForHumans(null, true, true) }}</span>
                                            </div>
                                            
                                            @if ($post->user_id === auth()->id())
                                                <div x-data="{ openOptions: false }" class="relative">
                                                    <button @click="openOptions = !openOptions" type="button" class="p-1 flex items-center justify-center border-2 border-transparent hover:border-black dark:hover:border-[#333333] hover:bg-yellow-300 dark:hover:bg-[#242424] transition-all text-black dark:text-white rounded-sm">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle>
                                                        </svg>
                                                    </button>
                                                    <div x-show="openOptions" @click.away="openOptions = false" style="display: none;" class="absolute right-0 mt-1 sm:mt-2 w-40 sm:w-44 bg-white dark:bg-[#101010] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] z-50 flex flex-col">
                                                        <a href="{{ route('posts.edit', $post->id) }}" class="px-3 py-2.5 text-[11px] sm:text-xs font-black uppercase text-black dark:text-white border-b-2 border-black dark:border-[#333333] hover:bg-cyan-400 transition-colors flex items-center gap-2">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                            Ubah
                                                        </a>
                                                        <form action="{{ route('posts.destroy', $post->id) }}" method="POST" onsubmit="return confirm('Hapus utas ini secara permanen?');" class="w-full m-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full text-left px-3 py-2.5 text-[11px] sm:text-xs font-black uppercase text-red-600 hover:bg-pink-400 hover:text-black transition-colors flex items-center gap-2">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Teks Postingan diperbesar -->
                                        <p class="text-black dark:text-white font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-3 mt-1.5">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-blue-600 dark:text-blue-400 font-bold hover:underline decoration-2">#$1</a>', e($post->content)) !!}</p>
                                        
                                        @if ($post->images && count($post->images) > 0)
                                            <div x-data="{ 
                                                    openImage: false, 
                                                    activeImage: '',
                                                    activeSlide: 0,
                                                    updateDots(e) {
                                                        let maxIndex = {{ count($post->images) - 1 }};
                                                        if (maxIndex === 0) return;
                                                        let maxScroll = e.target.scrollWidth - e.target.clientWidth;
                                                        if (maxScroll === 0) return;
                                                        this.activeSlide = Math.round((e.target.scrollLeft / maxScroll) * maxIndex);
                                                    }
                                                 }" class="mb-3">
                                                
                                                <!-- Jarak gap diperkecil (gap-1.5) agar sambungan gambar terasa lebih rapat dan menyatu -->
                                                <div @scroll.passive="updateDots" class="flex gap-1.5 sm:gap-2 overflow-x-auto snap-x snap-mandatory pb-3 pt-1 w-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                                    @foreach ($post->images as $imgUrl)
                                                        <!-- Ukuran dipaksa seragam dengan 'aspect-[4/5]' & bisa berdampingan pas di desktop dengan 'sm:w-[48%]' -->
                                                        <div @click="openImage = true; activeImage = '{{ asset('storage/' . $imgUrl) }}'" class="snap-center shrink-0 w-[85%] sm:w-[48%] aspect-[4/5] cursor-pointer border-2 sm:border-4 border-black dark:border-[#333333] shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] sm:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] rounded-xl sm:rounded-2xl overflow-hidden group transition-all hover:translate-y-[-2px]">
                                                            <!-- h-full & object-cover akan memastikan gambar mengisi seluruh kotak seragam tanpa merusak rasio aslinya -->
                                                            <img src="{{ asset('storage/' . $imgUrl) }}" alt="Foto" class="w-full h-full object-cover group-hover:opacity-85 transition-opacity">
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <!-- INDIKATOR TITIK-TITIK (DOTS) -->
                                                @if (count($post->images) > 1)
                                                    <div class="flex justify-center items-center gap-1.5 mt-1 sm:mt-2 mb-2">
                                                        @foreach ($post->images as $index => $img)
                                                            <div class="h-1.5 sm:h-2 rounded-full border border-black dark:border-[#333333] transition-all duration-300"
                                                                 :class="activeSlide === {{ $index }} ? 'w-4 sm:w-5 bg-black dark:bg-white' : 'w-1.5 sm:w-2 bg-gray-300 dark:bg-[#242424]'">
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-8 bg-black/90 backdrop-blur-sm" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                                                    <button @click="openImage = false" class="absolute top-4 right-4 sm:top-8 sm:right-8 text-white hover:text-yellow-400 transition-colors z-[110] bg-black/50 rounded-full p-1 border-2 border-transparent hover:border-white">
                                                        <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                    <img @click.away="openImage = false" :src="activeImage" alt="Foto Penuh" class="max-w-full max-h-[90vh] object-contain border-2 sm:border-4 border-white shadow-[4px_4px_0px_0px_rgba(255,255,255,1)] sm:shadow-[8px_8px_0px_0px_rgba(255,255,255,1)]">
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <!-- ICON INTERAKSI diperbesar -->
                                        <div class="flex items-center gap-2 sm:gap-3 mt-3 sm:mt-4 mb-2 flex-wrap">
                                            <form action="{{ route('posts.like', $post->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $post->isLikedBy(auth()->user()) ? 'bg-pink-400 text-black' : 'bg-white dark:bg-[#101010] text-black dark:text-white hover:bg-pink-300' }}">
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 {{ $post->isLikedBy(auth()->user()) ? 'fill-black' : 'fill-none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                                    <span class="text-xs sm:text-sm font-black">{{ $post->likes->count() > 0 ? $post->likes->count() : '' }}</span>
                                                </button>
                                            </form>
                                            <button @click="showComments = !showComments" type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] bg-white dark:bg-[#101010] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all hover:bg-cyan-300 text-black dark:text-white group">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                <span class="text-xs sm:text-sm font-black">{{ $post->comments->count() > 0 ? $post->comments->count() : '' }}</span>
                                            </button>
                                            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] bg-white dark:bg-[#101010] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all hover:bg-emerald-300 text-black dark:text-white group">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            </button>
                                            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] bg-white dark:bg-[#101010] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all hover:bg-yellow-300 text-black dark:text-white group">
                                                <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                            </button>
                                        </div>

                                        <!-- Area Komentar -->
                                        <div x-show="showComments" x-transition.opacity.duration.300ms style="display: none;" class="mt-4 pt-3 border-t-2 border-black dark:border-[#333333] border-dashed"> 
                                            <div class="space-y-4 mb-3">
                                                @foreach ($post->comments->whereNull('parent_id') as $comment)
                                                    <div x-data="{ openReply: false, isSubmitting: false }" class="relative">
                                                        <div class="flex gap-3">
                                                            <div class="flex flex-col items-center flex-shrink-0">
                                                                @if ($comment->user->avatar)
                                                                    <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="Avatar" class="h-8 w-8 sm:h-8 sm:w-8 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                @else
                                                                    <div class="h-8 w-8 sm:h-8 sm:w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 dark:bg-cyan-500 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                                        {{ substr($comment->user->name, 0, 1) }}
                                                                    </div>
                                                                @endif
                                                                <div class="w-0.5 sm:w-1 bg-black dark:bg-[#333333] flex-1 mt-1.5 sm:mt-2 mb-1 min-h-[16px]" x-show="openReply || {{ $comment->replies->count() > 0 ? 'true' : 'false' }}"></div>
                                                            </div>
                                                            <div class="flex-1 min-w-0 pb-1">
                                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                                    <span class="font-black text-[12px] sm:text-[13px] text-black dark:text-white hover:underline cursor-pointer">{{ $comment->user->username }}</span>
                                                                    @if($comment->user->is_verified)
                                                                        <span class="inline-flex items-center justify-center w-3 h-3 sm:w-3 sm:h-3 rounded-full bg-blue-500 border-2 border-black shadow-[1px_1px_0px_0px_rgba(0,0,0,1)]" title="Terverifikasi">
                                                                            <svg class="w-2 h-2 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                                        </span>
                                                                    @endif
                                                                    <span class="text-[10px] font-bold text-gray-500 ml-1">{{ $comment->created_at->diffForHumans(null, true, true) }}</span>
                                                                </div>
                                                                <p class="text-black dark:text-white font-medium text-[13px] sm:text-sm leading-relaxed">{{ $comment->content }}</p>
                                                                <button @click="openReply = !openReply" type="button" class="mt-1 text-[11px] font-black uppercase text-gray-500 hover:text-black transition-colors">Balas</button>
                                                                
                                                                <div x-show="openReply" style="display: none;" class="mt-2 w-full">
                                                                    <form action="{{ route('comments.store', $post->id) }}" method="POST" class="w-full flex gap-2 items-center" @submit="isSubmitting = true">
                                                                        @csrf
                                                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                                        <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-b-2 border-black dark:border-[#333333] focus:ring-0 focus:outline-none text-[11px] sm:text-xs font-bold p-0 pb-1 text-black dark:text-white placeholder-gray-400" placeholder="Balas ke {{ $comment->user->name }}..." required>
                                                                        <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] text-black font-black uppercase border-2 border-black bg-yellow-300 px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
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
                                                                                        <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="Avatar" class="h-6 w-6 sm:h-6 sm:w-6 object-cover border-2 border-black dark:border-[#333333] rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
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
                                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="h-8 w-8 rounded-full border-2 border-black dark:border-[#333333] object-cover shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                    @else
                                                        <div class="h-8 w-8 border-2 border-black dark:border-[#333333] bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                                            {{ substr(Auth::user()->name, 0, 1) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <form action="{{ route('comments.store', $post->id) }}" method="POST" class="flex-1 flex gap-2 items-center min-w-0" @submit="isSubmitting = true">
                                                    @csrf
                                                    <input type="text" name="content" class="flex-1 min-w-0 w-full bg-transparent border-none focus:ring-0 focus:outline-none text-black dark:text-white font-bold text-xs sm:text-sm placeholder-gray-400" placeholder="Tambahkan komentar..." required>
                                                    <button type="submit" :disabled="isSubmitting" class="flex-shrink-0 text-[10px] sm:text-[11px] text-black font-black uppercase border-2 border-black bg-cyan-400 px-3 py-1 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
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


            <!-- ========================================== -->
            <!-- 3. SIDEBAR KANAN (Widget Tambahan) -->
            <!-- ========================================== -->
            <!-- Hanya muncul di layar Desktop menengah ke atas (xl:flex) -->
            <div class="hidden xl:flex flex-col w-[280px] flex-shrink-0 md:sticky md:top-28 z-20 gap-6">
                
                <!-- Widget 1: Topik Hangat -->
                <div class="bg-white dark:bg-[#181818] border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] p-5 transition-colors">
                    <h3 class="font-black text-black dark:text-white uppercase text-base border-b-4 border-black dark:border-[#333333] pb-2 mb-4 flex items-center gap-2">
                        <span>🔥</span> Sedang Hangat
                    </h3>
                    <ul class="space-y-4">
                        <li>
                            <a href="#" class="block hover:bg-yellow-300 dark:hover:bg-[#333333] p-2 -mx-2 transition-colors group border-2 border-transparent hover:border-black dark:hover:border-transparent">
                                <p class="font-black text-sm text-black dark:text-white group-hover:text-black">#Makassar</p>
                                <p class="font-bold text-xs text-gray-500">Populer saat ini</p>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="block hover:bg-cyan-400 dark:hover:bg-[#333333] p-2 -mx-2 transition-colors group border-2 border-transparent hover:border-black dark:hover:border-transparent">
                                <p class="font-black text-sm text-black dark:text-white group-hover:text-black">#SkuySocial</p>
                                <p class="font-bold text-xs text-gray-500">Pembaruan Web</p>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="block hover:bg-pink-400 dark:hover:bg-[#333333] p-2 -mx-2 transition-colors group border-2 border-transparent hover:border-black dark:hover:border-transparent">
                                <p class="font-black text-sm text-black dark:text-white group-hover:text-black">Neo Brutalisme</p>
                                <p class="font-bold text-xs text-gray-500">Gaya Desain</p>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Widget 2: Kartu Dukungan -->
                <div class="bg-pink-400 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] p-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 text-6xl opacity-20 transform rotate-12">🚀</div>
                    <h3 class="font-black text-black uppercase text-lg mb-2 relative z-10">SkuySocial V.2</h3>
                    <p class="font-bold text-sm text-black mb-5 leading-tight relative z-10">Mari berteman dan dukung terus komunitas ini agar makin solid dan ramai!</p>
                    <a href="{{ route('user.profile', auth()->user()->username) }}" class="block text-center w-full bg-white border-2 border-black font-black uppercase text-sm py-2 hover:bg-yellow-300 hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] transition-all text-black relative z-10">
                        Lihat Profilku
                    </a>
                </div>

            </div>

        </div>
    </div>
	
	<!-- BOTTOM NAVIGATION BAR (HANYA MUNCUL DI HP) -->
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white dark:bg-[#101010] border-t-4 border-black dark:border-[#333333] flex justify-around items-center p-3 z-50 pb-safe">
        
        <!-- Beranda -->
        <a href="{{ route('feed') }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3l8 6v12h-5v-7h-6v7H4V9l8-6z"></path></svg>
        </a>

        <!-- Pencarian -->
        <a href="#" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </a>

        <!-- Buat Thread / Postingan (Tombol Tengah Mencolok) -->
        <button @click="window.scrollTo({top: 0, behavior: 'smooth'})" class="bg-yellow-300 border-2 border-black p-2.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
        </button>

        <!-- Aktivitas / Notifikasi -->
        <a href="#" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </a>

        <!-- Profil (BUGS FIXED DI SINI) -->
        <a href="{{ route('user.profile', auth()->user()->username) }}" class="text-black dark:text-white p-2 hover:opacity-70 transition-opacity">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </a>

    </div>
</x-app-layout>