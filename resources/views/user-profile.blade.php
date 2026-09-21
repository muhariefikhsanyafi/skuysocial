<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-[var(--skuy-border-strong)] pb-2">
            <h2 class="font-black text-2xl text-[var(--skuy-text-primary)] uppercase tracking-widest">
                Profil: {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-[var(--skuy-background)] min-h-screen">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            <!-- WADAH PROFIL UTAMA -->
<div class="bg-[var(--skuy-surface)]  border-4 border-[var(--skuy-border-strong)]  shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-6 mb-8 transition-colors duration-300">

    <!-- 1. Header: Nama, Username, & Avatar -->
    <div class="flex justify-between items-start mb-4">
        <!-- Kiri: Info Nama -->
        <div class="flex-1 pr-4">
            <h1 class="text-2xl sm:text-3xl font-black text-[var(--skuy-text-primary)]  uppercase leading-tight tracking-tight">
                {{ $user->name }}
            </h1>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="text-base font-bold text-[var(--skuy-text-primary)]  lowercase">{{ $user->username }}</span>
                
                @if($user->is_verified)
                    <span class="bg-[var(--skuy-secondary)] border-2 border-[var(--skuy-border-strong)] text-[var(--skuy-on-secondary)] text-[10px] px-1 font-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]" title="Terverifikasi">✓</span>
                @endif
                
                <!-- Badge ala Threads (opsional) -->
                <span class="bg-[var(--skuy-surface-subtle)] dark:bg-[var(--skuy-surface-elevated)] border-2 border-[var(--skuy-border-strong)]  text-[10px] font-black px-2 py-0.5 rounded-full text-[var(--skuy-text-primary)]  shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-none">
                    skuysocial.net
                </span>
            </div>
        </div>

        <!-- Kanan: Avatar Raksasa -->
        <div class="flex-shrink-0 relative">
            @if ($user->avatar)
                <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" class="h-20 w-20 sm:h-24 sm:w-24 object-cover border-4 border-[var(--skuy-border-strong)]  rounded-full shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]">
            @else
                <div class="h-20 w-20 sm:h-24 sm:w-24 border-4 border-[var(--skuy-border-strong)]  bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-3xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]">
                    {{ substr($user->name, 0, 1) }}
                </div>
            @endif
        </div>
    </div>

    <!-- 2. Bio & Tautan -->
    <!-- Catatan: Ganti teks statis ini dengan variabel dari database jika Anda sudah membuat kolom 'bio' -->
    <div class="mb-5 text-sm sm:text-base font-medium text-[var(--skuy-text-primary)]  leading-relaxed">
        <p>🔔 Akun Utama <a href="#" class="text-[var(--skuy-secondary)] hover:underline decoration-2 font-bold">@itsmearff</a></p>
        <p>🌏 Owner <a href="#" class="text-[var(--skuy-secondary)] hover:underline decoration-2 font-bold">@kuysocial.id</a></p>
        <a href="https://kuysocial.com" target="_blank" class="inline-block mt-1 font-bold hover:underline decoration-2 text-[var(--skuy-secondary)]">kuysocial.com</a>
    </div>

    <!-- 3. Kategori / Tags Pills (Brutalist Style) -->
    <div class="flex flex-wrap gap-2 mb-5">
        <span class="bg-[var(--skuy-surface)]  border-2 border-[var(--skuy-border-strong)]  px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-[var(--skuy-secondary)] hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Memes</span>
        <span class="bg-[var(--skuy-surface)]  border-2 border-[var(--skuy-border-strong)]  px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-[var(--skuy-text-muted)] hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Teknologi</span>
        <span class="bg-[var(--skuy-surface)]  border-2 border-[var(--skuy-border-strong)]  px-3 py-1 text-xs font-black uppercase shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-[var(--skuy-secondary)] hover:translate-x-[1px] hover:translate-y-[1px] hover:shadow-none cursor-pointer transition-all">Manchester United</span>
    </div>

    <!-- 4. Pengikut & Ikon Sosial -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-2 group cursor-pointer">
            <div class="flex -space-x-2">
                <div class="w-6 h-6 rounded-full border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)]"></div>
                <div class="w-6 h-6 rounded-full border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)]"></div>
            </div>
            <span class="text-sm font-bold text-[var(--skuy-text-muted)] group-hover:underline decoration-2">23 pengikut</span>
        </div>
        <div class="flex gap-3 text-[var(--skuy-text-primary)] ">
            <!-- Ikon Instagram -->
            <svg class="w-6 h-6 hover:scale-110 transition-transform cursor-pointer" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 3h-8a5 5 0 0 0-5 5v8a5 5 0 0 0 5 5h8a5 5 0 0 0 5-5v-8a5 5 0 0 0-5-5z"></path><path d="M16 11.37a4 4 0 1 1-4.26-3.87 4 4 0 0 1 4.26 3.87z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
        </div>
    </div>

    <!-- 5. Tombol Aksi -->
    <div class="flex gap-3 mb-6">
        <button class="flex-1 bg-[var(--skuy-surface)] dark:bg-[var(--skuy-surface-elevated)] border-4 border-[var(--skuy-border-strong)]  py-2 font-black uppercase text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-[var(--skuy-text-primary)] ">Edit Profil</button>
        <button class="flex-1 bg-[var(--skuy-surface)] dark:bg-[var(--skuy-surface-elevated)] border-4 border-[var(--skuy-border-strong)]  py-2 font-black uppercase text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all text-[var(--skuy-text-primary)]  md:hidden">Bagikan Profil</button>
    </div>

    <!-- 6. Navigasi Tab Ala Threads -->
    <div class="flex border-b-4 border-[var(--skuy-border-strong)]  overflow-x-auto snap-x [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm border-b-4 border-[var(--skuy-secondary)] -mb-1 text-[var(--skuy-on-selected)] bg-[var(--skuy-selected)] transition-colors">Threads</button>
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm text-[var(--skuy-text-muted)] hover:text-[var(--skuy-secondary)] transition-colors">Balasan</button>
        <button class="snap-start flex-1 min-w-[100px] py-3 font-black uppercase text-sm text-[var(--skuy-text-muted)] hover:text-[var(--skuy-secondary)] transition-colors">Media</button>
    </div>

