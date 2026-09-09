<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('settings.delete.heading') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('settings.delete.description') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
		class="bg-red-400 text-black border-4 border-black font-black uppercase px-6 py-3 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all">
    {{ __('settings.delete.button') }}
	</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('settings.delete.confirmation_heading') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('settings.delete.confirmation_description') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('settings.delete.password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full border-4 border-black p-3 font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none"
                    placeholder="{{ __('settings.delete.password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('settings.delete.cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('settings.delete.confirm') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
