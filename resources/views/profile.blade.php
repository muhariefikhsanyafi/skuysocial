<x-app-layout>
    @php
        $profilePostCount = $user->posts()->count();
    @endphp

    <div class="skuy-profile-page" data-skuy-profile>
        <div class="skuy-profile-shell">
            <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-profile-back">
                <x-fa-icon name="arrow-left-long" class="text-xl" />
                <span>{{ __('navigation.back_to_feed') }}</span>
            </a>

            <section class="skuy-profile-header {{ $user->cover ? 'skuy-profile-header--has-cover' : 'skuy-profile-header--no-cover' }}" data-profile-hero aria-labelledby="profile-title">
                <div class="skuy-profile-cover {{ $user->cover ? 'skuy-profile-cover--image' : 'skuy-profile-cover--empty' }}" data-profile-cover="{{ $user->cover ? 'image' : 'fallback' }}">
                    @if ($user->cover)
                        <img src="{{ asset('storage/' . $user->cover) }}" alt="{{ __('profile.media.cover_of', ['name' => $user->name]) }}" class="skuy-profile-cover__image">
                    @else
                        <div class="skuy-profile-cover__placeholder" aria-hidden="true"></div>
                    @endif
                </div>

                <div class="skuy-profile-header__body">
                    <div class="skuy-profile-hero-row">
                        <div class="skuy-profile-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                            @if ($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                            @else
                                <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                            @endif
                        </div>

                        <div class="skuy-profile-identity" data-profile-identity>
                        <h1 id="profile-title" class="skuy-profile-title">
                            <span>{{ $user->name }}</span>
                            @if ($user->is_verified)
                                <x-verified-badge flow />
                            @endif
                        </h1>
                        <p class="skuy-profile-username">{{ '@' . $user->username }}</p>
                    </div>

                        <div class="skuy-profile-action" data-profile-actions>
                            @if (auth()->id() === $user->id)
                                <div class="skuy-profile-owner-actions" data-profile-owner-actions>
                                    <a href="{{ route('user.edit', $user->username) }}" class="skuy-button skuy-button--primary">
                                        <x-fa-icon name="pen-to-square" />
                                        <span>{{ __('profile.edit') }}</span>
                                    </a>
                                    <a href="{{ route('profile.edit', $user->username) }}" class="skuy-button skuy-profile-settings-button">
                                        <x-fa-icon name="gear" />
                                        <span>{{ __('navigation.settings') }}</span>
                                    </a>
                                </div>
                            @elseif (auth()->check())
                                <div class="skuy-profile-visitor-actions">
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

                                    @if ($canStartConversation)
                                        <form action="{{ route('messages.start', $user->username) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="skuy-button skuy-profile-message-button">
                                                <x-fa-icon name="comment-lines" />
                                                <span>{{ __('messages.message') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>



                    <div class="skuy-profile-details">
                        <p class="skuy-profile-bio{{ filled(trim($user->bio ?? '')) ? '' : ' skuy-profile-bio--empty' }}">{{ filled(trim($user->bio ?? '')) ? $user->bio : __('profile.no_bio') }}</p>

                        @if ($user->profileLinks->isNotEmpty())
                            <ul class="skuy-profile-links" aria-label="{{ __('profile.links') }}">
                                @foreach ($user->profileLinks as $profileLink)
                                    <li>
                                        <a href="{{ $profileLink->url }}" target="_blank" rel="noopener noreferrer nofollow ugc">
                                            <x-fa-icon name="link" aria-hidden="true" />
                                            <span>{{ $profileLink->label }}</span>
                                            <span class="sr-only">{{ __('profile.opens_new_tab') }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <ul class="skuy-profile-stats" role="list" aria-label="{{ __('profile.threads') }}, {{ __('profile.followers') }}, {{ __('profile.following') }}" data-profile-statistics>
                        <li class="skuy-profile-stat">
                            <span class="skuy-profile-stat__value">{{ $profilePostCount }}</span>
                            <span class="skuy-profile-stat__label">{{ __('profile.threads') }}</span>
                        </li>
                        <li>
                            <a href="{{ route('user.followers', $user) }}" class="skuy-profile-stat">
                                <span class="skuy-profile-stat__value">{{ $followersCount }}</span>
                                <span class="skuy-profile-stat__label">{{ __('profile.followers') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.following', $user) }}" class="skuy-profile-stat">
                                <span class="skuy-profile-stat__value">{{ $followingsCount }}</span>
                                <span class="skuy-profile-stat__label">{{ __('profile.following') }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <nav class="skuy-profile-tabs" data-profile-tabs aria-label="{{ __('profile.threads') }}, {{ __('profile.likes') }}">
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
                @auth
                    @if ($posts->count() > 0)
                        <div class="skuy-profile-post-list" data-profile-post-stream x-data="skuyPostMediaViewer">
                            @include('partials.feed-threads', ['posts' => $posts])
                            @include('partials.post-media-lightbox')
                        </div>
                    @else
                        <div class="skuy-profile-empty" role="status">
                            <span class="skuy-profile-empty__icon" aria-hidden="true"><x-fa-icon name="comment-lines" /></span>
                            <h2 class="skuy-profile-empty__title">
                                {{ $activeTab === 'likes' ? __('profile.empty.liked_threads_title') : __('profile.empty.threads_title') }}
                            </h2>
                            <p class="skuy-profile-empty__description">
                                {{ $activeTab === 'likes' ? __('profile.empty.liked_threads_description') : __('profile.empty.threads_description') }}
                            </p>
                        </div>
                    @endif
                @else
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
                                                    <x-verified-badge />
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

                                            @if (auth()->check() && $displayPost->bookmarked_by_me)
                                                <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="{{ __('threads.actions.remove_from_saved') }}" title="{{ __('threads.actions.remove_from_saved') }}" aria-pressed="true" class="skuy-action skuy-action--bookmark">
                                                        <x-fa-icon name="bookmark" weight="solid" class="text-xl" />
                                                    </button>
                                                </form>
                                            @elseif (auth()->check())
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
                @endauth

                @if ($posts->hasPages())
                    <div class="skuy-profile-pagination">
                        {{ $posts->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
