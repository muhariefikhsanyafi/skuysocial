@forelse ($replies as $reply)
    <article class="skuy-profile-reply" data-profile-reply="{{ $reply->id }}">
        <header class="skuy-profile-reply__header">
            <a href="{{ route('user.profile', $reply->user->username) }}" class="skuy-profile-reply__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $reply->user->name]) }}">
                @if ($reply->user->avatar)
                    <img src="{{ asset('storage/' . $reply->user->avatar) }}" alt="">
                @else
                    {{ \App\Support\NameInitials::from($reply->user->name) }}
                @endif
            </a>
            <div class="skuy-profile-reply__identity">
                <a href="{{ route('user.profile', $reply->user->username) }}" class="skuy-profile-reply__name">{{ $reply->user->name }}</a>
                <span class="skuy-profile-reply__username">{{ '@' . $reply->user->username }}</span>
                <span aria-hidden="true">·</span>
                <time datetime="{{ $reply->created_at->toIso8601String() }}">{{ $reply->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}</time>
            </div>
        </header>
        <blockquote class="skuy-profile-reply__source">{{ $reply->post->content }}</blockquote>
        <p class="skuy-profile-reply__text">{{ $reply->content }}</p>
        <div class="skuy-profile-reply__context">
            <span>{{ __('profile.replying_to', ['username' => '@' . $reply->post->user->username]) }}</span>
            <a href="{{ route('posts.show', $reply->post->uid) }}">{{ __('threads.actions.view_thread') }}</a>
        </div>
    </article>
@empty
    <div class="skuy-profile-empty" role="status">
        <h2 class="skuy-profile-empty__title">{{ __('profile.empty.replies_title') }}</h2>
        <p class="skuy-profile-empty__description">{{ __('profile.empty.replies_description') }}</p>
    </div>
@endforelse
