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
            
            <!-- Kartu Header Profil Utama -->
<div class="bg-purple-300 border-4 border-black p-6 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8">
    
    <!-- Bagian 1: Avatar / Foto Profil -->
    <div class="flex-shrink-0">
        @if ($user->avatar)
            <!-- Tampilkan foto asli jika user sudah mengunggahnya -->
            <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-28 w-28 object-cover border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-white">
        @else
            <!-- Tampilkan inisial huruf jika belum ada foto -->
            <div class="h-28 w-28 border-4 border-black bg-white flex items-center justify-center text-black font-black uppercase text-5xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                {{ substr($user->name, 0, 1) }}
            </div>
        @endif
    </div>

    <!-- Bagian 2: Informasi Identitas & Bio -->
    <div class="flex flex-col text-center sm:text-left w-full">
        <!-- Nama -->
        <div class="font-black text-black uppercase text-3xl sm:text-4xl mb-2">{{ $user->name }}</div>
        
        <!-- Total Postingan -->
        <div class="inline-block bg-white border-2 border-black px-3 py-1 font-bold text-sm shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] mb-4 w-max mx-auto sm:mx-0">
            Total Postingan: {{ $user->posts->count() ?? 0 }}
        </div>

        <!-- Tampilkan Bio HANYA jika bio-nya tidak kosong -->
        @if ($user->bio)
            <div class="bg-yellow-200 border-4 border-black p-4 font-bold text-gray-900 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] mt-2 text-left">
                {{ $user->bio }}
            </div>
        @endif
    </div>
    
</div>

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