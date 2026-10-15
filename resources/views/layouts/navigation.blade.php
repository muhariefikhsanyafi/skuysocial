@php
    $user = auth()->user();
    $profileUrl = route('user.profile', $user->username);
    $isDashboard = request()->routeIs('dashboard');
    $isFeed = request()->routeIs('feed');
    $isProfileFlow = request()->routeIs('user.profile', 'user.edit');
    $isModernSocialShell = $isFeed || $isProfileFlow || request()->routeIs('search', 'bookmarks.index');
    $showDashboardNavigation = $user->hasVerifiedEmail() || $isDashboard;
    $unreadNotificationCount = (int) ($unreadNotificationCount ?? 0);
    $unreadMessageCount = (int) ($unreadMessageCount ?? 0);
    $unreadAnnouncementCount = (int) ($unreadAnnouncementCount ?? 0);
    $displayUnreadNotificationCount = $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount;
    $displayUnreadMessageCount = $unreadMessageCount > 99 ? '99+' : $unreadMessageCount;
    $displayUnreadAnnouncementCount = $unreadAnnouncementCount > 99 ? '99+' : $unreadAnnouncementCount;
    $activityMobileLabel = $unreadNotificationCount > 0
        ? __('navigation.activity') . ', ' . __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount])
        : __('navigation.activity');
    $announcementsMobileLabel = $unreadAnnouncementCount > 0
        ? __('navigation.announcements') . ', ' . __('navigation.unread_announcements_count', ['count' => $unreadAnnouncementCount])
        : __('navigation.announcements');
    $messagesMobileLabel = $unreadMessageCount > 0
        ? __('navigation.messages') . ', ' . __('navigation.unread_messages_count', ['count' => $unreadMessageCount])
        : __('navigation.messages');
    $brandHref = $showDashboardNavigation ? route('dashboard') : route('feed');
    $brandLabel = $showDashboardNavigation ? __('navigation.dashboard') : __('navigation.feed');
    $responsiveNavigationItems = $showDashboardNavigation
        ? [['key' => 'dashboard', 'href' => route('dashboard'), 'label' => __('navigation.dashboard'), 'active' => $isDashboard, 'icon' => 'gauge', 'surfaces' => 'mobile-secondary']]
        : [];
    $responsiveNavigationItems = array_merge($responsiveNavigationItems, [
        ['key' => 'feed', 'href' => route('feed'), 'label' => __('navigation.feed'), 'active' => $isFeed, 'icon' => 'house', 'surfaces' => 'desktop-primary'],
        ['key' => 'search', 'href' => route('search'), 'label' => __('navigation.search'), 'active' => request()->routeIs('search'), 'icon' => 'magnifying-glass', 'surfaces' => 'desktop-primary'],
        ['key' => 'activity', 'href' => route('activity.index'), 'label' => __('navigation.activity'), 'active' => request()->routeIs('activity.index'), 'icon' => 'bell', 'surfaces' => 'desktop-primary'],
        ['key' => 'messages', 'href' => route('messages.index'), 'label' => __('navigation.messages'), 'active' => request()->routeIs('messages.*'), 'icon' => 'comment-lines', 'surfaces' => 'desktop-primary'],
        ['key' => 'saved', 'href' => route('bookmarks.index'), 'label' => __('navigation.saved'), 'active' => request()->routeIs('bookmarks.index'), 'icon' => 'bookmark', 'surfaces' => 'desktop-primary mobile-secondary'],
        ['key' => 'announcements', 'href' => route('announcements.index'), 'label' => __('navigation.announcements'), 'active' => request()->routeIs('announcements.*'), 'icon' => 'bullhorn', 'surfaces' => 'mobile-secondary'],
    ]);
    $bottomHomeHref = route('feed');
    $bottomHomeLabel = __('navigation.home');
    $bottomHomeIcon = 'house';
    $bottomHomeCurrent = $isFeed;
    $composerHref = $isFeed ? '#composer' : route('feed').'#composer';
@endphp

