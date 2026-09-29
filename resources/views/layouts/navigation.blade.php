@php
    $user = auth()->user();
    $profileUrl = route('user.profile', $user->username);
    $isDashboard = request()->routeIs('dashboard');
    $isFeed = request()->routeIs('feed');
    $showDashboardNavigation = $user->hasVerifiedEmail() || $isDashboard;
    $unreadNotificationCount = (int) ($unreadNotificationCount ?? 0);
    $unreadMessageCount = (int) ($unreadMessageCount ?? 0);
    $displayUnreadNotificationCount = $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount;
    $displayUnreadMessageCount = $unreadMessageCount > 99 ? '99+' : $unreadMessageCount;
    $activityMobileLabel = $unreadNotificationCount > 0
        ? __('navigation.activity') . ', ' . __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount])
        : __('navigation.activity');
    $messagesMobileLabel = $unreadMessageCount > 0
        ? __('navigation.messages') . ', ' . __('navigation.unread_messages_count', ['count' => $unreadMessageCount])
        : __('navigation.messages');
    $brandHref = $showDashboardNavigation ? route('dashboard') : route('feed');
    $brandLabel = $showDashboardNavigation ? __('navigation.dashboard') : __('navigation.feed');
    $navigationItems = [];

    if ($showDashboardNavigation) {
        $navigationItems[] = ['key' => 'dashboard', 'href' => route('dashboard'), 'label' => __('navigation.dashboard'), 'active' => $isDashboard, 'icon' => 'gauge'];
    }

    $navigationItems = array_merge($navigationItems, [
        ['key' => 'feed', 'href' => route('feed'), 'label' => __('navigation.feed'), 'active' => request()->routeIs('feed'), 'icon' => 'house'],
        ['key' => 'search', 'href' => route('search'), 'label' => __('navigation.search'), 'active' => request()->routeIs('search'), 'icon' => 'magnifying-glass'],
        ['key' => 'activity', 'href' => route('activity.index'), 'label' => __('navigation.activity'), 'active' => request()->routeIs('activity.index'), 'icon' => 'bell'],
        ['key' => 'messages', 'href' => route('messages.index'), 'label' => __('navigation.messages'), 'active' => request()->routeIs('messages.*'), 'icon' => 'comment-lines'],
        ['key' => 'saved', 'href' => route('bookmarks.index'), 'label' => __('navigation.saved'), 'active' => request()->routeIs('bookmarks.index'), 'icon' => 'bookmark'],
        ['key' => 'profile', 'href' => $profileUrl, 'label' => __('navigation.profile'), 'active' => request()->routeIs('user.profile') && request()->route('username') === $user->username, 'icon' => 'user'],
    ]);
    $navigationGroups = [
        array_filter($navigationItems, static fn ($item) => in_array($item['key'], ['dashboard', 'feed', 'search'], true)),
        array_filter($navigationItems, static fn ($item) => in_array($item['key'], ['activity', 'messages', 'saved', 'profile'], true)),
    ];
    $bottomHomeIsDashboard = $isDashboard;
    $bottomHomeHref = $bottomHomeIsDashboard ? route('dashboard') : route('feed');
    $bottomHomeLabel = $bottomHomeIsDashboard ? __('navigation.dashboard') : __('navigation.feed');
    $bottomHomeIcon = $bottomHomeIsDashboard ? 'gauge' : 'house';
    $bottomHomeCurrent = $bottomHomeIsDashboard || $isFeed;
    $composerHref = $isFeed ? '#composer' : route('feed').'#composer';
@endphp

<div
    x-data="{
        open: false,
        sidebarOpen: window.matchMedia('(min-width: 1280px)').matches,
        navigationQuery: null,
        init() {
            this.navigationQuery = window.matchMedia('(min-width: 1280px)');
            this.navigationQuery.addEventListener('change', event => {
                this.sidebarOpen = event.matches;
            });
        }
    }"
    @keydown.escape.window="if (window.innerWidth < 1280) sidebarOpen = false"
    class="skuy-navigation-shell"
