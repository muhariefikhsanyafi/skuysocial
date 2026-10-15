<x-app-layout>
    <div class="skuy-feed-page skuy-points-page" data-flow-support="points">
        <div class="skuy-feed-content skuy-points-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <h1 class="skuy-list-page-header__title">{{ __('points.history.title') }}</h1>
            </header>

            @include('points.partials.wallet-summary', ['pointBalance' => $balance])

            @if ($transactions->isEmpty())
                <section class="skuy-social-empty mt-6" aria-labelledby="points-empty-heading" role="status">
                    <h2 id="points-empty-heading" class="skuy-social-empty__title">{{ __('points.history.empty_heading') }}</h2>
                    <p class="skuy-social-empty__description">{{ __('points.history.empty_description') }}</p>
                </section>
            @else
                <ol class="mt-6 divide-y divide-[var(--skuy-border-subtle)]" aria-label="{{ __('points.history.list_label') }}">
                    @foreach ($transactions as $transaction)
                        @php
                            $reasonKey = 'points.reasons.'.$transaction->reason_code;
                            $reason = \Illuminate\Support\Facades\Lang::has($reasonKey)
                                ? __($reasonKey)
                                : __('points.history.uncategorized');
                        @endphp
                        <li class="flex min-w-0 flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-[var(--skuy-text-primary)]">{{ $reason }}</p>
                                <time class="mt-1 block text-sm text-[var(--skuy-text-secondary)]" datetime="{{ $transaction->created_at->toIso8601String() }}">
                                    {{ $transaction->created_at->locale(app()->getLocale())->translatedFormat('j M Y H:i') }}
                                </time>
                            </div>
                            <p class="shrink-0 text-sm font-semibold tabular-nums text-[var(--skuy-text-primary)]">
                                <span class="sr-only">{{ __('points.transaction_types.'.$transaction->type->value) }}: </span>{{ $transaction->amount > 0 ? '+' : '−' }}{{ \Illuminate\Support\Number::format(abs($transaction->amount), locale: app()->getLocale()) }} {{ __('points.unit') }}
                            </p>
                        </li>
                    @endforeach
                </ol>

                @if ($transactions->hasPages())
                    <nav class="skuy-activity-pagination" aria-label="{{ __('points.history.pagination_label') }}">
                        {{ $transactions->links() }}
                    </nav>
                @endif
            @endif

            <a href="{{ route('profile.edit', auth()->user()->username) }}" class="mt-6 inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] px-2 text-sm font-semibold text-[var(--skuy-secondary)] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                {{ __('points.history.back_to_settings') }}
            </a>
        </div>
    </div>
</x-app-layout>
