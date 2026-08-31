<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        <div class="w-full max-w-2xl lg:max-w-3xl xl:max-w-4xl mx-auto px-2 sm:px-6">
            
            <!-- Header Kembali -->
            <div class="flex items-center gap-4 mb-4 px-2">
                <a href="{{ route('feed') }}" class="p-2 hover:bg-gray-200 dark:hover:bg-[#242424] rounded-full transition-colors text-black dark:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="font-black text-xl text-black dark:text-white uppercase">{{ $user->username }}</h1>
            </div>

            <!-- KARTU PROFIL RAKSASA -->
            <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] mb-8 overflow-hidden relative">
                
                <!-- Sampul (Cover) Dinamis -->
<div class="h-32 sm:h-48 bg-pink-400 border-b-2 sm:border-b-4 border-black dark:border-[#333333] relative overflow-hidden flex items-center justify-center">
    @if ($user->cover)
        <img src="{{ asset('storage/' . $user->cover) }}" class="absolute inset-0 w-full h-full object-cover">
    @else
        <div class="absolute -right-10 -top-10 text-9xl opacity-20 transform rotate-12">🚀</div>
        <div class="absolute -left-10 -bottom-10 text-9xl opacity-20 transform -rotate-12">👾</div>
    @endif
</div>

                <!-- Avatar & Tombol Aksi -->
                <div class="px-4 sm:px-6 pb-6 sm:pb-8 relative">
                    <div class="flex justify-between items-end -mt-12 sm:-mt-16 mb-4 relative z-10">
                        <!-- Avatar -->
                        <div class="flex-shrink-0">
                            @if ($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" class="h-24 w-24 sm:h-32 sm:w-32 object-cover border-4 border-black bg-white rounded-full shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                            @else
                                <div class="h-24 w-24 sm:h-32 sm:w-32 border-4 border-black bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-4xl sm:text-6xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                            @endif
                        </div>

                        <!-- Tombol Ikuti / Edit Profil -->
                        <div class="mb-2 sm:mb-4">
                            @if(auth()->id() === $user->id)
                                <a href="{{ route('user.edit') }}" class="inline-block px-4 py-2 sm:px-6 sm:py-2.5 bg-yellow-300 border-2 border-black font-black uppercase text-xs sm:text-sm shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black">
                                    Edit Profil
                                </a>
                            @else
                                <form action="{{ route('user.follow', $user->username) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 sm:px-6 sm:py-2.5 border-2 border-black font-black uppercase text-xs sm:text-sm shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $isFollowing ? 'bg-white text-black' : 'bg-black text-white hover:bg-gray-800' }}">
                                        {{ $isFollowing ? 'Mengikuti' : 'Ikuti' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <!-- Info User -->
                    <div class="mb-4">
                        <h2 class="font-black text-2xl sm:text-3xl uppercase text-black dark:text-white tracking-tight flex items-center gap-2">
                            {{ $user->name }}
                            @if($user->is_verified)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-500 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]" title="Terverifikasi">
                                    <svg class="w-3 h-3 text-white stroke-[4px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </span>
                            @endif
                        </h2>
                        <p class="font-bold text-gray-500 text-sm sm:text-base">{{ '@' }}{{ $user->username }}</p>
                    </div>

                    <p class="font-medium text-black dark:text-white text-sm sm:text-base leading-relaxed mb-6">
                        Menjelajah dunia persosmedan dengan gaya Neo-Brutalisme. Selamat datang di profil saya!
                    </p>

                    <!-- Statistik Follower -->
                    <div class="flex items-center gap-6 pt-4 border-t-2 border-dashed border-gray-300 dark:border-[#333333]">
                        <div class="flex flex-col">
                            <span class="font-black text-2xl text-black dark:text-white">{{ $followingsCount }}</span>
                            <span class="font-bold text-xs uppercase text-gray-500 tracking-wider">Mengikuti</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-black text-2xl text-black dark:text-white">{{ $followersCount }}</span>
                            <span class="font-bold text-xs uppercase text-gray-500 tracking-wider">Pengikut</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB MENU (Utas / Balasan) -->
            <div class="flex w-full border-b-4 border-black dark:border-[#333333] mb-6">
                <div class="flex-1 text-center pb-3 border-b-4 border-black dark:border-white -mb-1 relative z-10 cursor-pointer">
                    <span class="font-black uppercase text-black dark:text-white tracking-wider">Utas</span>
                </div>
                <div class="flex-1 text-center pb-3 text-gray-400 hover:text-black dark:hover:text-white cursor-pointer transition-colors">
                    <span class="font-black uppercase tracking-wider">Suka</span>
                </div>
            </div>

            <!-- AREA DAFTAR UTAS (Menggunakan gaya yang sama dengan Feed) -->
            <div class="space-y-5 sm:space-y-6">
                @if($posts->count() > 0)
                    @foreach ($posts as $post)
                        @php
                            $isRepost = $post->repost_id !== null;
                            $displayPost = $isRepost ? $post->originalPost : $post;
                        @endphp
                        
                        <div class="cursor-pointer hover:bg-gray-50 dark:hover:bg-[#141414] bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 transition-colors duration-300" @click="window.location='{{ route('posts.show', $displayPost->uid) }}'">
                            
                            @if($isRepost)
                                <div class="flex items-center gap-2 mb-3 text-gray-500 dark:text-gray-400 font-black text-[11px] uppercase tracking-wider">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <span>{{ $post->user->username }} memposting ulang</span>
                                </div>
                            @endif

                            <div class="flex gap-3 sm:gap-4">
                                <div class="flex flex-col items-center flex-shrink-0">
                                    @if ($displayPost->user->avatar)
                                        <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black rounded-full shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                    @else
                                        <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black bg-pink-400 rounded-full flex items-center justify-center text-black font-black uppercase text-base sm:text-lg shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                                            {{ substr($displayPost->user->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                        <span class="font-black text-sm sm:text-base text-black dark:text-white">{{ $displayPost->user->username }}</span>
                                        <span class="text-[11px] sm:text-xs font-bold text-gray-500 ml-1">{{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}</span>
                                    </div>
                                    
                                    <p class="text-black dark:text-white font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-3 mt-1.5">{{ $displayPost->content }}</p>
                                    
                                    @if ($displayPost->images && count($displayPost->images) > 0)
                                        <div class="mb-3 text-sm font-bold text-blue-500">
                                            [Berisi {{ count($displayPost->images) }} Gambar - Klik untuk melihat]
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-12 border-4 border-dashed border-gray-300 dark:border-[#333333] bg-white dark:bg-[#101010]">
                        <div class="text-6xl mb-4 opacity-50">👻</div>
                        <h3 class="font-black text-xl uppercase text-gray-500">Belum ada utas</h3>
                        <p class="font-bold text-gray-400 text-sm mt-2">Pengguna ini belum memposting apapun.</p>
                    </div>
                @endif
            </div>

            @if ($posts->hasPages())
                <div class="mt-6">
                    {{ $posts->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
