<section class="skuy-settings-panel space-y-6 border border-[var(--skuy-danger)]" aria-labelledby="delete-account-heading">
    <header>
        <h2 id="delete-account-heading" class="text-xl font-bold text-[var(--skuy-danger)]">
            {{ __('settings.delete.danger_heading') }}
        </h2>

        <h3 class="mt-3 text-base font-bold text-[var(--skuy-text-primary)]">
            {{ __('settings.delete.heading') }}
        </h3>

        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.delete.description') }}
        </p>
    </header>

    <x-danger-button
        type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('settings.delete.button') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="space-y-6 p-6 sm:p-8">
            @csrf
            @method('delete')

            <div>
                <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
                    {{ __('settings.delete.confirmation_heading') }}
                </h2>

                <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
                    {{ __('settings.delete.confirmation_description') }}
                </p>
            </div>

            <div class="space-y-2">
                <x-input-label for="delete-account-username-confirmation" value="{{ __('settings.delete.username_confirmation') }}" class="text-[var(--skuy-text-primary)]" />

                <p id="delete-account-username-hint" class="text-sm text-[var(--skuy-text-secondary)]">
                    {{ __('settings.delete.username_hint', ['username' => $user->username]) }}
                </p>

                <x-text-input
                    id="delete-account-username-confirmation"
                    name="username_confirmation"
                    type="text"
                    class="mt-1"
                    value="{{ old('username_confirmation') }}"
                    maxlength="255"
                    autocomplete="off"
                    aria-describedby="delete-account-username-hint"
                    required
                />

                <x-input-error :messages="$errors->userDeletion->get('username_confirmation')" class="mt-2" />
            </div>

            <div class="space-y-2">
                <x-input-label for="delete-account-password" value="{{ __('settings.delete.password') }}" class="text-[var(--skuy-text-primary)]" />

                <x-text-input
                    id="delete-account-password"
                    name="password"
                    type="password"
                    class="mt-1"
                    placeholder="{{ __('settings.delete.password_placeholder') }}"
                    autocomplete="current-password"
                    required
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('settings.delete.cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('settings.delete.confirm') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
