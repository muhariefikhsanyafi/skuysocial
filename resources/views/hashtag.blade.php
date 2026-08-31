<x-app-layout>
    <x-slot name="header">
        <div></div>
    </x-slot>

    <!-- Background dibuat sedikit abu-abu agar elemen yang cerah makin menonjol -->
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">
            
            <!-- Papan Nama Tagar -->
    <div class="bg-blue-300 border-4 border-black p-6 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] mb-12 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-4xl font-black uppercase text-black">
                Menjelajahi Tagar
            </h1>
            <p class="font-bold text-gray-900 mt-1">Ditemukan {{ $posts->count() }} postingan mantul.</p>
        </div>
        <div class="bg-black text-white border-4 border-white px-4 py-2 font-black text-2xl sm:text-3xl shadow-[4px_4px_0px_0px_rgba(255,255,0,1)] rotate-[-2deg]">
            #{{ $hashtag->name }}
        </div>
    </div>

            <!-- Area Daftar Status / Timeline -->
            <div class="space-y-10">
                @foreach ($posts as $post)
                    <div class="bg-white border-4 border-black shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-6">
                        
                        <!-- Header Postingan -->
                        <div class="flex items-center justify-between mb-5 border-b-4 border-black pb-4">
                            <div class="flex items-center">
                                <!-- Avatar Kotak -->
                                <!-- Avatar Kotak Pada Postingan -->
            @if ($post->user->avatar)
                <!-- Munculkan foto jika user tersebut punya foto -->
                <img src="{{ asset('storage/' . $post->user->avatar) }}" alt="{{ $post->user->name }}" class="h-14 w-14 object-cover border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-white">
            @else
                <!-- Munculkan inisial jika user tersebut belum punya foto -->
                <div class="h-14 w-14 border-4 border-black bg-pink-400 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] flex items-center justify-center text-black font-black uppercase text-2xl">
                    {{ substr($post->user->name, 0, 1) }}
                </div>
            @endif
                                <div class="ml-4">
                                    <a href="{{ route('user.profile', $post->user->username) }}" class="text-xl font-black text-black uppercase tracking-wide hover:underline hover:decoration-yellow-400 hover:decoration-4 transition-all">
    {{ $post->user->name }}
	</a>
                                    <p class="text-xs font-bold text-gray-800 mt-1 bg-gray-200 border-2 border-black inline-block px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] uppercase">
                                        {{ $post->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>

                            <!-- Tombol Aksi (Hanya muncul untuk pemilik postingan) -->
                            @if ($post->user_id === auth()->id())
                                <div class="flex gap-2">
                                    <!-- Tombol Edit -->
                                    <a href="{{ route('posts.edit', $post->uid) }}" class="bg-yellow-300 border-2 border-black font-bold uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">
                                        Edit
                                    </a>
                                    <!-- Tombol Hapus -->
                                    <form action="{{ route('posts.destroy', $post->uid) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus status ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-400 text-black border-2 border-black font-black uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Isi Konten Postingan -->
                        <p class="text-black font-bold text-lg leading-relaxed whitespace-pre-wrap">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="/tags/$1" class="text-blue-600 font-black hover:underline hover:bg-blue-100 px-1 transition-colors">#$1</a>', nl2br(e($post->content))) !!}</p>
						<!-- Tampilkan Gambar Jika Ada -->
                        @if ($post->image)
                            <div class="mt-4 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                                <img src="{{ asset('storage/' . $post->image) }}" alt="Foto dari {{ $post->user->name }}" class="w-full h-auto object-cover border-b-4 border-transparent">
                            </div>
                        @endif
						<!-- Tombol Interaksi -->
                        <div class="mt-6 pt-4 border-t-4 border-black flex items-center">
                            <form action="{{ route('posts.like', $post->uid) }}" method="POST">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 px-4 py-2 border-4 border-black font-black uppercase transition-all shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[4px] hover:translate-y-[4px] {{ $post->isLikedBy(auth()->user()) ? 'bg-pink-400 text-black' : 'bg-white text-black' }}">
                                    <!-- Ikon Hati -->
                                    <svg class="w-6 h-6" fill="{{ $post->isLikedBy(auth()->user()) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="square" stroke-linejoin="miter" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                    <!-- Jumlah Suka -->
                                    <span>{{ $post->likes->count() }} Suka</span>
                                </button>
                            </form>
                        </div>
						<!-- Area Komentar -->
                        <div class="mt-4 pt-4 border-t-4 border-black">
                            
                            <!-- Daftar Komentar Sebelumnya -->
                            <!-- Daftar Komentar Sebelumnya (Threaded) -->
<div class="space-y-4 mb-4">
    <!-- Hanya ambil komentar utama (yang tidak punya parent_id) -->
    @foreach ($post->comments->whereNull('parent_id') as $comment)
        
        <!-- Bungkus dengan Alpine.js untuk fitur buka-tutup form balasan -->
        <div x-data="{ openReply: false }" class="flex flex-col gap-2">
            
            <!-- 1. Kotak Komentar Utama -->
            <div class="bg-cyan-100 border-2 border-black p-3 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] flex flex-col">
                <div class="flex justify-between items-center mb-1 border-b-2 border-black pb-1">
                    <span class="font-black text-xs uppercase">{{ $comment->user->name }}</span>
                    <span class="text-[10px] font-bold text-gray-700 uppercase">{{ $comment->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-black font-bold text-sm mt-1">{{ $comment->content }}</p>
                
                <!-- Tombol Balas -->
                <div class="mt-2 text-right">
                    <button @click="openReply = !openReply" type="button" class="text-[10px] font-black uppercase bg-yellow-300 border-2 border-black px-2 py-1 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">
                        Balas
                    </button>
                </div>
            </div>

            <!-- 2. Form Input Balasan (Tersembunyi secara default) -->
            <div x-show="openReply" style="display: none;" class="ml-6 sm:ml-10">
                <form action="{{ route('comments.store', $post->uid) }}" method="POST" class="flex gap-2">
                    @csrf
                    <!-- Input rahasia untuk mengirimkan ID komentar yang dibalas -->
                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                    
                    <input type="text" name="content" class="flex-1 bg-white border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:bg-yellow-50 text-black font-bold text-xs p-2" placeholder="Balas {{ $comment->user->name }}..." required>
                    <button type="submit" class="bg-emerald-400 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[2px] hover:translate-x-[2px] transition-all font-black uppercase text-xs px-3 py-1 text-black">
                        Kirim
                    </button>
                </form>
            </div>

            <!-- 3. Daftar Balasan (Anak Komentar) -->
            @if ($comment->replies->count() > 0)
                <div class="ml-6 sm:ml-10 flex flex-col gap-2 border-l-4 border-black pl-3 mt-1">
                    @foreach ($comment->replies as $reply)
                        <div class="bg-pink-200 border-2 border-black p-2 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] flex flex-col">
                            <div class="flex justify-between items-center mb-1 border-b-2 border-black pb-1">
                                <span class="font-black text-[11px] uppercase">{{ $reply->user->name }}</span>
                                <span class="text-[9px] font-bold text-gray-700 uppercase">{{ $reply->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-black font-bold text-xs mt-1">{{ $reply->content }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    @endforeach
</div>

                            <!-- Form Input Komentar Baru -->
                            <form action="{{ route('comments.store', $post->uid) }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="text" name="content" class="flex-1 bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none p-2 font-bold text-black text-sm" placeholder="Balas postingan ini..." required autocomplete="off">
                                <button type="submit" class="bg-emerald-400 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] font-black uppercase px-4 py-2 text-black transition-all text-sm">
                                    Kirim
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>
