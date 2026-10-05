@php
    $viewerAppreciation = $displayPost->appreciations->first();
    $appreciationCount = (int) ($displayPost->appreciations_count ?? 0);
    $appreciationCost = \App\Actions\GivePostAppreciation::COST;
    $appreciationCostLabel = __('appreciation.cost_label', [
        'cost' => $appreciationCost,
        'unit' => __('points.unit'),
    ]);
    $canAppreciate = auth()->user()?->canPerformSocialWrites()
        && (int) $displayPost->user_id !== (int) auth()->id()
        && $viewerAppreciation === null;
    $returnSurface = in_array($surface ?? 'detail', ['feed', 'detail'], true) ? ($surface ?? 'detail') : 'detail';
    $appreciationCountLabel = trans_choice('appreciation.count', $appreciationCount, ['count' => $appreciationCount]);
@endphp

@if ($appreciationCount > 0 || $viewerAppreciation !== null || $canAppreciate)
    <section
        x-data="{
            open: false,
            placement: 'below',
            toggle(event) {
                if (this.open) {
                    this.open = false;
                    return;
                }

                const triggerRect = event.currentTarget.getBoundingClientRect();
                this.open = true;
                this.$nextTick(() => {
                    const popoverRect = this.$refs.popover.getBoundingClientRect();
                    const mobileDock = document.querySelector('.skuy-mobile-nav');
                    const bottomBoundary = mobileDock && mobileDock.getClientRects().length > 0
                        ? mobileDock.getBoundingClientRect().top
                        : window.innerHeight;
                    const spaceBelow = bottomBoundary - triggerRect.bottom - 8;
                    const spaceAbove = triggerRect.top - 8;
                    this.placement = spaceBelow >= popoverRect.height || spaceAbove < popoverRect.height
                        ? 'below'
                        : 'above';
                });
            }
        }"
        @click.stop="open = open"
        @click.away="open = false"
        @keydown.escape.window="open = false"
        class="skuy-appreciation skuy-appreciation--inline"
        data-post-appreciation="{{ $displayPost->uid }}"
        aria-label="{{ __('appreciation.title') }}"
    >
        @if ($canAppreciate)
            <button
                type="button"
                data-post-appreciation-trigger
                class="skuy-action skuy-action--appreciation"
                aria-label="{{ __('appreciation.actions.appreciate') }} · {{ $appreciationCostLabel }}"
                title="{{ __('appreciation.actions.appreciate') }} · {{ $appreciationCostLabel }}"
                aria-expanded="false"
                :aria-expanded="open.toString()"
                aria-controls="post-appreciation-{{ $displayPost->uid }}-choices"
                @click.stop="toggle($event)"
            >
                <x-fa-icon name="star" class="text-xl" />
                @if ($appreciationCount > 0)
                    <span class="skuy-appreciation__count" aria-label="{{ $appreciationCountLabel }}">{{ $appreciationCount }}</span>
                @endif
            </button>

            <div
                id="post-appreciation-{{ $displayPost->uid }}-choices"
                x-ref="popover"
                x-show="open"
                style="display: none;"
                class="skuy-appreciation__popover"
                :class="{ 'is-above': placement === 'above' }"
            >
                <p id="post-appreciation-{{ $displayPost->uid }}-choose-type" class="skuy-appreciation__choose-type">
                    {{ __('appreciation.actions.choose_type') }}
                </p>
                <p class="skuy-appreciation__cost">
                    {{ __('appreciation.cost_copy', ['cost' => $appreciationCost, 'unit' => __('points.unit')]) }}
                </p>
                <form action="{{ route('posts.appreciations.store', $displayPost->uid) }}" method="POST">
                    @csrf
                    <input type="hidden" name="surface" value="{{ $returnSurface }}">
                    <div class="skuy-appreciation__choices" role="group" aria-labelledby="post-appreciation-{{ $displayPost->uid }}-choose-type">
                        @foreach (\App\Enums\AppreciationType::cases() as $type)
                            <button type="submit" name="type" value="{{ $type->value }}" class="skuy-appreciation__choice">
                                {{ __('appreciation.types.'.$type->value) }}
                            </button>
                        @endforeach
                    </div>
                </form>
            </div>
        @elseif ($viewerAppreciation)
            <button
                type="button"
                class="skuy-action skuy-action--appreciation skuy-action--appreciated"
                aria-label="{{ __('appreciation.selected', ['type' => __('appreciation.types.'.$viewerAppreciation->type->value)]) }}"
                title="{{ __('appreciation.selected', ['type' => __('appreciation.types.'.$viewerAppreciation->type->value)]) }}"
                aria-pressed="true"
                disabled
            >
                <x-fa-icon name="star" weight="solid" class="text-xl" />
                <span class="skuy-appreciation__count" aria-label="{{ $appreciationCountLabel }}">{{ $appreciationCount }}</span>
            </button>
        @else
            <span class="skuy-action skuy-action--appreciation" aria-label="{{ $appreciationCountLabel }}" title="{{ $appreciationCountLabel }}">
                <x-fa-icon name="star" class="text-xl" />
                <span class="skuy-appreciation__count">{{ $appreciationCount }}</span>
            </span>
        @endif
    </section>
@endif
