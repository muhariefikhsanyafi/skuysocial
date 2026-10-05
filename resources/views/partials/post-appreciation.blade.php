@php
    $viewerAppreciation = $displayPost->appreciations->first();
    $appreciationCount = (int) ($displayPost->appreciations_count ?? 0);
    $showFeedback = session('post_appreciation_post_uid') === (string) $displayPost->uid
        && is_string(session('post_appreciation_feedback'));
    $appreciationCost = \App\Actions\GivePostAppreciation::COST;
    $canAppreciate = auth()->user()?->canPerformSocialWrites()
        && (int) $displayPost->user_id !== (int) auth()->id()
        && $viewerAppreciation === null;
@endphp

@if ($appreciationCount > 0 || $viewerAppreciation !== null || $canAppreciate || $showFeedback)
<section class="skuy-appreciation" data-post-appreciation="{{ $displayPost->uid }}" aria-label="{{ __('appreciation.title') }}">
    @if ($appreciationCount > 0)
        <p class="skuy-appreciation__count" aria-label="{{ trans_choice('appreciation.count', $appreciationCount, ['count' => $appreciationCount]) }}">
            {{ trans_choice('appreciation.count', $appreciationCount, ['count' => $appreciationCount]) }}
        </p>
    @endif

    @if ($viewerAppreciation)
        <p class="skuy-appreciation__selected" role="status">
            {{ __('appreciation.selected', ['type' => __('appreciation.types.'.$viewerAppreciation->type->value)]) }}
        </p>
    @elseif ($canAppreciate)
        <details class="skuy-appreciation__details">
            <summary class="skuy-appreciation__trigger">
                <span>{{ __('appreciation.actions.appreciate') }}</span>
                <span class="skuy-appreciation__trigger-cost">{{ $appreciationCost }} {{ __('points.unit') }}</span>
            </summary>
            <p class="skuy-appreciation__cost">
                {{ __('appreciation.cost_copy', ['cost' => $appreciationCost, 'unit' => __('points.unit')]) }}
            </p>
            <form action="{{ route('posts.appreciations.store', $displayPost->uid) }}" method="POST">
                @csrf
                <div class="skuy-appreciation__choices" role="group" aria-label="{{ __('appreciation.actions.choose_type') }}">
                    @foreach (\App\Enums\AppreciationType::cases() as $type)
                        <button type="submit" name="type" value="{{ $type->value }}" class="skuy-appreciation__choice">
                            {{ __('appreciation.types.'.$type->value) }}
                        </button>
                    @endforeach
                </div>
            </form>
        </details>
    @endif

    @if ($showFeedback)
        <p class="skuy-appreciation__feedback {{ session('post_appreciation_feedback_error') ? 'skuy-appreciation__feedback--error' : '' }}"
            role="{{ session('post_appreciation_feedback_error') ? 'alert' : 'status' }}"
            aria-live="{{ session('post_appreciation_feedback_error') ? 'assertive' : 'polite' }}">
            {{ session('post_appreciation_feedback') }}
        </p>
    @endif
</section>
@endif
