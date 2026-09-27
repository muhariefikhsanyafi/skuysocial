<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start gap-3">
            <a href="{{ route('user.profile', $user->username) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] px-3 text-[var(--skuy-secondary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] hover:text-[var(--skuy-secondary-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                <x-fa-icon name="arrow-left-long" class="text-xl" />
            </a>
            <div>
                <h2 class="text-2xl font-bold text-[var(--skuy-text-primary)]">
                    {{ __('settings.profile.heading') }}
                </h2>
                <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
                    {{ __('settings.profile.description') }}
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $profileLinksForForm = old('profile_links_present')
            ? old('profile_links', [])
            : $user->profileLinks->map(fn ($link) => ['label' => $link->label, 'url' => $link->url])->values()->all();
        $profileLinksForForm = is_array($profileLinksForForm) ? array_values($profileLinksForForm) : [];
    @endphp

    <div class="skuy-profile-page">
        <div class="skuy-profile-shell space-y-6">
            <form action="{{ route('user.update') }}" method="POST" enctype="multipart/form-data"
                  x-data="{
                      coverPreview: '{{ $user->cover ? asset('storage/' . $user->cover) : '' }}',
                      avatarPreview: '{{ $user->avatar ? asset('storage/' . $user->avatar) : '' }}',
                      profileLinks: @js($profileLinksForForm),
                      maxProfileLinks: 5,
                      addProfileLink() {
                          if (this.profileLinks.length < this.maxProfileLinks) {
                              this.profileLinks.push({ label: '', url: '' });
                          }
                      },
                      removeProfileLink(index) {
                          this.profileLinks.splice(index, 1);
                      },
                      moveProfileLink(index, offset) {
                          const target = index + offset;
                          if (target < 0 || target >= this.profileLinks.length) return;

                          const [link] = this.profileLinks.splice(index, 1);
                          this.profileLinks.splice(target, 0, link);
                      },
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
                <input type="hidden" name="profile_links_present" value="1">

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

                    <div class="mt-8 border-t border-[var(--skuy-border-subtle)] pt-6" aria-labelledby="profile-links-heading">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 id="profile-links-heading" class="text-lg font-bold text-[var(--skuy-text-primary)]">{{ __('settings.profile.links_heading') }}</h2>
                                <p class="mt-1 text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.profile.links_description') }}</p>
                            </div>
                            <button type="button" @click="addProfileLink" x-bind:disabled="profileLinks.length >= maxProfileLinks" class="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-4 py-2 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] disabled:cursor-not-allowed disabled:text-[var(--skuy-text-muted)]">
                                <x-fa-icon name="plus" class="text-base" />
                                {{ __('settings.profile.add_link') }}
                            </button>
                        </div>

                        <p class="mt-3 text-xs font-medium text-[var(--skuy-text-muted)]">{{ __('settings.profile.links_limit', ['count' => 5]) }}</p>

                        <p x-show="profileLinks.length === 0" class="mt-4 text-sm text-[var(--skuy-text-secondary)]" role="status">
                            {{ __('settings.profile.no_links') }}
                        </p>

                        <div class="mt-4 space-y-4">
                            <template x-for="(link, index) in profileLinks" :key="index">
                                <div class="grid min-w-0 gap-4 rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface-subtle)] p-4 sm:grid-cols-2">
                                    <div class="min-w-0 space-y-2">
                                        <label x-bind:for="`profile-link-label-${index}`" class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.profile.link_label') }}</label>
                                        <input x-bind:id="`profile-link-label-${index}`" x-bind:name="`profile_links[${index}][label]`" x-model="link.label" type="text" maxlength="50" required class="block min-h-[44px] w-full min-w-0 rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-2 text-[var(--skuy-text-primary)] placeholder:text-[var(--skuy-text-muted)] focus:border-[var(--skuy-focus)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)]" placeholder="{{ __('settings.profile.link_label_placeholder') }}">
                                    </div>
                                    <div class="min-w-0 space-y-2">
                                        <label x-bind:for="`profile-link-url-${index}`" class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.profile.link_url') }}</label>
                                        <input x-bind:id="`profile-link-url-${index}`" x-bind:name="`profile_links[${index}][url]`" x-model="link.url" type="url" maxlength="2048" required class="block min-h-[44px] w-full min-w-0 rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-2 text-[var(--skuy-text-primary)] placeholder:text-[var(--skuy-text-muted)] focus:border-[var(--skuy-focus)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)]" placeholder="{{ __('settings.profile.link_url_placeholder') }}">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" @click="moveProfileLink(index, -1)" x-bind:disabled="index === 0" class="inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] px-3 text-sm font-semibold text-[var(--skuy-text-secondary)] underline underline-offset-4 transition-colors hover:text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] disabled:cursor-not-allowed disabled:text-[var(--skuy-text-muted)]">
                                                {{ __('settings.profile.move_link_up') }}
                                            </button>
                                            <button type="button" @click="moveProfileLink(index, 1)" x-bind:disabled="index === profileLinks.length - 1" class="inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] px-3 text-sm font-semibold text-[var(--skuy-text-secondary)] underline underline-offset-4 transition-colors hover:text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] disabled:cursor-not-allowed disabled:text-[var(--skuy-text-muted)]">
                                                {{ __('settings.profile.move_link_down') }}
                                            </button>
                                            <button type="button" @click="removeProfileLink(index)" class="inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] px-3 text-sm font-semibold text-[var(--skuy-text-secondary)] underline underline-offset-4 transition-colors hover:text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                                                {{ __('settings.profile.remove_link') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <fieldset class="mt-8 border-t border-[var(--skuy-border-subtle)] pt-6" aria-describedby="profile-privacy-description">
                        <legend id="profile-privacy-heading" class="text-lg font-bold text-[var(--skuy-text-primary)]">{{ __('settings.profile.privacy_heading') }}</legend>
                        <p id="profile-privacy-description" class="mt-1 text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.profile.privacy_description') }}</p>

                        <div class="mt-4 space-y-3">
                            <label for="profile-visibility-public" class="flex min-h-[44px] cursor-pointer gap-3 rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface-subtle)] p-4">
                                <input id="profile-visibility-public" type="radio" name="profile_visibility" value="public" @checked(old('profile_visibility', $user->profile_visibility->value) === 'public') class="mt-1 h-4 w-4 shrink-0 border-[var(--skuy-border-strong)] text-[var(--skuy-brand)] focus:ring-[var(--skuy-focus)]">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.profile.privacy_public') }}</span>
                                    <span class="mt-1 block text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.profile.privacy_public_description') }}</span>
                                </span>
                            </label>

                            <label for="profile-visibility-members-only" class="flex min-h-[44px] cursor-pointer gap-3 rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface-subtle)] p-4">
                                <input id="profile-visibility-members-only" type="radio" name="profile_visibility" value="members_only" @checked(old('profile_visibility', $user->profile_visibility->value) === 'members_only') class="mt-1 h-4 w-4 shrink-0 border-[var(--skuy-border-strong)] text-[var(--skuy-brand)] focus:ring-[var(--skuy-focus)]">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.profile.privacy_members_only') }}</span>
                                    <span class="mt-1 block text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.profile.privacy_members_only_description') }}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

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

        </div>
    </div>
</x-app-layout>
