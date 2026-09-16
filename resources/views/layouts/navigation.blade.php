<nav x-data="{ open: false, sidebarOpen: false }" class="skuy-topbar">
    <div class="skuy-topbar__inner">
        <div class="skuy-topbar__brand-group">
            <button
                @click="sidebarOpen = !sidebarOpen; $dispatch('toggle-sidebar', sidebarOpen)"
                :aria-label="sidebarOpen ? '{{ __('navigation.close_navigation_menu') }}' : '{{ __('navigation.open_navigation_menu') }}'"
                :aria-expanded="sidebarOpen"
                aria-controls="mobile-navigation"
                type="button"
                class="skuy-icon-button md:hidden"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"></path>
                </svg>
                <span class="sr-only">{{ __('navigation.open_navigation_menu') }}</span>
            </button>

            <a href="{{ route('feed') }}" class="skuy-brand" aria-label="SkuySocial, {{ __('navigation.home') }}">
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
                <svg x-show="!darkMode" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 0 1 8.646 3.646 9.003 9.003 0 0 0 12 21a9.003 9.003 0 0 0 8.354-5.646Z"></path>
                </svg>
                <svg x-show="darkMode" style="display: none;" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M3 12h2m14 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                    <circle cx="12" cy="12" r="4"></circle>
                </svg>
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
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"></path>
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div id="desktop-profile-menu" role="menu" aria-labelledby="desktop-profile-trigger" class="skuy-menu-surface">
                            <x-dropdown-link :href="route('user.profile', auth()->user()->username)" class="skuy-menu-link" role="menuitem">
                                {{ __('navigation.my_profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')" class="skuy-menu-link" role="menuitem">
                                {{ __('navigation.settings') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="skuy-menu-link" role="menuitem">
                                    {{ __('navigation.sign_out') }}
                                </x-dropdown-link>
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
                    <svg class="skuy-profile-icon h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <g x-show="!open">
                            <circle cx="12" cy="8" r="3.5" stroke-width="1.8"></circle>
                            <path stroke-linecap="round" stroke-width="1.8" d="M5 21a7 7 0 0 1 14 0"></path>
                        </g>
                        <path x-show="open" style="display: none;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 6 12 12M18 6 6 18"></path>
                    </svg>
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
                <a href="{{ route('profile.edit') }}" class="skuy-menu-link">
                    {{ __('navigation.settings') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();" class="skuy-menu-link text-[var(--skuy-danger)]">
                        {{ __('navigation.sign_out') }}
                    </a>
                </form>
            </div>
        </div>
    </div>
</nav>
