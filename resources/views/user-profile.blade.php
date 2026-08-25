<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-black pb-2">
            <h2 class="font-black text-2xl text-black uppercase tracking-widest">
                Profil: {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            <!-- WADAH PROFIL UTAMA -->
<div class="bg-white dark:bg-[#101010] border-4 border-black dark:border-[#333333] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-6 mb-8 transition-colors duration-300">

    <!-- 1. Header: Nama, Username, & Avatar -->
    <div class="flex justify-between items-start mb-4">
        <!-- Kiri: Info Nama -->
        <div class="flex-1 pr-4">
            <h1 class="text-2xl sm:text-3xl font-black text-black dark:text-white uppercase leading-tight tracking-tight">
                {{ $user->name }}
            </h1>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="text-base font-bold text-black dark:text-white lowercase">{{ $user->username }}</span>
                
                @if($user->is_verified)
                    <span class="bg-cyan-400 border-2 border-black text-black text-[10px] px-1 font-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]" title="Terverifikasi">✓</span>
                @endif
                
                <!-- Badge ala Threads (opsional) -->
                <span class="bg-gray-100 dark:bg-[#242424] border-2 border-black dark:border-[#333333] text-[10px] font-black px-2 py-0.5 rounded-full text-black dark:text-white shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-none">
                    skuysocial.net
                </span>
            </div>
        </div>

        <!-- Kanan: Avatar Raksasa -->
        <div class="flex-shrink-0 relative">
            @if ($user->avatar)
                <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" class="h-20 w-20 sm:h-24 sm:w-24 object-cover border-4 border-black dark:border-[#333333] rounded-full shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]">
            @else
                <div class="h-20 w-20 sm:h-24 sm:w-24 border-4 border-black dark:border-[#333333] bg-pink-400 dark:bg-pink-500 rounded-full flex items-center justify-center text-black font-black uppercase text-3xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]">
                    {{ substr($user->name, 0, 1) }}
                </div>
            @endif
        </div>
    </div>

    <!-- 2. Bio & Tautan -->
    <!-- Catatan: Ganti teks statis ini dengan variabel dari database jika Anda sudah membuat kolom 'bio' -->
    <div class="mb-5 text-sm sm:text-base font-medium text-black dark:text-white leading-relaxed">
        <p>🔔 Akun Utama <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline decoration-2 font-bold">@itsmearff</a></p>
        <p>🌏 Owner <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline decoration-2 font-bold">@kuysocial.id</a></p>
        <a href="https://kuysocial.com" target="_blank" class="inline-block mt-1 font-bold hover:underline decoration-2 text-black dark:text-white">kuysocial.com</a>
    </div>

    <!-- 3. Kategori / Tags Pills (Brutalist Style) -->
    <div class="flex flex-wrap gap-2 mb-5">
        <span class="bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-blue-600 dark:text-blue-400 hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Memes</span>
        <span class="bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-gray-500 hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Teknologi</span>
        <span class="bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-blue-600 dark:text-blue-400 hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Manchester United</span>
    </div>

    <!-- 4. Pengikut & Ikon Sosial -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-2 group cursor-pointer">
            <div class="flex -space-x-2">
                <div class="w-6 h-6 rounded-full border-2 border-black bg-yellow-300"></div>
                <div class="w-6 h-6 rounded-full border-2 border-black bg-cyan-400"></div>
            </div>
            <span class="text-sm font-bold text-gray-500 group-hover:underline decoration-2">23 pengikut</span>
        </div>
        <div class="flex gap-3 text-black dark:text-white">
            <!-- Ikon Instagram -->
            <svg class="w-6 h-6 hover:scale-110 transition-transform cursor-pointer" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 3h-8a5 5 0 0 0-5 5v8a5 5 0 0 0 5 5h8a5 5 0 0 0 5-5v-8a5 5 0 0 0-5-5z"></path><path d="M16 11.37a4 4 0 1 1-4.26-3.87 4 4 0 0 1 4.26 3.87z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
        </div>
    </div>

    <!-- 5. Tombol Aksi -->
    <div class="flex gap-3 mb-6">
        <button class="flex-1 bg-white dark:bg-[#242424] border-4 border-black dark:border-[#333333] py-2 font-black uppercase text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white">Edit Profil</button>
        <button class="flex-1 bg-white dark:bg-[#242424] border-4 border-black dark:border-[#333333] py-2 font-black uppercase text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-black dark:text-white md:hidden">Bagikan Profil</button>
    </div>

    <!-- 6. Navigasi Tab Ala Threads -->
    <div class="flex border-b-4 border-black dark:border-[#333333] overflow-x-auto snap-x [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm border-b-4 border-black dark:border-white -mb-1 text-black dark:text-white bg-yellow-300 dark:bg-[#333333] transition-colors">Threads</button>
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm text-gray-500 hover:text-black dark:hover:text-white transition-colors">Balasan</button>
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm text-gray-500 hover:text-black dark:hover:text-white transition-colors">Media</button>
    </div>

</div>
<!-- AKHIR WADAH PROFIL UTAMA -->

            <!-- Area Daftar Status User Tersebut -->
            <div class="space-y-10">
                @forelse ($posts as $post)
                    <div class="bg-white border-4 border-black shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-6">
                        
                        <!-- Header Postingan -->
                        <div class="flex items-center justify-between mb-5 border-b-4 border-black pb-4">
                            <div class="flex items-center">
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
                                    <p class="text-xl font-black text-black uppercase tracking-wide">{{ $post->user->name }}</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1 bg-gray-200 border-2 border-black inline-block px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] uppercase">
                                        {{ $post->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Tombol Edit & Hapus -->
                            @if ($post->user_id === auth()->id())
                                <div class="flex gap-2">
                                    <a href="{{ route('posts.edit', $post->id) }}" class="bg-yellow-300 border-2 border-black font-bold uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">Edit</a>
                                    <form action="{{ route('posts.destroy', $post->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus status ini?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="bg-red-400 text-black border-2 border-black font-black uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">Hapus</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        
                        <p class="text-black font-bold text-lg leading-relaxed whitespace-pre-wrap">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-blue-600 font-black hover:underline hover:bg-blue-100 px-1 transition-colors">#$1</a>', nl2br(e($post->content))) !!}</p>
						<!-- Tampilkan Gambar Jika Ada -->
                        @if ($post->image)
                            <div class="mt-4 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                                <img src="{{ asset('storage/' . $post->image) }}" alt="Foto dari {{ $post->user->name }}" class="w-full h-auto object-cover border-b-4 border-transparent">
                            </div>
                        @endif
                    </div>
                @empty
                    <!-- Tampilan Jika Belum Ada Postingan -->
                    <div class="bg-white border-4 border-black shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-10 text-center">
                        <p class="text-2xl font-black text-black uppercase">Belum ada postingan.</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>