@php
    $poll = $displayPost->poll ?? null;
    $pollOptions = $poll?->options ?? collect();
    $pollTotalVotes = (int) $pollOptions->sum('votes_count');
    $viewerVoteOptionId = $poll?->viewerVotes?->first()?->option_id;
    $canVote = auth()->check()
        && auth()->user()->canPerformSocialWrites()
        && $viewerVoteOptionId === null;
@endphp

@if ($poll && $pollOptions->isNotEmpty())
    <section class="skuy-poll" data-post-poll="{{ $poll->id }}" aria-label="{{ $poll->question }}">
        <h3 class="skuy-poll__question">{{ $poll->question }}</h3>

        @if ($canVote)
            <form action="{{ route('posts.poll-votes', $displayPost->uid) }}" method="POST" class="skuy-poll__form">
                @csrf
                @foreach ($pollOptions as $option)
                    @php
                        $optionVotes = (int) ($option->votes_count ?? 0);
                        $percentage = $pollTotalVotes > 0 ? (int) round(($optionVotes / $pollTotalVotes) * 100) : 0;
                    @endphp
                    <label class="skuy-poll__option">
                        <input class="skuy-poll__radio" type="radio" name="option_id" value="{{ $option->id }}" required>
                        <span class="skuy-poll__option-content">
                            <span class="skuy-poll__option-heading">
                                <span>{{ $option->label }}</span>
                                <strong>{{ $percentage }}%</strong>
                            </span>
                            <span class="skuy-poll__bar" aria-hidden="true"><span style="width: {{ $percentage }}%"></span></span>
                        </span>
                    </label>
                @endforeach
                <button class="skuy-poll__vote" type="submit">{{ __('polls.vote') }}</button>
            </form>
        @else
            <div class="skuy-poll__results" role="list" aria-label="{{ __('polls.results') }}">
                @foreach ($pollOptions as $option)
                    @php
                        $optionVotes = (int) ($option->votes_count ?? 0);
                        $percentage = $pollTotalVotes > 0 ? (int) round(($optionVotes / $pollTotalVotes) * 100) : 0;
                        $isViewerChoice = $viewerVoteOptionId !== null && (int) $viewerVoteOptionId === (int) $option->id;
                    @endphp
                    <div class="skuy-poll__result{{ $isViewerChoice ? ' is-selected' : '' }}" role="listitem" aria-label="{{ $option->label }}: {{ $percentage }}%">
                        <span class="skuy-poll__option-heading">
                            <span>{{ $option->label }}</span>
                            <strong>{{ $percentage }}%</strong>
                        </span>
                        <span class="skuy-poll__bar" aria-hidden="true"><span style="width: {{ $percentage }}%"></span></span>
                    </div>
                @endforeach
            </div>
        @endif

        @error('option_id')
            <p class="skuy-poll__error" role="alert">{{ $message }}</p>
        @enderror
        <p class="skuy-poll__total">{{ trans_choice('polls.votes', $pollTotalVotes, ['count' => $pollTotalVotes]) }}</p>
    </section>
@endif
