<section class="skuy-settings-panel">
    <header>
        <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
            {{ __('settings.messaging.heading') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.messaging.description') }}
        </p>
    </header>

    <form method="post" action="{{ route('settings.messaging-privacy.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <fieldset class="space-y-3" @if ($errors->has('message_privacy')) aria-describedby="message-privacy-error" @endif>
            <legend class="sr-only">{{ __('settings.messaging.heading') }}</legend>

            @foreach (\App\Enums\MessagePrivacy::cases() as $privacy)
                <label for="message-privacy-{{ $privacy->value }}" class="flex min-h-[52px] cursor-pointer items-start gap-3 rounded-[var(--radius-medium)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-4 transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-within:border-[var(--skuy-focus)]">
                    <input
                        id="message-privacy-{{ $privacy->value }}"
                        type="radio"
                        name="message_privacy"
                        value="{{ $privacy->value }}"
                        @checked(old('message_privacy', $user->message_privacy->value) === $privacy->value)
                        aria-describedby="message-privacy-{{ $privacy->value }}-description"
                        class="mt-1 size-4 shrink-0 accent-[var(--skuy-brand)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]"
                    >
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-[var(--skuy-text-primary)]">{{ __('settings.messaging.options.'.$privacy->value) }}</span>
                        <span id="message-privacy-{{ $privacy->value }}-description" class="mt-1 block text-sm leading-6 text-[var(--skuy-text-secondary)]">{{ __('settings.messaging.descriptions.'.$privacy->value) }}</span>
                    </span>
                </label>
            @endforeach

            <x-input-error id="message-privacy-error" :messages="$errors->get('message_privacy')" />
        </fieldset>

        <div class="flex flex-col gap-3 border-t border-[var(--skuy-border-subtle)] pt-5 sm:flex-row sm:items-center">
            <x-primary-button class="min-h-[44px]">{{ __('settings.messaging.save') }}</x-primary-button>
            @if (session('status') === 'message-privacy-updated')
                <p class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                    {{ __('settings.messaging.saved') }}
                </p>
            @endif
        </div>
    </form>
</section>
