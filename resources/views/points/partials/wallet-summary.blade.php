<section class="skuy-settings-panel" aria-labelledby="points-wallet-heading">
    <header>
        <h2 id="points-wallet-heading" class="text-xl font-bold text-[var(--skuy-text-primary)]">
            {{ __('points.wallet.heading') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('points.wallet.description') }}
        </p>
    </header>

    <p class="mt-5 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[var(--skuy-text-primary)]">
        <span>{{ __('points.wallet.balance_label') }}:</span>
        <strong class="text-2xl font-bold tabular-nums" data-points-balance="{{ $pointBalance }}">
            {{ \Illuminate\Support\Number::format($pointBalance, locale: app()->getLocale()) }}
        </strong>
        <span>{{ __('points.unit') }}</span>
    </p>

    <a href="{{ route('points.history') }}" class="mt-4 inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] px-2 text-sm font-semibold text-[var(--skuy-secondary)] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
        {{ __('points.wallet.history_link') }}
    </a>
</section>
