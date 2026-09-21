<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-[var(--skuy-background)] min-h-screen transition-colors duration-300">
        <div class="w-full max-w-2xl lg:max-w-3xl mx-auto px-2 sm:px-6">
            
            <!-- Header Kembali -->
            <div class="flex items-center gap-4 mb-6 px-2">
                <a href="{{ route('user.profile', $user->username) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="p-2 hover:bg-[var(--skuy-surface-subtle)] rounded-full transition-colors text-[var(--skuy-text-primary)] hover:text-[var(--skuy-secondary)]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="font-black text-xl text-[var(--skuy-text-primary)] uppercase">{{ __('profile.edit') }}</h1>
            </div>

            <!-- Form Edit Profil dengan Alpine.js untuk Preview Gambar -->
            <form action="{{ route('user.update') }}" method="POST" enctype="multipart/form-data" 
                  x-data="{ 
                      coverPreview: '{{ $user->cover ? asset('storage/' . $user->cover) : '' }}',
                      avatarPreview: '{{ $user->avatar ? asset('storage/' . $user->avatar) : '' }}',
                      updateCover(event) {
                          const file = event.target.files[0];
                          if (file) this.coverPreview = URL.createObjectURL(file);
                      },
                      updateAvatar(event) {
                          const file = event.target.files[0];
                          if (file) this.avatarPreview = URL.createObjectURL(file);
                      },
                      isSubmitting: false
                  }" 
                  @submit="isSubmitting = true"
                  class="bg-[var(--skuy-surface)] border-2 sm:border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] overflow-hidden relative">
                
                @csrf

                <!-- Area Edit Sampul (Cover) -->
                <div class="relative h-32 sm:h-48 bg-[var(--skuy-selected)] border-b-2 sm:border-b-4 border-[var(--skuy-border-strong)] group overflow-hidden">
                    <!-- Tampilan Cover -->
                    <template x-if="coverPreview">
                        <img :src="coverPreview" alt="{{ __('profile.media.cover_preview') }}" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!coverPreview">
                        <div class="w-full h-full flex items-center justify-center relative">
                            <div class="absolute -right-10 -top-10 text-9xl opacity-20 transform rotate-12">🚀</div>
                            <div class="absolute -left-10 -bottom-10 text-9xl opacity-20 transform -rotate-12">👾</div>
                        </div>
                    </template>
                    
                    <!-- Overlay Upload Hover -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer">
                        <button type="button" @click="$refs.coverInput.click()" aria-label="{{ __('settings.profile.change_cover') }}" class="cursor-pointer flex items-center gap-2 bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)] font-black uppercase text-xs px-4 py-2 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-[var(--skuy-secondary-hover)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ __('settings.profile.change_cover') }}
                        </button>
                        <input id="cover" x-ref="coverInput" type="file" name="cover" class="hidden" accept="image/*" @change="updateCover">
                    </div>
                </div>

                <!-- Area Bawah (Avatar & Input Teks) -->
                <div class="px-4 sm:px-6 pb-6 sm:pb-8 relative">
                    
                    <!-- Area Edit Avatar -->
                    <div class="flex justify-between items-end -mt-12 sm:-mt-16 mb-6 relative z-10">
                        <div class="relative group cursor-pointer">
                            <div class="flex-shrink-0">
                                <template x-if="avatarPreview">
                                    <img :src="avatarPreview" alt="{{ __('profile.media.avatar_preview') }}" class="h-24 w-24 sm:h-32 sm:w-32 object-cover border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] rounded-full shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] relative z-10">
                                </template>
                                <template x-if="!avatarPreview">
                                    <div class="h-24 w-24 sm:h-32 sm:w-32 border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] rounded-full flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-4xl sm:text-6xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] relative z-10">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                </template>
                            </div>
                            <!-- Overlay Upload Hover -->
                            <button type="button" @click="$refs.avatarInput.click()" aria-label="{{ __('settings.profile.change_avatar') }}" class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 rounded-full z-20 flex items-center justify-center cursor-pointer transition-opacity border-4 border-transparent">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>
                            <input id="avatar" x-ref="avatarInput" type="file" name="avatar" class="hidden" accept="image/*" @change="updateAvatar">
                        </div>
                    </div>

                    <!-- Pesan Error Validasi -->
                    @if ($errors->any())
                        <div class="mb-6 p-4 bg-[var(--skuy-surface)] border-2 border-[var(--skuy-danger)] shadow-[2px_2px_0px_0px_var(--skuy-danger)]">
                            <ul class="list-disc list-inside text-[var(--skuy-danger)] text-xs sm:text-sm font-black uppercase">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Input Nama Tampilan -->
                    <div class="mb-5">
                        <label for="name" class="block text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.profile.display_name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] transition-colors shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)]" required maxlength="50">
                    </div>

                    <!-- Input Username (Hanya Baca / Disabled) -->
                    <div class="mb-5">
                        <label for="username" class="block text-[var(--skuy-text-muted)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.profile.username_read_only') }}</label>
                        <input id="username" type="text" value="{{ '@' . $user->username }}" class="w-full bg-[var(--skuy-surface-subtle)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-muted)] font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)] cursor-not-allowed" disabled>
                    </div>

                    <!-- Input Bio -->
                    <div class="mb-8">
                        <label for="bio" class="block text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.profile.bio_about') }}</label>
                        <textarea id="bio" name="bio" rows="4" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] transition-colors shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)] resize-none" placeholder="{{ __('settings.profile.bio_placeholder') }}" maxlength="255">{{ old('bio', $user->bio) }}</textarea>
                        <p class="text-xs font-bold text-[var(--skuy-text-muted)] mt-2">*{{ __('settings.profile.bio_limit') }}</p>
                    </div>

                    <!-- Tombol Simpan -->
                    <div class="flex justify-end gap-3 pt-5 pb-4 border-t-2 border-dashed border-[var(--skuy-border-subtle)]">
                        <a href="{{ route('user.profile', $user->username) }}" class="px-5 sm:px-6 py-2.5 bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-[var(--skuy-surface-subtle)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
                            {{ __('settings.profile.cancel') }}
                        </a>
                        <button type="submit" :disabled="isSubmitting" class="px-5 sm:px-6 py-2.5 bg-[var(--skuy-brand)] hover:bg-[var(--skuy-brand-hover)] border-2 border-[var(--skuy-border-strong)] text-[var(--skuy-on-brand)] font-black uppercase text-xs sm:text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-[var(--skuy-text-muted)]">
                            <span x-show="!isSubmitting">{{ __('settings.profile.save_changes') }}</span>
                            <span x-show="isSubmitting">{{ __('settings.profile.saving') }}</span>
                        </button>
                    </div>

                </div>
            </form>
			
			<!-- ========================================== -->
            <!-- BLOK UBAH PASSWORD -->
            <!-- ========================================== -->
            <div class="mt-8 bg-[var(--skuy-surface)] border-2 sm:border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8">
                <h2 class="font-black text-xl sm:text-2xl text-[var(--skuy-text-primary)] uppercase mb-2">{{ __('settings.password.heading') }}</h2>
                <p class="font-bold text-sm text-[var(--skuy-text-secondary)] mb-6">{{ __('settings.password.description') }}</p>
                
                <!-- Kita arahkan ke rute bawaan Laravel Breeze -->
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    @method('put')
                    
                    <div class="mb-5">
                        <label class="block text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.password.current') }}</label>
                        <input type="password" name="current_password" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-[var(--skuy-on-danger)] bg-[var(--skuy-danger)] p-2 border-2 border-[var(--skuy-danger)] font-black text-xs uppercase" />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.password.new') }}</label>
                        <input type="password" name="password" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-[var(--skuy-on-danger)] bg-[var(--skuy-danger)] p-2 border-2 border-[var(--skuy-danger)] font-black text-xs uppercase" />
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-[var(--skuy-text-primary)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.password.confirmation') }}</label>
                        <input type="password" name="password_confirmation" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-[var(--skuy-on-danger)] bg-[var(--skuy-danger)] p-2 border-2 border-[var(--skuy-danger)] font-black text-xs uppercase" />
                    </div>
                    
                    <button type="submit" class="bg-[var(--skuy-brand)] text-[var(--skuy-on-brand)] px-6 py-3 font-black uppercase text-sm border-2 border-[var(--skuy-border-strong)] hover:bg-[var(--skuy-brand-hover)] hover:-translate-y-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all">
                        {{ __('settings.password.save') }}
                    </button>
                    
                    @if (session('status') === 'password-updated')
                        <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="inline-block ml-4 text-sm font-black uppercase text-[var(--skuy-on-secondary)] bg-[var(--skuy-secondary)] px-3 py-1 border-2 border-[var(--skuy-border-strong)] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                            {{ __('settings.password.saved') }}
                        </p>
                    @endif
                </form>
            </div>

            <!-- ========================================== -->
            <!-- BLOK HAPUS AKUN (DANGER) -->
            <!-- ========================================== -->
            <div class="mt-8 bg-[var(--skuy-danger)] border-2 sm:border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8">
                <h2 class="font-black text-xl sm:text-2xl text-[var(--skuy-on-danger)] uppercase mb-2">{{ __('settings.delete.heading') }}</h2>
                <p class="font-bold text-sm text-[var(--skuy-on-danger)] mb-6">{{ __('settings.delete.short_description') }}</p>
                
                <form action="{{ route('profile.destroy') }}" method="POST">
                    @csrf
                    @method('delete')
                    
                    <div class="mb-6">
                        <label class="block text-[var(--skuy-on-danger)] font-black uppercase text-xs sm:text-sm mb-2">{{ __('settings.delete.password') }}</label>
                        <input type="password" name="password" class="w-full bg-[var(--skuy-surface)] border-2 border-[var(--skuy-border-strong)] px-4 py-3 text-[var(--skuy-text-primary)] font-bold focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]" placeholder="{{ __('settings.delete.password_placeholder') }}" required>
                        <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-[var(--skuy-danger)] bg-[var(--skuy-surface)] p-2 border-2 border-[var(--skuy-danger)] font-black text-xs uppercase" />
                    </div>
                    
                    <button type="submit" data-confirm="{{ __('settings.delete.browser_confirmation') }}" onclick="return confirm(this.dataset.confirm)" class="bg-[var(--skuy-danger)] text-[var(--skuy-on-danger)] px-6 py-3 font-black uppercase text-sm border-2 border-[var(--skuy-on-danger)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:translate-y-[2px] hover:translate-x-[2px] hover:shadow-none transition-all">
                        {{ __('settings.delete.button') }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
