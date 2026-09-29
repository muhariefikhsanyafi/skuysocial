@php
    $oneSignalClientAvailable = (bool) config('services.onesignal.enabled', false)
        && filled(config('services.onesignal.app_id'));
@endphp

<section
    class="skuy-settings-panel space-y-5"
    aria-labelledby="push-notifications-heading"
    data-push-settings
    data-state-checking="{{ __('settings.push.state.checking') }}"
    data-state-unavailable="{{ __('settings.push.state.unavailable') }}"
    data-state-unsupported="{{ __('settings.push.state.unsupported') }}"
    data-state-denied="{{ __('settings.push.state.denied') }}"
    data-state-not-requested="{{ __('settings.push.state.not_requested') }}"
    data-state-enabled="{{ __('settings.push.state.enabled') }}"
    data-state-action-failed="{{ __('settings.push.action_failed') }}"
    data-enable-label="{{ __('settings.push.enable') }}"
    data-disable-label="{{ __('settings.push.disable') }}"
>
    <header>
        <h2 id="push-notifications-heading" class="text-xl font-bold text-[var(--skuy-text-primary)]">
            {{ __('settings.push.heading') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.push.description') }}
        </p>
    </header>

    <p data-push-status role="status" aria-live="polite" aria-atomic="true" class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
        {{ $oneSignalClientAvailable ? __('settings.push.state.checking') : __('settings.push.state.unavailable') }}
    </p>

    <p data-push-action-error hidden role="alert" class="text-sm leading-6 text-[var(--skuy-danger)]"></p>

    <p data-push-denied-help hidden class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
        {{ __('settings.push.denied_help') }}
    </p>

    <noscript>
        <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.push.javascript_required') }}
        </p>
    </noscript>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <button
            type="button"
            data-push-enable
            hidden
            class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-brand)] bg-[var(--skuy-brand)] px-4 py-3 text-sm font-semibold text-[var(--skuy-on-brand)] transition-colors hover:border-[var(--skuy-brand-hover)] hover:bg-[var(--skuy-brand-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] disabled:cursor-not-allowed disabled:opacity-60"
        >
            {{ __('settings.push.enable') }}
        </button>
        <button
            type="button"
            data-push-disable
            hidden
            class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-4 py-3 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] disabled:cursor-not-allowed disabled:opacity-60"
        >
            {{ __('settings.push.disable') }}
        </button>
    </div>
</section>
