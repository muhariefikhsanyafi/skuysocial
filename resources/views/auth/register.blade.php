<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        
        <!-- Nama -->
        <div>
            <label for="name" class="block font-black text-black uppercase text-sm mb-1">Nama Keren Kamu</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-cyan-50 focus:bg-cyan-200 transition-colors">
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-600 font-bold" />
        </div>

		<!-- Username -->
		<div>
        <label for="username" class="block font-black text-black uppercase text-sm mb-1">Username <span class="text-xs text-gray-500 lowercase">(tanpa spasi)</span></label>
        <input id="username" type="text" name="username" value="{{ old('username') }}" required autocomplete="username" 
               class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none" 
               oninput="this.value = this.value.replace(/\s/g, '').toLowerCase()" 
               pattern="^[a-zA-Z0-9_.]+$" 
               title="Username tidak boleh menggunakan spasi" />
        <x-input-error :messages="$errors->get('username')" class="mt-2 text-red-600 font-bold" />
		</div>
	
        <!-- Email -->
        <div>
            <label for="email" class="block font-black text-black uppercase text-sm mb-1">Alamat Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-cyan-50 focus:bg-cyan-200 transition-colors">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 font-bold" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block font-black text-black uppercase text-sm mb-1">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-cyan-50 focus:bg-cyan-200 transition-colors">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-600 font-bold" />
        </div>

        <!-- Konfirmasi Password -->
        <div>
            <label for="password_confirmation" class="block font-black text-black uppercase text-sm mb-1">Ulangi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full border-4 border-black p-3 font-bold focus:ring-0 focus:outline-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-cyan-50 focus:bg-cyan-200 transition-colors">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-600 font-bold" />
        </div>

        <x-turnstile action="register" />

        <!-- Tombol Aksi -->
        <div class="flex items-center justify-between mt-8 pt-4 border-t-4 border-black">
            <a class="text-sm font-bold text-black hover:underline hover:decoration-4 hover:decoration-emerald-400 transition-all" href="{{ route('login') }}">
                Sudah punya akun?
            </a>

            <button type="submit" class="bg-emerald-400 border-4 border-black shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[6px] hover:translate-x-[6px] font-black uppercase px-8 py-3 text-black transition-all text-lg">
                Daftar!
            </button>
        </div>
    </form>
</x-guest-layout>
