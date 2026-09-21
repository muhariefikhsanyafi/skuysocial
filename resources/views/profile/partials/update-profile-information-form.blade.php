<section>
    <header>
        <h2 class="text-lg font-medium text-[var(--skuy-text-primary)]">
            {{ __('settings.profile.heading') }}
        </h2>

        <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
            {{ __('settings.profile.description') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('settings.profile.name')" class="text-[var(--skuy-text-primary)]" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)]" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2 text-[var(--skuy-danger)]" :messages="$errors->get('name')" />
        </div>
		
		<!-- Username -->
    <div>
        <label for="username" class="block font-black text-[var(--skuy-text-primary)] uppercase text-sm mb-1">{{ __('settings.profile.username') }}</label>
        <input id="username" 
               name="username" 
               type="text" 
               class="w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]"
               value="{{ old('username', $user->username) }}" 
               required 
               autocomplete="username" 
               oninput="this.value = this.value.replace(/\s/g, '').toLowerCase()" 
               pattern="^[a-zA-Z0-9_.]+$" 
               title="{{ __('settings.profile.username_format') }}" />
        <x-input-error class="mt-2 text-[var(--skuy-danger)] font-bold" :messages="$errors->get('username')" />
    </div>
	
        <div>
            <x-input-label for="email" :value="__('settings.profile.email')" class="text-[var(--skuy-text-primary)]" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)]" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2 text-[var(--skuy-danger)]" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-[var(--skuy-text-secondary)]">
                        {{ __('settings.profile.email_unverified') }}

                        <button form="send-verification" class="underline text-sm text-[var(--skuy-secondary)] hover:text-[var(--skuy-secondary-hover)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2">
                            {{ __('settings.profile.resend_verification') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-[var(--skuy-secondary)]">
                            {{ __('settings.profile.verification_sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
		
		<!-- Input Bio Singkat -->
        <div>
            <x-input-label for="bio" :value="__('settings.profile.bio_short')" class="font-black uppercase text-[var(--skuy-text-primary)]" />
            <textarea id="bio" name="bio" rows="3" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:bg-[var(--skuy-selected)] focus:border-[var(--skuy-focus)] resize-none" placeholder="{{ __('settings.profile.bio_placeholder') }}">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2 text-[var(--skuy-danger)]" :messages="$errors->get('bio')" />
        </div>

        <!-- Input Avatar (Foto Profil) -->
        <div>
            <x-input-label for="avatar" :value="__('settings.profile.avatar')" class="font-black uppercase mb-2" />
            
            <!-- Tampilkan foto lama jika ada -->
            @if ($user->avatar)
                <div class="mb-3">
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-24 w-24 object-cover border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                </div>
            @endif

            <input type="file" id="avatar" name="avatar" accept="image/*" class="block w-full text-sm text-[var(--skuy-text-primary)] file:mr-4 file:py-2 file:px-4 file:border-4 file:border-[var(--skuy-border-strong)] file:text-sm file:font-black file:uppercase file:bg-[var(--skuy-secondary)] file:text-[var(--skuy-on-secondary)] hover:file:bg-[var(--skuy-secondary-hover)] file:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:file:shadow-none hover:file:translate-y-[4px] hover:file:translate-x-[4px] file:transition-all mb-1 cursor-pointer">
            <x-input-error class="mt-2 text-[var(--skuy-danger)]" :messages="$errors->get('avatar')" />
        </div>
		
        <div class="flex items-center gap-4">
            <x-primary-button class="bg-[var(--skuy-brand)] hover:bg-[var(--skuy-brand-hover)] border-4 border-[var(--skuy-border-strong)] text-[var(--skuy-on-brand)] font-black uppercase px-6 py-3 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all">
    {{ __('settings.profile.save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-[var(--skuy-secondary)]"
                >{{ __('settings.profile.saved') }}</p>
            @endif
        </div>
    </form>
</section>
