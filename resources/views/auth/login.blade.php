<x-guest-layout>
    <!-- Pesan Error (Jika Ada) -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf
        
        <!-- Email -->
        <div>
            <label for="email" class="block font-black text-black uppercase text-sm mb-1">Email Kamu</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-yellow-50 focus:bg-yellow-200 transition-colors">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 font-bold" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block font-black text-black uppercase text-sm mb-1">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-yellow-50 focus:bg-yellow-200 transition-colors">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-600 font-bold" />
        </div>

        <!-- Tombol Aksi -->
        <div class="flex items-center justify-between mt-6">
            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-black hover:underline hover:decoration-4 hover:decoration-pink-400 transition-all" href="{{ route('password.request') }}">
                    Lupa password?
                </a>
            @endif

            <button type="submit" class="bg-pink-400 border-4 border-black shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[6px] hover:translate-x-[6px] font-black uppercase px-8 py-3 text-black transition-all text-lg">
                Masuk
            </button>
        </div>
        
        <!-- Link Daftar -->
        <div class="text-center mt-8 border-t-4 border-black pt-6">
            <p class="font-bold text-sm text-gray-700">Pendatang baru?</p>
            <a href="{{ route('register') }}" class="inline-block mt-3 bg-yellow-300 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] font-black uppercase px-6 py-3 text-black transition-all w-full text-center">
                Buat Akun Sekarang
            </a>
        </div>
    </form>
</x-guest-layout>