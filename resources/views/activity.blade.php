<x-app-layout>
    <div class="max-w-2xl mx-auto pb-10 pt-4 sm:pt-8 px-4 sm:px-0">
        <!-- Header Halaman -->
        <div class="mb-6 flex items-center gap-3 border-b-4 border-black pb-4 dark:border-[#333333]">
            <a href="{{ route('feed') }}" class="p-2 border-2 border-black bg-white hover:bg-yellow-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wider">Aktivitas Saya</h1>
        </div>

        <!-- Daftar Notifikasi -->
        <div class="space-y-4">

            <!-- Cek apakah data notifikasi kosong menggunakan IF standar -->
            @if($notifications->isEmpty())
                <!-- Tampilan jika tidak ada notifikasi -->
                <div class="p-8 border-4 border-black bg-white shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] text-center flex flex-col items-center justify-center">
                    <div class="text-6xl mb-4">📭</div>
                    <h2 class="text-xl font-black uppercase mb-2">Belum ada aktivitas</h2>
                    <p class="text-gray-600 font-bold">Notifikasi akan muncul di sini saat seseorang menyukai, membalas, atau mengikuti Anda.</p>
                </div>

            @else
                <!-- Lakukan perulangan jika ada data -->
                @foreach ($notifications as $notification)
                    @php
                        // Ambil data yang dikirim oleh kurir
                        $data = $notification->data;
                    @endphp

                    <div class="p-4 border-4 border-black bg-white shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] flex items-start gap-4">
                        
                        <!-- Avatar Pembuat Aktivitas -->
                        <a href="{{ route('user.profile', $data['username']) }}" class="flex-shrink-0">
                            <img src="{{ isset($data['avatar']) ? asset('storage/' . $data['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($data['name']).'&background=random&color=fff&font-weight=bold' }}"
                                 alt="{{ $data['name'] }}"
                                 class="w-12 h-12 rounded-full border-2 border-black object-cover bg-gray-200">
                        </a>

                        <!-- Isi Pesan Notifikasi -->
                        <div class="flex-1 pt-1">
                            <p class="text-sm sm:text-base text-black">
                                <a href="{{ route('user.profile', $data['username']) }}" class="font-black hover:underline hover:text-blue-600 transition-colors">
                                    {{ $data['name'] }}
                                </a>
                                {{ $data['message'] }}
                            </p>
                            <span class="text-xs text-gray-500 font-bold block mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>

                        <!-- Ikon Penanda Jenis Aktivitas -->
                        <div class="flex-shrink-0">
                            @if($data['type'] === 'like')
                                <div class="w-10 h-10 bg-pink-400 border-2 border-black flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" /></svg>
                                </div>
                            @elseif($data['type'] === 'comment')
                                <div class="w-10 h-10 bg-cyan-400 border-2 border-black flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" /></svg>
                                </div>
                            @elseif($data['type'] === 'follow')
                                <div class="w-10 h-10 bg-green-400 border-2 border-black flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 20 20"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" /></svg>
                                </div>
								@elseif($data['type'] === 'repost')
                                <div class="w-10 h-10 bg-emerald-400 border-2 border-black flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