>
<nav class="skuy-topbar">
    <div class="skuy-topbar__inner">
        <div class="skuy-topbar__brand-group">
            <button
                @click="sidebarOpen = !sidebarOpen"
                :aria-label="sidebarOpen ? '{{ __('navigation.close_navigation_menu') }}' : '{{ __('navigation.open_navigation_menu') }}'"
                :aria-expanded="sidebarOpen"
                aria-controls="mobile-navigation"
                type="button"
                class="skuy-icon-button skuy-navigation-trigger xl:hidden"
            >
                <x-fa-icon name="bars" weight="solid" class="text-xl" />
                <span class="sr-only">{{ __('navigation.open_navigation_menu') }}</span>
            </button>

            <a href="{{ $brandHref }}" class="skuy-brand" aria-label="SkuySocial, {{ $brandLabel }}">
                <span class="skuy-brand__accent">Skuy</span><span>Social</span>
            </a>
        </div>

        <div class="skuy-topbar__actions">
            <x-language-switcher variant="app" />

            <button
                @click="darkMode = !darkMode"
                :aria-label="darkMode ? '{{ __('navigation.enable_light_mode') }}' : '{{ __('navigation.enable_dark_mode') }}'"
                :aria-pressed="darkMode"
                type="button"
                class="skuy-icon-button"
            >
                <x-fa-icon x-show="!darkMode" name="moon" weight="solid" class="text-xl" />
                <x-fa-icon x-show="darkMode" style="display: none;" name="sun" weight="solid" class="text-xl" />
                <span class="sr-only" x-text="darkMode ? '{{ __('navigation.dark') }}' : '{{ __('navigation.light') }}'"></span>
            </button>

            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            id="desktop-profile-trigger"
                            x-ref="trigger"
                            type="button"
                            class="skuy-profile-trigger"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            :aria-expanded="open"
                            aria-controls="desktop-profile-menu"
                        >
                            <span class="skuy-profile-trigger__name">{{ Auth::user()->name }}</span>
                            <x-fa-icon name="chevron-down" class="text-base" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div id="desktop-profile-menu" role="menu" aria-labelledby="desktop-profile-trigger" class="skuy-menu-surface">
                            <x-dropdown-link :href="route('user.profile', auth()->user()->username)" class="skuy-menu-link" role="menuitem">
                                {{ __('navigation.my_profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit', $user->username)" class="skuy-menu-link" role="menuitem">
                                {{ __('navigation.settings') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}" data-onesignal-logout>
                                @csrf
                                <button type="submit" class="skuy-menu-link min-h-[44px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]" role="menuitem">
                                    {{ __('navigation.sign_out') }}
                                </button>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center sm:hidden">
                <button
                    @click="open = !open"
                    :aria-label="open ? '{{ __('navigation.close_profile_menu') }}' : '{{ __('navigation.open_profile_menu') }}'"
                    :aria-expanded="open"
                    aria-controls="mobile-profile-menu"
                    type="button"
                    class="skuy-icon-button"
                >
                    <x-fa-icon x-show="!open" name="user" class="skuy-profile-icon text-xl" />
                    <x-fa-icon x-show="open" style="display: none;" name="xmark" weight="solid" class="skuy-profile-icon text-xl" />
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-profile-menu" x-show="open" x-transition style="display: none;" class="border-t border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] sm:hidden">
        <div class="mx-auto w-[min(100%-24px,1280px)] py-3">
            <div class="mb-2 px-2">
                <div class="break-words text-sm font-bold text-[var(--skuy-text-primary)]">{{ Auth::user()->name }}</div>
                <div class="break-all text-xs text-[var(--skuy-text-muted)]">{{ Auth::user()->email }}</div>
            </div>

            <div class="skuy-menu-surface">
                <a href="{{ route('user.profile', auth()->user()->username) }}" class="skuy-menu-link" aria-current="{{ request()->routeIs('user.profile') && request()->route('username') === auth()->user()->username ? 'page' : 'false' }}">
                    {{ __('navigation.my_profile') }}
                </a>
                <a href="{{ route('bookmarks.index') }}" class="skuy-menu-link" aria-current="{{ request()->routeIs('bookmarks.index') ? 'page' : 'false' }}">
                    {{ __('navigation.saved') }}
                </a>
                <a href="{{ route('messages.index') }}" class="skuy-menu-link" aria-current="{{ request()->routeIs('messages.*') ? 'page' : 'false' }}">
                    {{ __('navigation.messages') }}
                </a>
                <a href="{{ route('profile.edit', $user->username) }}" class="skuy-menu-link">
                    {{ __('navigation.settings') }}
                </a>
                <form method="POST" action="{{ route('logout') }}" data-onesignal-logout>
                    @csrf
                    <button type="submit" class="skuy-menu-link min-h-[44px] text-[var(--skuy-danger)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                        {{ __('navigation.sign_out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<aside
    id="mobile-navigation"
    x-show="sidebarOpen"
    x-transition
    style="display: none;"
    class="skuy-app-rail"
    aria-label="{{ __('home.public.primary_navigation') }}"
>
    <div class="skuy-app-rail__inner">
        <p class="skuy-app-nav__eyebrow">{{ __('home.public.primary_navigation') }}</p>

        <nav class="skuy-app-nav" aria-label="{{ __('home.public.primary_navigation') }}">
            @foreach ($navigationGroups as $navigationGroup)
                @if ($navigationGroup)
                    <div class="skuy-app-nav__group">
                        @foreach ($navigationGroup as $item)
                            <a
                                href="{{ $item['href'] }}"
                                aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                                @click="sidebarOpen = false"
                                class="skuy-app-nav__link"
                            >
                                <x-fa-icon :name="$item['icon']" :weight="$item['active'] ? 'solid' : 'regular'" class="skuy-app-nav__icon" />
                                <span class="skuy-app-nav__label">{{ $item['label'] }}</span>

                                @if ($item['key'] === 'activity' && $unreadNotificationCount > 0)
                                    <span
                                        class="skuy-feed-nav__badge"
                                        data-unread-notifications-badge
                                        aria-label="{{ __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount]) }}"
                                    >{{ $displayUnreadNotificationCount }}</span>
                                @elseif ($item['key'] === 'messages' && $unreadMessageCount > 0)
                                    <span
                                        class="skuy-feed-nav__badge"
                                        data-unread-messages-badge
                                        aria-label="{{ __('navigation.unread_messages_count', ['count' => $unreadMessageCount]) }}"
                                    >{{ $displayUnreadMessageCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>

        @if ($user->canPerformSocialWrites())
            <a href="{{ route('feed') }}#composer" class="skuy-app-rail__compose">
                <x-fa-icon name="plus" weight="solid" class="text-xl" />
                <span>{{ __('navigation.create_thread') }}</span>
            </a>
        @endif
    </div>
</aside>

<nav class="skuy-mobile-nav" aria-label="{{ __('home.public.primary_navigation') }}">
    <a href="{{ $bottomHomeHref }}" aria-label="{{ $bottomHomeLabel }}" aria-current="{{ $bottomHomeCurrent ? 'page' : 'false' }}" class="skuy-mobile-nav__item" data-mobile-nav-key="home">
        <x-fa-icon :name="$bottomHomeIcon" :weight="$bottomHomeCurrent ? 'solid' : 'regular'" class="text-xl" />
        <span>{{ $bottomHomeLabel }}</span>
    </a>

    <a href="{{ route('search') }}" aria-label="{{ __('navigation.search') }}" aria-current="{{ request()->routeIs('search') ? 'page' : 'false' }}" class="skuy-mobile-nav__item" data-mobile-nav-key="search">
        <x-fa-icon name="magnifying-glass" :weight="request()->routeIs('search') ? 'solid' : 'regular'" class="text-xl" />
        <span>{{ __('navigation.search') }}</span>
    </a>

    @if ($user->canPerformSocialWrites())
        <a href="{{ $composerHref }}" aria-label="{{ __('navigation.create_thread') }}" class="skuy-mobile-nav__item skuy-mobile-nav__item--compose" data-mobile-nav-key="create">
            <x-fa-icon name="plus" weight="solid" class="text-xl" />
            <span class="sr-only">{{ __('navigation.create_thread') }}</span>
        </a>
    @else
        <a href="{{ route('feed') }}" aria-label="{{ __('navigation.feed') }}" class="skuy-mobile-nav__item">
            <x-fa-icon name="house" :weight="request()->routeIs('feed') ? 'solid' : 'regular'" class="text-xl" />
            <span>{{ __('navigation.feed') }}</span>
        </a>
    @endif

    <a href="{{ route('activity.index') }}" aria-label="{{ $activityMobileLabel }}" aria-current="{{ request()->routeIs('activity.index') ? 'page' : 'false' }}" class="skuy-mobile-nav__item" data-mobile-nav-key="activity">
        <span class="skuy-mobile-nav__icon-wrap">
            <x-fa-icon name="bell" :weight="request()->routeIs('activity.index') ? 'solid' : 'regular'" class="text-xl" />
            @if ($unreadNotificationCount > 0)
                <span
                    class="skuy-feed-nav__badge skuy-mobile-nav__badge"
                    data-unread-notifications-badge
                    aria-label="{{ __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount]) }}"
                >{{ $displayUnreadNotificationCount }}</span>
            @endif
        </span>
        <span>{{ __('navigation.activity') }}</span>
    </a>

    <a href="{{ route('messages.index') }}" aria-label="{{ $messagesMobileLabel }}" aria-current="{{ request()->routeIs('messages.*') ? 'page' : 'false' }}" class="skuy-mobile-nav__item" data-mobile-nav-key="messages">
        <span class="skuy-mobile-nav__icon-wrap">
            <x-fa-icon name="comment-lines" :weight="request()->routeIs('messages.*') ? 'solid' : 'regular'" class="text-xl" />
            @if ($unreadMessageCount > 0)
                <span
                    class="skuy-feed-nav__badge skuy-mobile-nav__badge"
                    data-unread-messages-badge
                    aria-label="{{ __('navigation.unread_messages_count', ['count' => $unreadMessageCount]) }}"
                >{{ $displayUnreadMessageCount }}</span>
            @endif
        </span>
        <span>{{ __('navigation.messages') }}</span>
    </a>
</nav>
</div>
