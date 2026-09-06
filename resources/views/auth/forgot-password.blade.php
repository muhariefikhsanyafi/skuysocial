<x-guest-layout>
    <!-- Teks Penjelasan -->
    <div class="mb-6 font-bold text-black text-sm sm:text-base leading-relaxed border-b-4 border-black pb-4">
        Lupa password? Santai saja. Masukkan alamat email kamu di bawah ini, dan kami akan mengirimkan tautan agar kamu bisa membuat password yang baru.
    </div>

    <!-- Pesan Sukses (Jika email berhasil dikirim) -->
    <x-auth-session-status class="mb-4 font-black text-emerald-600 p-3 border-4 border-emerald-600 bg-emerald-100" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <!-- Input Email -->
        <div>
            <label for="email" class="block font-black text-black uppercase text-sm mb-1">Email Kamu</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-white focus:bg-yellow-200 transition-colors">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 font-bold" />
        </div>

        <x-turnstile action="forgot_password" />

        <!-- Tombol Aksi -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6 pt-2">
            <!-- Link Kembali ke Login -->
            <a class="text-sm font-bold text-black hover:underline hover:decoration-4 hover:decoration-emerald-400 transition-all w-full sm:w-auto text-center" href="{{ route('login') }}">
                Batal & Kembali
            </a>

            <!-- Tombol Kirim Tautan -->
            <button type="submit" class="bg-yellow-300 border-4 border-black shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[6px] hover:translate-x-[6px] font-black uppercase px-6 py-3 text-black transition-all text-sm w-full sm:w-auto text-center">
                Kirim Tautan Reset
            </button>
        </div>
    </form>
</x-guest-layout>
