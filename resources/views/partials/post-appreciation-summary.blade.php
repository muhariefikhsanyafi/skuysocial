@php
    $viewerAppreciation = $displayPost->appreciations->first();
    $canSendAppreciation = auth()->user()?->canPerformSocialWrites()
        && (int) $displayPost->user_id !== (int) auth()->id()
        && $viewerAppreciation === null;
    $appreciationTypes = ['bermanfaat', 'inspiratif', 'solutif'];
    $appreciationIcons = [
        'bermanfaat' => 'lightbulb',
        'inspiratif' => 'sparkles',
        'solutif' => 'circle-check',
    ];
    $appreciationCount = (int) ($displayPost->appreciations_count ?? 0);
    $pointsCost = \App\Actions\GivePostAppreciation::COST;
@endphp

@if ($appreciationCount > 0)
    <section class="skuy-post-appreciation-summary" aria-label="{{ __('appreciation.title') }}" data-post-appreciation-summary="{{ $displayPost->uid }}">
        <div class="skuy-post-appreciation-summary__choices">
            @foreach ($appreciationTypes as $type)
                @if ($canSendAppreciation)
                    <form action="{{ route('posts.appreciations.store', $displayPost->uid) }}" method="POST">
                        @csrf
                        <input type="hidden" name="surface" value="feed">
                        <button type="submit" name="type" value="{{ $type }}" class="skuy-post-appreciation-summary__choice">
                            <x-fa-icon :name="$appreciationIcons[$type]" :weight="$type === 'inspiratif' ? 'solid' : 'regular'" class="text-xs" />
                            {{ __('appreciation.types.'.$type) }}
                        </button>
                    </form>
                @else
                    <span class="skuy-post-appreciation-summary__choice">
                        <x-fa-icon :name="$appreciationIcons[$type]" :weight="$type === 'inspiratif' ? 'solid' : 'regular'" class="text-xs" />
                        {{ __('appreciation.types.'.$type) }}
                    </span>
                @endif
            @endforeach
            <span class="skuy-post-appreciation-summary__cost">{{ $pointsCost }} {{ __('points.appreciation_cost_unit') }}</span>
        </div>
    </section>
@endif
