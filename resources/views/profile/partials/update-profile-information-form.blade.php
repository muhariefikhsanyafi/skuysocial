<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full border-4 border-black p-3 font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full border-4 border-black p-3 font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
		
		<!-- Input Bio Singkat -->
        <div>
            <x-input-label for="bio" :value="__('Bio Singkat')" class="font-black uppercase" />
            <textarea id="bio" name="bio" rows="3" class="mt-1 block w-full border-4 border-black p-3 font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:bg-yellow-50 resize-none" placeholder="Tulis sesuatu tentang dirimu...">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <!-- Input Avatar (Foto Profil) -->
        <div>
            <x-input-label for="avatar" :value="__('Foto Profil (Avatar)')" class="font-black uppercase mb-2" />
            
            <!-- Tampilkan foto lama jika ada -->
            @if ($user->avatar)
                <div class="mb-3">
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" class="h-24 w-24 object-cover border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                </div>
            @endif

            <input type="file" id="avatar" name="avatar" accept="image/*" class="block w-full text-sm text-black file:mr-4 file:py-2 file:px-4 file:border-4 file:border-black file:text-sm file:font-black file:uppercase file:bg-pink-300 file:text-black hover:file:bg-pink-400 file:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:file:shadow-none hover:file:translate-y-[4px] hover:file:translate-x-[4px] file:transition-all mb-1 cursor-pointer">
            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>
		
        <div class="flex items-center gap-4">
            <x-primary-button class="bg-blue-400 border-4 border-black font-black uppercase px-6 py-3 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all">
    {{ __('Simpan') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