<div
    x-data="{
        accountMenuOpen: false,
        desktopAccountMenuOpen: false,
        sidebarOpen: window.matchMedia('(min-width: 1280px)').matches,
        isDesktop: window.matchMedia('(min-width: 1280px)').matches,
        navigationQuery: null,
        accountMenuQuery: null,
        navigationReturnFocus: null,
        accountReturnFocus: null,
        previousBodyOverflow: '',
        overlayScrollLocked: false,
        init() {
            this.accountMenuQuery = window.matchMedia('(min-width: 640px)');
            this.accountMenuQuery.addEventListener('change', event => {
                if (!event.matches || !this.accountMenuOpen) return;

                this.closeAccountMenu(false);
                this.$nextTick(() => document.querySelector('#desktop-profile-trigger')?.focus());
            });
            this.navigationQuery = window.matchMedia('(min-width: 1280px)');
            this.navigationQuery.addEventListener('change', event => {
                this.isDesktop = event.matches;
                this.sidebarOpen = event.matches;
                this.syncOverlayScrollLock();
            });
        },
        syncOverlayScrollLock() {
            const shouldLock = (!this.isDesktop &amp;&amp; this.sidebarOpen) || this.accountMenuOpen;
            if (shouldLock &amp;&amp; !this.overlayScrollLocked) {
                this.previousBodyOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                this.overlayScrollLocked = true;
            } else if (!shouldLock &amp;&amp; this.overlayScrollLocked) {
                document.body.style.overflow = this.previousBodyOverflow;
                this.overlayScrollLocked = false;
            }
        },
        trapOverlayFocus(event, container) {
            if (event.key !== 'Tab') return;

            const focusable = [...container.querySelectorAll('a[href], button:not(:disabled)')]
                .filter(element => element.getClientRects().length > 0);
            if (focusable.length === 0) {
                event.preventDefault();
                container.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey &amp;&amp; (document.activeElement === first || !container.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey &amp;&amp; (document.activeElement === last || !container.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        },
        toggleNavigation() {
            if (this.isDesktop) return;
            if (this.sidebarOpen) {
                this.closeNavigation();
                return;
            }

            this.closeAccountMenu(false);
            this.navigationReturnFocus = document.activeElement;
            this.sidebarOpen = true;
            this.syncOverlayScrollLock();
            this.$nextTick(() => this.$refs.navigationClose?.focus());
        },
        closeNavigation(restoreFocus = true) {
            if (this.isDesktop) return;
            this.sidebarOpen = false;
            this.syncOverlayScrollLock();
            if (restoreFocus &amp;&amp; this.navigationReturnFocus?.isConnected) {
                this.$nextTick(() => this.navigationReturnFocus.focus());
            }
        },
        toggleAccountMenu() {
            if (this.accountMenuOpen) {
                this.closeAccountMenu();
                return;
            }

            this.closeNavigation(false);
            this.accountReturnFocus = document.activeElement;
            this.accountMenuOpen = true;
            this.syncOverlayScrollLock();
            this.$nextTick(() => this.$refs.accountClose?.focus());
        },
        closeAccountMenu(restoreFocus = true) {
            this.accountMenuOpen = false;
            this.syncOverlayScrollLock();
            if (restoreFocus &amp;&amp; this.accountReturnFocus?.isConnected) {
                this.$nextTick(() => this.accountReturnFocus.focus());
            }
        }
    }"
    @desktop-profile-menu-toggle.window="desktopAccountMenuOpen = Boolean($event.detail)"
    x-effect="$el.closest('.skuy-app-shell')?.classList.toggle('skuy-desktop-account-menu-open', desktopAccountMenuOpen)"
    @keydown.escape.window="if (accountMenuOpen) closeAccountMenu(); else if (!isDesktop &amp;&amp; sidebarOpen) closeNavigation()"
    class="skuy-navigation-shell"
    data-skuy-prism-shell
>
<nav class="skuy-topbar">
    <div class="skuy-topbar__inner">
        <div class="skuy-topbar__brand-group">
            <button
                @click="toggleNavigation()"
                :aria-label="sidebarOpen &amp;&amp; !isDesktop ? '{{ __('navigation.close_navigation_menu') }}' : '{{ __('navigation.open_navigation_menu') }}'"
                :aria-expanded="(sidebarOpen &amp;&amp; !isDesktop).toString()"
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

        @if ($isModernSocialShell)
            <form action="{{ route('search') }}" method="GET" role="search" class="skuy-flow-topbar-search">
                <x-fa-icon name="magnifying-glass" />
                <label for="flow-navigation-search" class="sr-only">{{ __('navigation.search') }}</label>
                <input id="flow-navigation-search" type="search" name="q" placeholder="{{ __('navigation.search_placeholder') }}" maxlength="100" autocomplete="off">
                <button type="submit" aria-label="{{ __('navigation.search') }}">
                    <x-fa-icon name="arrow-right" />
                </button>
            </form>
        @endif

        <div class="skuy-topbar__actions">
            @if ($isModernSocialShell)
                <div class="skuy-flow-topbar-shortcuts">
                    @if ($user->canPerformSocialWrites())
                        <a href="{{ $composerHref }}" class="skuy-icon-button" aria-label="{{ __('navigation.create_thread') }}" title="{{ __('navigation.create_thread') }}">
                            <x-fa-icon name="plus" weight="solid" />
                        </a>
                    @endif
                    <a href="{{ route('messages.index') }}" class="skuy-icon-button" aria-label="{{ $messagesMobileLabel }}" title="{{ __('navigation.messages') }}">
                        <x-fa-icon name="comment-lines" />
                        @if ($unreadMessageCount > 0)
                            <span class="skuy-flow-topbar-badge" data-unread-messages-badge>{{ $displayUnreadMessageCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('activity.index') }}" class="skuy-icon-button" aria-label="{{ $activityMobileLabel }}" title="{{ __('navigation.activity') }}">
                        <x-fa-icon name="bell" />
                        @if ($unreadNotificationCount > 0)
                            <span class="skuy-flow-topbar-badge" data-unread-notifications-badge aria-label="{{ __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount]) }}">{{ $displayUnreadNotificationCount }}</span>
                        @endif
                    </a>
                </div>
            @endif
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
                <x-dropdown id="desktop-profile-dropdown" align="right" width="48">
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
                            @if ($isModernSocialShell)
                                <span class="skuy-flow-topbar-avatar" aria-hidden="true">
                                    @if ($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="">
                                    @else
                                        {{ mb_substr($user->name, 0, 1) }}
                                    @endif
                                </span>
                                <span class="sr-only">{{ Auth::user()->name }}</span>
                            @else
                                <span class="skuy-profile-trigger__name">{{ Auth::user()->name }}</span>
                                <x-fa-icon name="chevron-down" class="text-base" />
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div id="desktop-profile-menu" role="menu" aria-labelledby="desktop-profile-trigger" class="skuy-menu-surface">
                            <x-dropdown-link :href="route('user.profile', auth()->user()->username)" class="skuy-menu-link" role="menuitem" data-account-menu-key="profile">
                                {{ __('navigation.my_profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit', $user->username)" class="skuy-menu-link" role="menuitem" data-account-menu-key="settings">
                                {{ __('navigation.settings') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}" data-onesignal-logout>
                                @csrf
                                <button type="submit" class="skuy-menu-link min-h-[44px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]" role="menuitem" data-account-menu-key="sign-out">
                                    {{ __('navigation.sign_out') }}
                                </button>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center sm:hidden">
                <button
                    @click="toggleAccountMenu()"
                    x-ref="accountTrigger"
                    :aria-label="accountMenuOpen ? '{{ __('navigation.close_profile_menu') }}' : '{{ __('navigation.open_profile_menu') }}'"
                    :aria-expanded="accountMenuOpen.toString()"
                    aria-controls="mobile-profile-menu"
                    type="button"
                    class="skuy-icon-button"
                >
                    <x-fa-icon x-show="!accountMenuOpen" name="user" class="skuy-profile-icon text-xl" />
                    <x-fa-icon x-show="accountMenuOpen" style="display: none;" name="xmark" weight="solid" class="skuy-profile-icon text-xl" />
                </button>
            </div>
        </div>
    </div>
</nav>

<div
    id="mobile-profile-menu-backdrop"
    x-show="accountMenuOpen"
    x-transition.opacity
    style="display: none;"
    class="skuy-mobile-account-scrim sm:hidden"
    @click.self="closeAccountMenu()"
    @keydown.escape.stop="closeAccountMenu()"
    :aria-hidden="(!accountMenuOpen).toString()"
>
    <section
        id="mobile-profile-menu"
        x-ref="accountDialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobile-profile-menu-title"
        class="skuy-mobile-account-menu"
        @keydown.tab="trapOverlayFocus($event, $el)"
    >
        <header class="skuy-mobile-account-menu__header">
            <div class="skuy-mobile-account-menu__identity" data-account-identity>
                <h2 id="mobile-profile-menu-title">{{ Auth::user()->name }}</h2>
                <p>{{ Auth::user()->email }}</p>
            </div>
            <button
                type="button"
                x-ref="accountClose"
                data-account-close
                class="skuy-icon-button"
                aria-label="{{ __('navigation.close_profile_menu') }}"
                @click="closeAccountMenu()"
            >
                <x-fa-icon name="xmark" weight="solid" class="text-lg" />
            </button>
        </header>

        <nav class="skuy-mobile-account-menu__items" aria-label="{{ __('navigation.my_profile') }}">
            <a href="{{ route('user.profile', $user->username) }}" class="skuy-menu-link" data-account-menu-key="profile" aria-current="{{ request()->routeIs('user.profile') && request()->route('username') === $user->username ? 'page' : 'false' }}" @click="closeAccountMenu(false)">{{ __('navigation.my_profile') }}</a>
            <a href="{{ route('profile.edit', $user->username) }}" class="skuy-menu-link" data-account-menu-key="settings" @click="closeAccountMenu(false)">{{ __('navigation.settings') }}</a>
            <form method="POST" action="{{ route('logout') }}" data-onesignal-logout>
                @csrf
                <button type="submit" class="skuy-menu-link min-h-[44px] text-[var(--skuy-danger)]" data-account-menu-key="sign-out">{{ __('navigation.sign_out') }}</button>
            </form>
        </nav>
    </section>
</div>

<div x-show="!isDesktop &amp;&amp; sidebarOpen" x-transition.opacity style="display: none;" class="skuy-app-rail__backdrop" aria-hidden="true" @click="closeNavigation()"></div>

<aside
    id="mobile-navigation"
    x-show="sidebarOpen"
    x-transition
    style="display: none;"
    class="skuy-app-rail"
    data-skuy-desktop-rail
    x-ref="navigationDialog"
    :role="isDesktop ? 'complementary' : 'dialog'"
    :aria-modal="isDesktop ? null : 'true'"
    :aria-label="isDesktop ? '{{ __('home.public.primary_navigation') }}' : '{{ __('navigation.secondary_navigation') }}'"
    @keydown.tab="if (!isDesktop) trapOverlayFocus($event, $el)"
    @keydown.escape.stop="if (!isDesktop) closeNavigation()"
>
    <div class="skuy-app-rail__inner">
        <div class="skuy-app-rail__mobile-heading">
            <div>
                <a href="{{ $brandHref }}" class="skuy-app-rail__mobile-brand" aria-label="SkuySocial, {{ $brandLabel }}">
                    <span class="skuy-brand__accent">Skuy</span><span>Social</span>
                </a>
                <p>{{ __('navigation.secondary_navigation') }}</p>
            </div>
            <button type="button" x-ref="navigationClose" data-drawer-close class="skuy-icon-button" aria-label="{{ __('navigation.close_navigation_menu') }}" @click="closeNavigation()">
                <x-fa-icon name="xmark" weight="solid" class="text-lg" />
            </button>
        </div>

        <a href="{{ $brandHref }}" class="skuy-app-rail__brand" aria-label="SkuySocial, {{ $brandLabel }}">
            <span class="skuy-brand__accent">Skuy</span><span>Social</span>
        </a>
        <p class="skuy-app-nav__eyebrow">{{ __('home.public.primary_navigation') }}</p>

        <nav class="skuy-app-nav skuy-app-nav--responsive" :aria-label="isDesktop ? '{{ __('home.public.primary_navigation') }}' : '{{ __('navigation.secondary_navigation') }}'" data-navigation-kind="responsive" data-rail-slot="desktop-primary">
            <div class="skuy-app-nav__group">
                @foreach ($responsiveNavigationItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        data-nav-key="{{ $item['key'] }}"
                        data-nav-surfaces="{{ $item['surfaces'] }}"
                        @if ($item['key'] === 'feed' && $isProfileFlow) data-nav-context-active="true" @endif
                        aria-label="{{ $item['key'] === 'activity' && $unreadNotificationCount > 0 ? $activityMobileLabel : ($item['key'] === 'messages' && $unreadMessageCount > 0 ? $messagesMobileLabel : ($item['key'] === 'announcements' && $unreadAnnouncementCount > 0 ? $announcementsMobileLabel : $item['label'])) }}"
                        aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                        @click="closeNavigation(false)"
                        class="skuy-app-nav__link"
                    >
                        <x-fa-icon :name="$item['icon']" :weight="$isModernSocialShell || $item['active'] ? 'solid' : 'regular'" class="skuy-app-nav__icon" />
                        <span class="skuy-app-nav__label">{{ $item['label'] }}</span>
                        @if ($item['key'] === 'activity' && $unreadNotificationCount > 0)
                            <span class="skuy-feed-nav__badge" data-unread-notifications-badge aria-label="{{ __('navigation.unread_notifications_count', ['count' => $unreadNotificationCount]) }}">{{ $displayUnreadNotificationCount }}</span>
                        @elseif ($item['key'] === 'messages' && $unreadMessageCount > 0)
                            <span class="skuy-feed-nav__badge" data-unread-messages-badge aria-label="{{ __('navigation.unread_messages_count', ['count' => $unreadMessageCount]) }}">{{ $displayUnreadMessageCount }}</span>
                        @elseif ($item['key'] === 'announcements' && $unreadAnnouncementCount > 0)
                            <span class="skuy-feed-nav__badge" aria-label="{{ __('announcements.unread') }}: {{ $unreadAnnouncementCount }}">{{ $displayUnreadAnnouncementCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="skuy-app-rail__spacer" data-rail-slot="spacer" aria-hidden="true"></div>

        @if ($user->canPerformSocialWrites())
            <a href="{{ route('feed') }}#composer" class="skuy-app-rail__compose" data-desktop-create-cta data-rail-slot="create">
                <x-fa-icon name="plus" weight="solid" class="text-xl" />
                <span>{{ __('navigation.create_thread') }}</span>
            </a>
        @endif

        <div class="skuy-app-rail__footer" data-rail-slot="account" x-data="{ accountMenuOpen: false }" @click.outside="accountMenuOpen = false" @keydown.escape.window="accountMenuOpen = false">
            <div class="skuy-app-rail__account-card">
                <a href="{{ $profileUrl }}" class="skuy-app-rail__account" aria-label="{{ __('navigation.my_profile') }}: {{ $user->name }}">
                    <span class="skuy-app-rail__account-avatar" aria-hidden="true"
                          x-data="{ avatarUrl: @js($user->avatar ? asset('storage/'.$user->avatar) : '') }"
                          @avatar-saved.window="if ($event.detail.userId === {{ (int) $user->id }}) avatarUrl = $event.detail.url">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" :src="avatarUrl" x-show="avatarUrl" x-on:error="avatarUrl = ''" alt="" decoding="async">
                        @else
                            <template x-if="avatarUrl"><img :src="avatarUrl" x-on:error="avatarUrl = ''" alt="" decoding="async"></template>
                        @endif
                        <span x-show="!avatarUrl" @if ($user->avatar) style="display: none;" @endif>{{ mb_substr($user->name, 0, 1) }}</span>
                    </span>
                    <span class="skuy-app-rail__account-copy">
                        <strong>{{ $user->name }}</strong>
                        <span>{{ '@' . $user->username }}</span>
                    </span>
                </a>
                <button type="button" class="skuy-app-rail__account-more" aria-label="{{ __('navigation.open_profile_menu') }}" aria-haspopup="menu" :aria-expanded="accountMenuOpen.toString()" aria-controls="rail-account-menu" @click="accountMenuOpen = !accountMenuOpen">
                    <x-fa-icon name="ellipsis" class="text-lg" />
                </button>
                <div id="rail-account-menu" x-show="accountMenuOpen" x-transition style="display: none;" role="menu" aria-label="{{ __('navigation.my_profile') }}" class="skuy-menu-surface skuy-app-rail__account-menu">
                    <a href="{{ $profileUrl }}" class="skuy-menu-link" role="menuitem" data-account-menu-key="profile">{{ __('navigation.my_profile') }}</a>
                    <a href="{{ route('profile.edit', $user->username) }}" class="skuy-menu-link" role="menuitem" data-account-menu-key="settings">{{ __('navigation.settings') }}</a>
                    <form method="POST" action="{{ route('logout') }}" data-onesignal-logout>
                        @csrf
                        <button type="submit" class="skuy-menu-link min-h-[44px] text-[var(--skuy-danger)]" role="menuitem" data-account-menu-key="sign-out">{{ __('navigation.sign_out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</aside>

<nav class="skuy-mobile-nav" aria-label="{{ __('home.public.primary_navigation') }}" data-navigation-kind="mobile-primary">
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
        <button type="button" disabled aria-label="{{ __('navigation.create_thread') }}" aria-disabled="true" class="skuy-mobile-nav__item skuy-mobile-nav__item--compose skuy-mobile-nav__item--disabled" data-mobile-nav-key="create">
            <x-fa-icon name="plus" weight="solid" class="text-xl" />
            <span class="sr-only">{{ __('navigation.create_thread') }}</span>
        </button>
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
