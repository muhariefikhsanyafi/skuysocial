<x-app-layout>
    <div class="skuy-feed-page skuy-messages-page">
        <div class="skuy-feed-content skuy-messages-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <header class="skuy-list-page-header">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <div class="min-w-0">
                    <h1 class="skuy-list-page-header__title">{{ __('messages.title') }}</h1>
                    <p class="mt-1 text-sm text-[var(--skuy-text-muted)]">{{ __('messages.subtitle') }}</p>
                </div>
            </header>

            @if ($rows->isEmpty())
                <div class="skuy-social-empty" role="status">
                    <span class="skuy-social-empty__mark" aria-hidden="true">
                        <x-fa-icon name="comment-lines" />
                    </span>
                    <h2 class="skuy-social-empty__title">{{ __('messages.empty_title') }}</h2>
                    <p class="skuy-social-empty__description">{{ __('messages.empty_description') }}</p>
                </div>
            @else
                <ul class="skuy-message-list" aria-label="{{ __('messages.title') }}">
                    @foreach ($rows as $row)
                        @php($counterpart = $row['counterpart'])

                        <li>
                            <article class="skuy-message-row">
                                <div class="skuy-avatar skuy-message-row__avatar" aria-hidden="true">
                                    @if ($counterpart && filled($counterpart->avatar))
                                        <img
                                            src="{{ asset('storage/' . $counterpart->avatar) }}"
                                            alt=""
                                        >
                                    @elseif ($counterpart)
                                        <span>{{ mb_substr($counterpart->name, 0, 1) }}</span>
                                    @else
                                        <x-fa-icon name="user" />
                                    @endif
                                </div>

                                <div class="skuy-message-row__identity">
                                    @if ($counterpart)
                                        <a href="{{ route('user.profile', $counterpart->username) }}" class="skuy-message-row__name">
                                            {{ $counterpart->name }}
                                        </a>
                                        @if ($counterpart->is_verified)
                                            <span class="skuy-profile-verified" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                                </svg>
                                            </span>
                                        @endif
                                        <span class="skuy-message-row__username">{{ '@' . $counterpart->username }}</span>
                                    @else
                                        <span class="skuy-message-row__name">{{ __('messages.deleted_account') }}</span>
                                    @endif
                                </div>

                                <time class="skuy-message-row__time" datetime="{{ $row['activity_at']->toIso8601String() }}">
                                    {{ $row['activity_at']->locale(app()->getLocale())->diffForHumans() }}
                                </time>

                                <p class="skuy-message-row__preview">
                                    @if ($row['sender_prefix'] !== null)
                                        <span class="skuy-message-row__prefix">{{ $row['sender_prefix'] }}</span>
                                    @endif
                                    <span>{{ $row['preview'] }}</span>
                                </p>
                            </article>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
