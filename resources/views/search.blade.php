<x-app-layout>
    @php
        $searchValue = old('q');
        $searchValue = is_string($searchValue) ? $searchValue : $query;
    @endphp

    <div class="skuy-discovery-page skuy-feed-page" data-flow-feed x-data="skuyPostMediaViewer">
        <div class="skuy-discovery-shell">
            <header class="skuy-discovery-header" aria-labelledby="search-page-title">
                <div class="skuy-discovery-header__identity">
                    <span class="skuy-discovery-header__icon" aria-hidden="true">
                        <x-fa-icon name="magnifying-glass" />
                    </span>
                    <div>
                        <p class="skuy-discovery-eyebrow">{{ __('navigation.search') }}</p>
                        <h1 id="search-page-title" class="skuy-discovery-title">{{ __('search.heading') }}</h1>
                    </div>
                </div>
            </header>

            <form action="{{ route('search') }}" method="GET" class="skuy-discovery-search" role="search">
                <label for="discovery-search-input" class="skuy-discovery-search__label">
                    {{ __('search.heading') }}
                </label>
                <div class="skuy-discovery-search__row">
                    <input
                        id="discovery-search-input"
                        type="search"
                        name="q"
                        value="{{ $searchValue }}"
                        placeholder="{{ __('search.placeholder') }}"
                        autocomplete="off"
                        @error('q')
                            aria-invalid="true"
                            aria-describedby="discovery-search-error"
                        @enderror
                        class="skuy-discovery-search__input"
                    >
                    <button type="submit" class="skuy-button skuy-button--primary skuy-discovery-search__submit">
                        {{ __('search.submit') }}
                    </button>
                </div>
                @error('q')
                    <p id="discovery-search-error" class="skuy-discovery-search__error" role="alert">{{ $message }}</p>
                @enderror
            </form>

            @if ($query !== '')
                <div class="skuy-discovery-query" role="status" aria-live="polite">
                    <span class="skuy-discovery-query__label">{{ __('search.heading') }}</span>
                    <span class="skuy-discovery-query__value">{{ $query }}</span>
                </div>

                <div class="skuy-discovery-sections">
                    <section class="skuy-discovery-section" aria-labelledby="search-users-heading">
                        <div class="skuy-discovery-section__heading">
                            <span class="skuy-discovery-section__icon" aria-hidden="true">
                                <x-fa-icon name="users" />
                            </span>
                            <h2 id="search-users-heading" class="skuy-discovery-section__title">{{ __('search.sections.users') }}</h2>
                        </div>

                        @if ($users->isEmpty())
                            <div class="skuy-discovery-empty skuy-discovery-empty--compact" role="status">
                                <p class="skuy-discovery-empty__description">{{ __('search.empty.users') }}</p>
                            </div>
                        @else
                            <div class="skuy-discovery-user-grid" role="list">
                                @foreach ($users as $user)
                                    <a href="{{ route('user.profile', $user->username) }}" class="skuy-discovery-user" role="listitem">
                                        <span class="skuy-avatar skuy-discovery-user__avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                                            @if ($user->avatar)
                                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                                            @else
                                                <span aria-hidden="true">{{ substr($user->name, 0, 1) }}</span>
                                            @endif
                                        </span>
                                        <span class="skuy-discovery-user__identity">
                                            <span class="skuy-discovery-user__name-line">
                                                <span class="skuy-discovery-user__name">{{ $user->name }}</span>
                                                @if ($user->is_verified)
                                                    <span class="skuy-discovery-verified" role="img" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path></svg>
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="skuy-discovery-user__username">{{ '@' . $user->username }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($hashtags->isNotEmpty())
                        <section class="skuy-discovery-section" aria-labelledby="search-hashtags-heading">
                            <div class="skuy-discovery-section__heading">
                                <span class="skuy-discovery-section__icon" aria-hidden="true">#</span>
                                <h2 id="search-hashtags-heading" class="skuy-discovery-section__title">{{ __('search.sections.hashtags') }}</h2>
                            </div>
                            <div class="skuy-discovery-link-grid" role="list">
                                @foreach ($hashtags as $hashtag)
                                    <a href="{{ route('tags.show', $hashtag->name) }}" class="skuy-discovery-link" role="listitem">
                                        <span class="skuy-discovery-link__mark" aria-hidden="true">
                                            <x-fa-icon name="hashtag" />
                                        </span>
                                        <span class="skuy-discovery-link__text">#{{ $hashtag->name }}</span>
                                        <x-fa-icon name="arrow-right" class="skuy-discovery-link__arrow" />
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($topics->isNotEmpty())
                        <section class="skuy-discovery-section" aria-labelledby="search-topics-heading">
                            <div class="skuy-discovery-section__heading">
                                <span class="skuy-discovery-section__icon" aria-hidden="true">
                                    <x-fa-icon name="hashtag" />
                                </span>
                                <h2 id="search-topics-heading" class="skuy-discovery-section__title">{{ __('search.sections.topics') }}</h2>
                            </div>
                            <div class="skuy-discovery-link-grid" role="list">
                                @foreach ($topics as $topic)
                                    <a href="{{ route('topik.show', ['topic' => $topic]) }}" class="skuy-discovery-link" role="listitem">
                                        <span class="skuy-discovery-link__mark" aria-hidden="true">
                                            <x-fa-icon name="hashtag" />
                                        </span>
                                        <span class="skuy-discovery-link__text">#{{ $topic }}</span>
                                        <x-fa-icon name="arrow-right" class="skuy-discovery-link__arrow" />
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="skuy-discovery-section" aria-labelledby="search-threads-heading">
                        <div class="skuy-discovery-section__heading">
                            <span class="skuy-discovery-section__icon" aria-hidden="true">
                                <x-fa-icon name="comment-lines" />
                            </span>
                            <h2 id="search-threads-heading" class="skuy-discovery-section__title">{{ __('search.sections.threads') }}</h2>
                        </div>

                        @if ($posts->isEmpty())
                            <div class="skuy-discovery-empty" role="status">
                                <span class="skuy-discovery-empty__mark" aria-hidden="true">
                                    <x-fa-icon name="comment-lines" />
                                </span>
                                <h3 class="skuy-discovery-empty__title">{{ __('search.empty.threads') }}</h3>
                            </div>
                        @else
                            <div class="skuy-discovery-post-list skuy-feed-list">
                                @include('partials.feed-threads', ['posts' => $posts])
                            </div>

                            @if ($posts->hasPages())
                                <div class="skuy-discovery-pagination">
                                    {{ $posts->links() }}
                                </div>
                            @endif
                        @endif
                    </section>
                </div>
            @elseif (! $errors->has('q'))
                <section class="skuy-discovery-empty skuy-discovery-empty--initial" aria-labelledby="search-empty-title" role="status">
                    <span class="skuy-discovery-empty__mark" aria-hidden="true">
                        <x-fa-icon name="magnifying-glass" />
                    </span>
                    <h2 id="search-empty-title" class="skuy-discovery-empty__title">{{ __('search.empty.initial_title') }}</h2>
                    <p class="skuy-discovery-empty__description">{{ __('search.empty.initial_description') }}</p>
                </section>
            @endif
        </div>
        @include('partials.post-media-lightbox')
    </div>
</x-app-layout>
