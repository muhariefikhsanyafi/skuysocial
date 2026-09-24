<x-app-layout>
    @php
        $user = auth()->user();
        $canCreateThread = $user->canPerformSocialWrites();
    @endphp

    <div class="skuy-dashboard-page">
        <div class="skuy-dashboard-frame skuy-dashboard-frame--bento">
            <section class="skuy-dashboard-welcome" aria-labelledby="dashboard-title">
                <div>
                    <p class="skuy-dashboard-kicker">{{ __('home.dashboard.title') }}</p>
                    <h1 id="dashboard-title" class="skuy-dashboard-title">
                        {{ __('home.dashboard.greeting', ['name' => $user->name]) }}
                    </h1>
                    <p class="skuy-dashboard-welcome__copy">{{ __('home.dashboard.welcome') }}</p>
                </div>

                <div class="skuy-dashboard-welcome__action">
                    @if ($canCreateThread)
                        <a href="{{ route('feed') }}#composer" class="skuy-button skuy-button--primary skuy-dashboard-primary-action">
                            {{ __('home.dashboard.create_thread') }}
                        </a>
                    @else
                        <a href="{{ route('feed') }}" class="skuy-button skuy-button--primary skuy-dashboard-primary-action">
                            {{ __('home.dashboard.open_feed') }}
                        </a>
                    @endif
                </div>
            </section>

            <aside class="skuy-dashboard-account" aria-labelledby="dashboard-account-title">
                <div class="skuy-dashboard-account__identity">
                    @if ($user->avatar)
                        <div class="skuy-dashboard-avatar">
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                        </div>
                    @else
                        <div class="skuy-dashboard-avatar" aria-label="{{ __('threads.media.avatar_of', ['name' => $user->name]) }}">
                            <span aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        </div>
                    @endif

                    <div class="min-w-0">
                        <p class="skuy-dashboard-account__eyebrow">{{ __('home.dashboard.account') }}</p>
                        <h2 id="dashboard-account-title" class="skuy-dashboard-account__name">{{ $user->name }}</h2>
                        <p class="skuy-dashboard-account__username">{{ '@' . $user->username }}</p>
                    </div>
                </div>

                <dl class="skuy-dashboard-account__details">
                    <div>
                        <dt>{{ __('home.dashboard.email') }}</dt>
                        <dd>{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('home.dashboard.username') }}</dt>
                        <dd>{{ '@' . $user->username }}</dd>
                    </div>
                </dl>

                <a href="{{ route('profile.edit') }}" class="skuy-dashboard-account__link">
                    {{ __('home.dashboard.account_settings') }}
                </a>
            </aside>

            <section class="skuy-dashboard-routes" aria-labelledby="dashboard-routes-title">
                <div class="skuy-dashboard-routes__heading">
                    <div>
                        <p class="skuy-dashboard-kicker">{{ __('home.dashboard.route_kicker') }}</p>
                        <h2 id="dashboard-routes-title" class="skuy-dashboard-section-title">{{ __('home.dashboard.route_heading') }}</h2>
                    </div>
                    <p class="skuy-dashboard-routes__description">{{ __('home.dashboard.route_description') }}</p>
                </div>

                <nav class="skuy-dashboard-route-list" aria-label="{{ __('home.dashboard.route_heading') }}">
                    <a href="{{ route('feed') }}" class="skuy-dashboard-route skuy-dashboard-route--feed">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 11 9-8 9 8M5 10v10h14V10M9 20v-6h6v6"></path></svg>
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_feed') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_feed_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('search') }}" class="skuy-dashboard-route skuy-dashboard-route--search">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"></circle><path stroke-linecap="round" d="m16 16 5 5"></path></svg>
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_search') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_search_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('activity.index') }}" class="skuy-dashboard-route skuy-dashboard-route--activity">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 1 1-2.34-5.66"></path><path stroke-linecap="round" stroke-linejoin="round" d="M20 4v6h-6"></path></svg>
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_activity') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_activity_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('bookmarks.index') }}" class="skuy-dashboard-route skuy-dashboard-route--saved">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-3-6 3V4Z"></path></svg>
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_saved') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_saved_description') }}</span>
                        </span>
                    </a>
                </nav>
            </section>
        </div>
    </div>
</x-app-layout>
