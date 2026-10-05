@php
    $viewerAppreciation = $displayPost->appreciations->first();
    $showFeedback = session('post_appreciation_post_uid') === (string) $displayPost->uid
        && is_string(session('post_appreciation_feedback'));
@endphp

@if ($viewerAppreciation || $showFeedback)
    <div class="skuy-appreciation-status" data-post-appreciation-status="{{ $displayPost->uid }}">
        @if ($viewerAppreciation)
            <p class="skuy-appreciation__selected" role="status">
                {{ __('appreciation.selected', ['type' => __('appreciation.types.'.$viewerAppreciation->type->value)]) }}
            </p>
        @endif

        @if ($showFeedback)
            <p class="skuy-appreciation__feedback {{ session('post_appreciation_feedback_error') ? 'skuy-appreciation__feedback--error' : '' }}"
                role="{{ session('post_appreciation_feedback_error') ? 'alert' : 'status' }}"
                aria-live="{{ session('post_appreciation_feedback_error') ? 'assertive' : 'polite' }}">
                {{ session('post_appreciation_feedback') }}
            </p>
        @endif
    </div>
@endif
