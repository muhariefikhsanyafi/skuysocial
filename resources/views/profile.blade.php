<x-app-layout>
    @php
        $profilePostCount = $user->posts()->count();
    @endphp

    <div class="skuy-profile-page">
        <div class="skuy-profile-shell">
            <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-profile-back">
                <x-fa-icon name="arrow-left-long" class="text-xl" />
                <span>{{ __('navigation.back_to_feed') }}</span>
            </a>

            <section class="skuy-profile-header" aria-labelledby="profile-title">
                <div class="skuy-profile-cover {{ $user->cover ? 'skuy-profile-cover--image' : 'skuy-profile-cover--empty' }}" data-profile-cover="{{ $user->cover ? 'image' : 'fallback' }}">
                    @if ($user->cover)
                        <img src="{{ asset('storage/' . $user->cover) }}" alt="{{ __('profile.media.cover_of', ['name' => $user->name]) }}" class="skuy-profile-cover__image">
                    @else
                        <div class="skuy-profile-cover__placeholder" aria-hidden="true"></div>
                    @endif
                </div>

                <div class="skuy-profile-header__body">
                    <div class="skuy-profile-header__top">
                        <div class="skuy-profile-avatar">
                            @if ($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                            @else
                                <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="skuy-profile-identity">
                        <h1 id="profile-title" class="skuy-profile-title">
                            <span>{{ $user->name }}</span>
                            @if ($user->is_verified)
                                <span class="skuy-profile-verified" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                    </svg>
                                </span>
                            @endif
                        </h1>
                        <p class="skuy-profile-username">{{ '@' . $user->username }}</p>
                    </div>

                    <p class="skuy-profile-bio{{ filled(trim($user->bio ?? '')) ? '' : ' skuy-profile-bio--empty' }}">{{ filled(trim($user->bio ?? '')) ? $user->bio : __('profile.no_bio') }}</p>

                    <div class="skuy-profile-action">
                        @if (auth()->id() === $user->id)
                            <a href="{{ route('user.edit') }}" class="skuy-button skuy-button--primary">
                                {{ __('profile.edit') }}
                            </a>
                        @else
                            <form action="{{ route('user.follow', $user->username) }}" method="POST">
                                @csrf
                                <button
                                    type="submit"
                                    aria-pressed="{{ $isFollowing ? 'true' : 'false' }}"
                                    class="skuy-button skuy-profile-follow-button{{ $isFollowing ? ' skuy-profile-follow-button--following' : '' }}"
                                >
                                    {{ $isFollowing ? __('profile.following') : __('profile.follow') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="skuy-profile-stats" role="group" aria-label="{{ __('profile.threads') }}, {{ __('profile.followers') }}, {{ __('profile.following') }}">
                        <div class="skuy-profile-stat">
                            <span class="skuy-profile-stat__value">{{ $profilePostCount }}</span>
                            <span class="skuy-profile-stat__label">{{ __('profile.threads') }}</span>
                        </div>
                        <a href="{{ route('user.followers', $user) }}" class="skuy-profile-stat">
                            <span class="skuy-profile-stat__value">{{ $followersCount }}</span>
                            <span class="skuy-profile-stat__label">{{ __('profile.followers') }}</span>
                        </a>
                        <a href="{{ route('user.following', $user) }}" class="skuy-profile-stat">
                            <span class="skuy-profile-stat__value">{{ $followingsCount }}</span>
                            <span class="skuy-profile-stat__label">{{ __('profile.following') }}</span>
                        </a>
                    </div>
                </div>

                <nav class="skuy-profile-tabs" aria-label="{{ __('profile.threads') }}, {{ __('profile.likes') }}">
                    <a
                        href="{{ route('user.profile', $user) }}"
                        aria-current="{{ $activeTab === 'threads' ? 'page' : 'false' }}"
                        class="skuy-profile-tab{{ $activeTab === 'threads' ? ' is-active' : '' }}"
                    ><span>{{ __('profile.threads') }}</span></a>
                    <a
                        href="{{ route('user.profile', ['username' => $user->username, 'tab' => 'likes']) }}"
                        aria-current="{{ $activeTab === 'likes' ? 'page' : 'false' }}"
                        class="skuy-profile-tab{{ $activeTab === 'likes' ? ' is-active' : '' }}"
                    ><span>{{ __('profile.likes') }}</span></a>
                </nav>
            </section>

            <section class="skuy-profile-content" aria-label="{{ $activeTab === 'likes' ? __('profile.likes') : __('profile.threads') }}">
                @if ($posts->count() > 0)
                    <div class="skuy-profile-post-list">
                        @foreach ($posts as $post)
                            @php
                                $isRepost = $post->repost_id !== null;
                                $displayPost = $isRepost ? $post->originalPost : $post;
                                $modernMedia = is_array($displayPost?->images) ? $displayPost->images : [];
                                $mediaPaths = [];

                                foreach ($modernMedia as $mediaPath) {
                                    if (is_string($mediaPath) && trim($mediaPath) !== '' && ! in_array($mediaPath, $mediaPaths, true)) {
                                        $mediaPaths[] = $mediaPath;
                                    }
                                }

                                if ($mediaPaths === [] && is_string($displayPost?->image) && trim($displayPost->image) !== '') {
                                    $mediaPaths[] = $displayPost->image;
                                }
                            @endphp

                            <article
                                x-data
                                @click="if(!$event.target.closest('button, a, form, img')) window.location='{{ route('posts.show', $displayPost->uid) }}'"
                                class="skuy-post skuy-profile-post cursor-pointer"
                            >
                                @if ($isRepost)
                                    <div class="skuy-post__repost">
                                        <x-fa-icon name="retweet" class="text-base" />
                                        <span>{{ __('threads.reposted_by', ['username' => $post->user->username]) }}</span>
                                    </div>
                                @endif

                                <div class="skuy-post__layout">
                                    <div class="skuy-post__avatar-column">
                                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                            @if ($displayPost->user->avatar)
                                                <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $displayPost->user->name]) }}">
                                            @else
                                                <span aria-hidden="true">{{ mb_substr($displayPost->user->name, 0, 1) }}</span>
                                            @endif
                                        </a>
                                    </div>

                                    <div class="skuy-post__body">
                                        <div class="skuy-post__header">
                                            <div class="skuy-post__identity">
                                                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__display-name">
                                                    {{ $displayPost->user->name }}
                                                </a>
                                                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="skuy-post__username">
                                                    <span aria-hidden="true">@</span>{{ $displayPost->user->username }}
                                                </a>
                                                @if ($displayPost->user->is_verified)
                                                    <span class="inline-flex text-[var(--skuy-secondary)]" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                                        </svg>
                                                    </span>
                                                @endif
                                                <time datetime="{{ $displayPost->created_at->toIso8601String() }}" class="skuy-post__time">
                                                    {{ $displayPost->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}
                                                </time>
                                            </div>
                                        </div>

                                        <div class="skuy-post__content">
                                            <p class="skuy-post__text">{!! preg_replace('/#([a-zA-Z0-9_]+)/', '<a href="'.url('/tags').'/$1" class="skuy-hashtag">#$1</a>', e($displayPost->content)) !!}</p>

                                            @if (count($mediaPaths) === 1)
                                                <div class="skuy-profile-post__single-media">
                                                    <img src="{{ asset('storage/' . $mediaPaths[0]) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                </div>
                                            @elseif (count($mediaPaths) > 1)
                                                <div
                                                    x-data="{
                                                        activeSlide: 0,
                                                        updateDots(event) {
                                                            const maxIndex = {{ count($mediaPaths) - 1 }};
                                                            if (maxIndex === 0) return;

                                                            const maxScroll = event.target.scrollWidth - event.target.clientWidth;
                                                            if (maxScroll === 0) return;

                                                            this.activeSlide = Math.round((event.target.scrollLeft / maxScroll) * maxIndex);
                                                        }
                                                    }"
                                                    data-media-slider="true"
                                                    class="skuy-profile-post__media-slider"
                                                >
                                                    <div @scroll.passive="updateDots" data-media-slider-track="true" class="skuy-media-track overflow-x-auto snap-x snap-mandatory">
                                                        @foreach ($mediaPaths as $mediaPath)
                                                            <div data-media-slide="true" class="skuy-media-item snap-center shrink-0">
                                                                <img src="{{ asset('storage/' . $mediaPath) }}" alt="{{ __('threads.media.photo_by', ['name' => $displayPost->user->name]) }}">
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    <div data-media-indicator="true" class="skuy-media-dots" aria-hidden="true">
                                                        @foreach ($mediaPaths as $index => $mediaPath)
                                                            <div data-media-indicator-dot="true" class="skuy-media-dot w-1.5 sm:w-2 bg-gray-300 dark:bg-[#242424]" :class="activeSlide === {{ $index }} ? 'is-active' : ''"></div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="skuy-profile-post__footer" @click.stop>
                                            <a href="{{ route('posts.show', $displayPost->uid) }}" class="skuy-profile-post__view-link">
                                                {{ __('threads.actions.view_thread') }}
                                            </a>

                                            @if ($displayPost->bookmarked_by_me)
                                                <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark">
                                                        <x-fa-icon name="bookmark" weight="solid" class="text-xl" />
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('bookmarks.store', $displayPost->uid) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" aria-label="{{ __('threads.actions.save') }}" title="{{ __('threads.actions.save') }}" aria-pressed="false" class="skuy-action skuy-action--bookmark">
                                                        <x-fa-icon name="bookmark" class="text-xl" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="skuy-profile-empty" role="status">
                        <h2 class="skuy-profile-empty__title">
                            {{ $activeTab === 'likes' ? __('profile.empty.liked_threads_title') : __('profile.empty.threads_title') }}
                        </h2>
                        <p class="skuy-profile-empty__description">
                            {{ $activeTab === 'likes' ? __('profile.empty.liked_threads_description') : __('profile.empty.threads_description') }}
                        </p>
                    </div>
                @endif

                @if ($posts->hasPages())
                    <div class="skuy-profile-pagination">
                        {{ $posts->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
