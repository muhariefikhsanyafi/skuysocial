<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-black pb-2">
            <h2 class="font-black text-2xl text-black uppercase tracking-widest">
                {{ __('Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Kartu Sambutan Brutal -->
            <div class="bg-yellow-300 border-4 border-black shadow-[12px_12px_0px_0px_rgba(0,0,0,1)] p-8 sm:p-16 text-center">
                
                <h3 class="text-4xl sm:text-6xl font-black text-black uppercase tracking-tighter mb-6 drop-shadow-[4px_4px_0px_rgba(255,255,255,1)]">
                    Halo, {{ auth()->user()->name }}!
                </h3>
                
                <p class="text-lg sm:text-2xl font-bold text-gray-900 mb-12 border-4 border-black bg-white inline-block px-6 py-3 shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                    Selamat datang di markas besar SkuySocial.
                </p>

                <!-- Tombol Pintasan Cepat -->
                <div class="flex flex-col sm:flex-row justify-center gap-6 mt-4">
                    
                    <!-- Pintasan ke Beranda -->
                    <a href="{{ route('feed') }}" class="bg-pink-400 border-4 border-black shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[6px] hover:translate-x-[6px] font-black uppercase px-8 py-4 text-black transition-all text-xl flex items-center justify-center gap-2">
                        <span>➔</span> Selami Beranda
                    </a>

                    <!-- Pintasan ke Profil -->
                    <a href="{{ route('user.profile', auth()->user()->id) }}" class="bg-cyan-400 border-4 border-black shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[6px] hover:translate-x-[6px] font-black uppercase px-8 py-4 text-black transition-all text-xl flex items-center justify-center gap-2">
                        <span>👤</span> Lihat Profilku
                    </a>
                    
                </div>
            </div>

        </div>
    </div>
</x-app-layout>