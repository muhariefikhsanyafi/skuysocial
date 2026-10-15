<x-app-layout>
    @php
        $user = auth()->user();
        $canCreateThread = $user->canPerformSocialWrites();
        $canClaimDailyCheckIn = $canCreateThread && ! $dailyCheckIn->checkedIn;
        $formattedDailyCheckInDate = \Carbon\CarbonImmutable::parse(
            $dailyCheckIn->date,
            (string) config('app.timezone'),
        )->locale(app()->getLocale())->translatedFormat('j F Y');
    @endphp

    <div class="skuy-dashboard-page" data-flow-dashboard="home">
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

                <a href="{{ route('profile.edit', $user->username) }}" class="skuy-dashboard-account__link">
                    {{ __('home.dashboard.account_settings') }}
                </a>
            </aside>

            <section
                class="skuy-dashboard-checkin"
                aria-labelledby="daily-checkin-title"
                data-daily-checkin-date="{{ $dailyCheckIn->date }}"
            >
                <div class="skuy-dashboard-checkin__content">
                    <div class="min-w-0">
                        <p class="skuy-dashboard-kicker">{{ __('home.dashboard.checkin.kicker') }}</p>
                        <h2 id="daily-checkin-title" class="skuy-dashboard-checkin__title">
                            {{ __('home.dashboard.checkin.title') }}
                        </h2>
                        <p class="skuy-dashboard-checkin__description">
                            {{ __('home.dashboard.checkin.description', ['amount' => \Illuminate\Support\Number::format($dailyCheckIn->reward, locale: app()->getLocale())]) }}
                        </p>
                        <p class="skuy-dashboard-checkin__day">
                            <span>{{ __('home.dashboard.checkin.application_day') }}:</span>
                            <time datetime="{{ $dailyCheckIn->date }}">{{ $formattedDailyCheckInDate }}</time>
                        </p>
                        <p class="skuy-dashboard-checkin__timezone-note">
                            {{ __('home.dashboard.checkin.timezone_note') }}
                        </p>
                    </div>

                    <div class="skuy-dashboard-checkin__reward">
                        <span>{{ __('home.dashboard.checkin.reward_label') }}</span>
                        <strong>+{{ \Illuminate\Support\Number::format($dailyCheckIn->reward, locale: app()->getLocale()) }} {{ __('points.unit') }}</strong>
                    </div>
                </div>

                <div class="skuy-dashboard-checkin__action">
                    <div class="min-w-0">
                        @if ($dailyCheckIn->checkedIn)
                            <p class="skuy-dashboard-checkin__state">{{ __('home.dashboard.checkin.checked_in') }}</p>

                            @if (session()->has('daily_checkin_claimed') && session('daily_checkin_claimed'))
                                <p class="skuy-dashboard-checkin__feedback" role="status" aria-live="polite">
                                    {{ __('home.dashboard.checkin.received', ['amount' => \Illuminate\Support\Number::format((int) session('daily_checkin_awarded'), locale: app()->getLocale())]) }}
                                </p>
                            @else
                                <p class="skuy-dashboard-checkin__feedback" role="status" aria-live="polite">
                                    {{ __('home.dashboard.checkin.already_claimed') }}
                                </p>
                            @endif
                        @elseif (! $canCreateThread)
                            <p class="skuy-dashboard-checkin__feedback" role="status" aria-live="polite">
                                {{ __('moderation.denials.social_write') }}
                            </p>
                        @else
                            <p class="skuy-dashboard-checkin__feedback" role="status" aria-live="polite">
                                {{ __('home.dashboard.checkin.available') }}
                            </p>
                        @endif

                        <p class="skuy-dashboard-checkin__balance" data-points-balance="{{ $dailyCheckIn->balance }}">
                            <span>{{ __('points.wallet.balance_label') }}:</span>
                            <strong>{{ \Illuminate\Support\Number::format($dailyCheckIn->balance, locale: app()->getLocale()) }} {{ __('points.unit') }}</strong>
                        </p>
                    </div>

                    @if ($canClaimDailyCheckIn)
                        <form method="POST" action="{{ route('daily-checkin.store') }}" class="skuy-dashboard-checkin__form">
                            @csrf
                            <button type="submit" class="skuy-button skuy-button--primary skuy-dashboard-checkin__button">
                                {{ __('home.dashboard.checkin.claim', ['amount' => \Illuminate\Support\Number::format($dailyCheckIn->reward, locale: app()->getLocale())]) }}
                            </button>
                        </form>
                    @endif
                </div>
            </section>

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
                            <x-fa-icon name="house" class="text-xl" />
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_feed') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_feed_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('search') }}" class="skuy-dashboard-route skuy-dashboard-route--search">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <x-fa-icon name="magnifying-glass" class="text-xl" />
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_search') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_search_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('activity.index') }}" class="skuy-dashboard-route skuy-dashboard-route--activity">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <x-fa-icon name="bell" class="text-xl" />
                        </span>
                        <span class="skuy-dashboard-route__copy">
                            <span class="skuy-dashboard-route__label">{{ __('home.dashboard.route_activity') }}</span>
                            <span class="skuy-dashboard-route__description">{{ __('home.dashboard.route_activity_description') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('bookmarks.index') }}" class="skuy-dashboard-route skuy-dashboard-route--saved">
                        <span class="skuy-dashboard-route__icon" aria-hidden="true">
                            <x-fa-icon name="bookmark" class="text-xl" />
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
