<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start gap-3">
            <a href="{{ route('user.profile', $user->username) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] px-3 text-[var(--skuy-secondary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] hover:text-[var(--skuy-secondary-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                <x-fa-icon name="arrow-left-long" class="text-xl" />
            </a>
            <div>
                <h2 class="text-2xl font-bold text-[var(--skuy-text-primary)]">
                    {{ __('profile.edit') }}
                </h2>
                <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
                    {{ __('settings.profile.description') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="skuy-profile-page">
        <div class="skuy-profile-shell space-y-6">
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
                  class="skuy-settings-panel skuy-settings-panel--flush">
                @csrf

                <div class="group relative h-24 overflow-hidden border-b border-[var(--skuy-border-subtle)] bg-[var(--skuy-selected)] sm:h-32">
                    <template x-if="coverPreview">
                        <img :src="coverPreview" alt="{{ __('profile.media.cover_preview') }}" class="absolute inset-0 h-full w-full object-cover">
                    </template>
                    <template x-if="!coverPreview">
                        <div class="flex h-full w-full items-center justify-center bg-[var(--skuy-selected)] text-[var(--skuy-on-selected)]" aria-hidden="true">
                            <x-fa-icon name="camera" class="text-[40px]" />
                        </div>
                    </template>

                    <div class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100" style="background: color-mix(in srgb, var(--skuy-text-primary) 50%, transparent);">
                        <x-primary-button type="button" @click="$refs.coverInput.click()" aria-label="{{ __('settings.profile.change_cover') }}" class="cursor-pointer gap-2">
                            <x-fa-icon name="camera" class="text-base" />
                            {{ __('settings.profile.change_cover') }}
                        </x-primary-button>
                        <input id="cover" x-ref="coverInput" type="file" name="cover" class="hidden" accept="image/*" @change="updateCover">
                    </div>
                </div>

                <div class="relative px-4 pb-6 sm:px-6">
                    <div class="relative z-10 -mt-10 mb-6 flex items-end justify-between sm:-mt-12">
                        <div class="group relative cursor-pointer">
                            <div class="flex-shrink-0">
                                <template x-if="avatarPreview">
                                    <img :src="avatarPreview" alt="{{ __('profile.media.avatar_preview') }}" class="relative z-10 h-20 w-20 rounded-full border-2 border-[var(--skuy-surface)] bg-[var(--skuy-surface)] object-cover ring-1 ring-[var(--skuy-border-subtle)] sm:h-24 sm:w-24">
                                </template>
                                <template x-if="!avatarPreview">
                                    <div class="relative z-10 flex h-20 w-20 items-center justify-center rounded-full border-2 border-[var(--skuy-surface)] bg-[var(--skuy-selected)] text-3xl font-bold text-[var(--skuy-on-selected)] ring-1 ring-[var(--skuy-border-subtle)] sm:h-24 sm:w-24 sm:text-4xl">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                </template>
                            </div>

                            <button type="button" @click="$refs.avatarInput.click()" aria-label="{{ __('settings.profile.change_avatar') }}" class="absolute inset-0 z-20 inline-flex items-center justify-center rounded-full border-0 text-[var(--skuy-on-secondary)] opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100 focus-visible:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]" style="background: color-mix(in srgb, var(--skuy-text-primary) 50%, transparent);">
                                <x-fa-icon name="camera" weight="solid" class="text-[28px]" />
                            </button>
                            <input id="avatar" x-ref="avatarInput" type="file" name="avatar" class="hidden" accept="image/*" @change="updateAvatar">
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="mb-6 rounded-[var(--radius-small)] border border-[var(--skuy-danger)] bg-[var(--skuy-surface-subtle)] p-3" role="alert">
                            <ul class="space-y-1 text-sm font-semibold text-[var(--skuy-danger)]">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-2">
                        <label for="name" class="block text-sm font-semibold leading-5 text-[var(--skuy-text-primary)]">{{ __('settings.profile.display_name') }}</label>
                        <x-text-input id="name" type="text" name="name" class="mt-1" :value="old('name', $user->name)" required maxlength="50" />
                    </div>

                    <div class="mt-6 space-y-2">
                        <label for="username" class="block text-sm font-semibold leading-5 text-[var(--skuy-text-secondary)]">{{ __('settings.profile.username_read_only') }}</label>
                        <x-text-input id="username" type="text" value="{{ '@' . $user->username }}" class="mt-1 cursor-not-allowed bg-[var(--skuy-surface-subtle)] text-[var(--skuy-text-muted)]" disabled />
                    </div>

                    <div class="mt-6 space-y-2">
                        <label for="bio" class="block text-sm font-semibold leading-5 text-[var(--skuy-text-primary)]">{{ __('settings.profile.bio_about') }}</label>
                        <textarea id="bio" name="bio" rows="4" class="mt-1 block min-h-[136px] w-full resize-y rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-3 text-[var(--skuy-text-primary)] placeholder:text-[var(--skuy-text-muted)] focus:border-[var(--skuy-focus)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)]" placeholder="{{ __('settings.profile.bio_placeholder') }}" maxlength="255">{{ old('bio', $user->bio) }}</textarea>
                        <p class="text-xs font-medium text-[var(--skuy-text-muted)]">{{ __('settings.profile.bio_limit') }}</p>
                    </div>

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-[var(--skuy-border-subtle)] pt-6 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('user.profile', $user->username) }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-4 py-3 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                            {{ __('settings.profile.cancel') }}
                        </a>
                        <x-primary-button type="submit" x-bind:disabled="isSubmitting">
                            <span x-show="!isSubmitting">{{ __('settings.profile.save_changes') }}</span>
                            <span x-show="isSubmitting">{{ __('settings.profile.saving') }}</span>
                        </x-primary-button>
                    </div>
                </div>
            </form>

            <section class="skuy-settings-panel">
                <header>
                    <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
                        {{ __('settings.password.heading') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
                        {{ __('settings.password.description') }}
                    </p>
                </header>

                <form action="{{ route('password.update') }}" method="POST" class="mt-6 space-y-5">
                    @csrf
                    @method('put')

                    <div class="space-y-2">
                        <x-input-label for="custom_current_password" :value="__('settings.password.current')" />
                        <x-text-input id="custom_current_password" type="password" name="current_password" class="mt-1" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="custom_new_password" :value="__('settings.password.new')" />
                        <x-text-input id="custom_new_password" type="password" name="password" class="mt-1" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="custom_password_confirmation" :value="__('settings.password.confirmation')" />
                        <x-text-input id="custom_password_confirmation" type="password" name="password_confirmation" class="mt-1" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-primary-button>
                            {{ __('settings.password.save') }}
                        </x-primary-button>

                        @if (session('status') === 'password-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                                {{ __('settings.password.saved') }}
                            </p>
                        @endif
                    </div>
                </form>
            </section>

            <section class="skuy-settings-panel">
                <header>
                    <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('settings.two_factor.heading') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.two_factor.description') }}</p>
                </header>
                <a href="{{ route('security.two-factor.show') }}" class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] px-4 py-3 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                    {{ __('settings.two_factor.manage') }}
                </a>
            </section>

            <section class="skuy-settings-panel skuy-settings-panel--danger">
                <header>
                    <h2 class="text-xl font-bold text-[var(--skuy-danger)]">
                        {{ __('settings.delete.heading') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
                        {{ __('settings.delete.short_description') }}
                    </p>
                </header>

                <form action="{{ route('profile.destroy') }}" method="POST" class="mt-6 space-y-5">
                    @csrf
                    @method('delete')

                    <div class="space-y-2">
                        <x-input-label for="custom_delete_password" :value="__('settings.delete.password')" />
                        <x-text-input id="custom_delete_password" type="password" name="password" class="mt-1" placeholder="{{ __('settings.delete.password_placeholder') }}" required />
                        <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                    </div>

                    <x-danger-button type="submit" data-confirm="{{ __('settings.delete.browser_confirmation') }}" onclick="return confirm(this.dataset.confirm)">
                        {{ __('settings.delete.button') }}
                    </x-danger-button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