</div>
<!-- AKHIR WADAH PROFIL UTAMA -->

            <!-- Area Daftar Status User Tersebut -->
            <div class="space-y-10">
                @forelse ($posts as $post)
                    <div class="bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-6">
                        
                        <!-- Header Postingan -->
                        <div class="flex items-center justify-between mb-5 border-b-4 border-[var(--skuy-border-strong)] pb-4">
                            <div class="flex items-center">
                                @if ($post->user->avatar)
    <!-- Munculkan foto jika user tersebut punya foto -->
    <img src="{{ asset('storage/' . $post->user->avatar) }}" alt="{{ $post->user->name }}" class="h-14 w-14 object-cover border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-[var(--skuy-surface)]">
@else
    <!-- Munculkan inisial jika user tersebut belum punya foto -->
    <div class="h-14 w-14 border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-2xl">
        {{ substr($post->user->name, 0, 1) }}
    </div>
@endif
                                <div class="ml-4">
                                    <p class="text-xl font-black text-[var(--skuy-text-primary)] uppercase tracking-wide">{{ $post->user->name }}</p>
                                    <p class="text-xs font-bold text-[var(--skuy-text-secondary)] mt-1 bg-[var(--skuy-surface-subtle)] border-2 border-[var(--skuy-border-strong)] inline-block px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] uppercase">
                                        {{ $post->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Tombol Edit & Hapus -->
                            @if ($post->user_id === auth()->id())
                                <div class="flex gap-2">
                                    <a href="{{ route('posts.edit', $post->uid) }}" class="bg-[var(--skuy-brand)] text-[var(--skuy-on-brand)] hover:bg-[var(--skuy-brand-hover)] border-2 border-[var(--skuy-border-strong)] font-bold uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">Edit</a>
                                    <form action="{{ route('posts.destroy', $post->uid) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus status ini?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="bg-[var(--skuy-danger)] text-[var(--skuy-on-danger)] border-2 border-[var(--skuy-border-strong)] font-black uppercase px-3 py-1 text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">Hapus</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        
                        <p class="text-[var(--skuy-text-primary)] font-bold text-lg leading-relaxed whitespace-pre-wrap">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="text-[var(--skuy-secondary)] font-black hover:underline hover:bg-[var(--skuy-selected)] px-1 transition-colors">#$1</a>', nl2br(e($post->content))) !!}</p>
						<!-- Tampilkan Gambar Jika Ada -->
                        @if ($post->image)
                            <div class="mt-4 border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                                <img src="{{ asset('storage/' . $post->image) }}" alt="Foto dari {{ $post->user->name }}" class="w-full h-auto object-cover border-b-4 border-transparent">
                            </div>
                        @endif
                    </div>
                @empty
                    <!-- Tampilan Jika Belum Ada Postingan -->
                    <div class="bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-10 text-center">
                        <p class="text-2xl font-black text-[var(--skuy-text-primary)] uppercase">Belum ada postingan.</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
