<section class="space-y-6">
    <header>
        <h2 class="text-xl font-bold text-[var(--skuy-danger)]">
            {{ __('settings.delete.heading') }}
        </h2>

        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.delete.description') }}
        </p>
    </header>

    <x-danger-button
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
                <x-input-label for="password" value="{{ __('settings.delete.password') }}" class="text-[var(--skuy-text-primary)]" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1"
                    placeholder="{{ __('settings.delete.password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('settings.delete.cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('settings.delete.confirm') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
