<x-app-layout>
    <div class="max-w-2xl mx-auto pb-10 pt-4 sm:pt-8 px-4 sm:px-0">

        <!-- Header -->
        <div class="mb-6 flex items-center gap-3 border-b-4 border-black pb-4 dark:border-[#333333]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wider">Cari Sesuatu?</h1>
        </div>

        <!-- Form Input Pencarian -->
        <form action="{{ route('search') }}" method="GET" class="mb-8 flex gap-2">
            <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Cari nama, username, atau bahasan..." autocomplete="off" class="w-full p-3 border-4 border-black text-lg font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:outline-none focus:bg-yellow-100 transition-colors">
            <button type="submit" class="px-6 bg-yellow-300 border-4 border-black font-black uppercase shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-400 hover:translate-y-1 hover:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] transition-all">
                Gas!
            </button>
        </form>

        <!-- AREA HASIL PENCARIAN -->
        @if(isset($query) && $query !== '')

            <!-- 1. Blok Hasil Akun (Orang) -->
            <div class="mb-10">
                <h2 class="text-lg font-black bg-cyan-300 border-4 border-black inline-block px-3 py-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] mb-4 uppercase">
                    👥 Akun Pengguna
                </h2>
                
                @if($users->isEmpty())
                    <p class="font-bold text-gray-500 italic">Tidak ada akun yang nyangkut.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($users as $user)
                            <a href="{{ route('user.profile', $user->username) }}" class="flex items-center gap-3 p-3 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:bg-cyan-100 transition-colors cursor-pointer">
                                <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=random&color=fff&font-weight=bold' }}" class="w-12 h-12 rounded-full border-2 border-black object-cover bg-gray-200">
                                <div>
                                    <p class="font-black text-black leading-tight">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-600 font-bold">{{ '@' . $user->username }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($hashtags->isNotEmpty())
                <!-- 2. Blok Hasil Tagar -->
                <div class="mb-10">
                    <h2 class="text-lg font-black bg-yellow-300 border-4 border-black inline-block px-3 py-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] mb-4 uppercase">
                        🏷️ Tagar
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($hashtags as $hashtag)
                            <a href="{{ route('tags.show', $hashtag->name) }}" class="block p-3 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-100 transition-colors cursor-pointer">
                                <p class="font-black text-black">#{{ $hashtag->name }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($topics->isNotEmpty())
                <!-- 3. Blok Hasil Topik -->
                <div class="mb-10">
                    <h2 class="text-lg font-black bg-emerald-300 border-4 border-black inline-block px-3 py-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] mb-4 uppercase">
                        🧵 Topik
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($topics as $topic)
                            <a href="{{ route('topik.show', ['topic' => $topic]) }}" class="block p-3 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-100 transition-colors cursor-pointer">
                                <p class="font-black text-black">{{ $topic }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 4. Blok Hasil Postingan -->
            <div>
                <h2 class="text-lg font-black bg-pink-400 text-white border-4 border-black inline-block px-3 py-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] mb-4 uppercase">
                    📝 Postingan
                </h2>

                @if($posts->isEmpty())
                    <p class="font-bold text-gray-500 italic">Tidak ada bahasan yang cocok dengan kata kunci ini.</p>
                @else
                    <div class="space-y-4">
                        @foreach($posts as $post)
                            <!-- DIBUNGKUS LINK AGAR BISA DIKLIK -->
                            <a href="{{ route('posts.show', $post) }}" class="block p-4 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:bg-yellow-100 hover:translate-x-1 transition-all cursor-pointer">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-black text-black hover:underline">{{ $post->user->name }}</span>
                                    <span class="text-xs text-gray-500 font-bold">&bull; {{ $post->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-black font-medium">{{ $post->content }}</p>
                            </a>
                        @endforeach
                    </div>

                    @if($posts->hasPages())
                        <div class="mt-6">
                            {{ $posts->links() }}
                        </div>
                    @endif
                @endif
            </div>

        @else
            <!-- Tampilan Awal Jika Belum Mencari Sesuatu -->
            <div class="p-8 border-4 border-black bg-white shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] text-center flex flex-col items-center">
                <div class="text-6xl mb-4">🕵️‍♂️</div>
                <h2 class="text-xl font-black uppercase mb-2">Mau stalking siapa hari ini?</h2>
                <p class="text-gray-600 font-bold">Ketik nama teman atau topik menarik di kolom atas, biar sistem KuySocial yang carikan buat Anda.</p>
            </div>
        @endif
        
    </div>
</x-app-layout>
