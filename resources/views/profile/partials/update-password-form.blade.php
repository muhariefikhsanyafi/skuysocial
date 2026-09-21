<section>
    <header>
        <h2 class="text-lg font-medium text-[var(--skuy-text-primary)]">
            {{ __('settings.password.heading') }}
        </h2>

        <p class="mt-1 text-sm text-[var(--skuy-text-secondary)]">
            {{ __('settings.password.description') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('settings.password.current')" class="text-[var(--skuy-text-primary)]" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)]" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-[var(--skuy-danger)]" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('settings.password.new')" class="text-[var(--skuy-text-primary)]" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-[var(--skuy-danger)]" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('settings.password.confirmation')" class="text-[var(--skuy-text-primary)]" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full border-4 border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] p-3 font-bold text-[var(--skuy-text-primary)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:border-[var(--skuy-focus)] focus:bg-[var(--skuy-selected)]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-[var(--skuy-danger)]" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button class="bg-[var(--skuy-brand)] hover:bg-[var(--skuy-brand-hover)] border-4 border-[var(--skuy-border-strong)] text-[var(--skuy-on-brand)] font-black uppercase px-6 py-3 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all">
    {{ __('settings.password.save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-[var(--skuy-secondary)]"
                >{{ __('settings.password.saved') }}</p>
            @endif
        </div>
    </form>
</section>
