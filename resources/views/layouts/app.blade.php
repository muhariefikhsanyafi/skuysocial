<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-flow-page="{{ request()->routeIs('feed', 'bookmarks.index', 'search') ? 'feed' : (request()->routeIs('user.profile', 'user.edit') ? 'profile' : '') }}"
    x-data="{ darkMode: document.documentElement.classList.contains('dark') }"
    x-init="$watch('darkMode', value => window.skuyTheme.persist(value))"
    :class="{ 'dark': darkMode }"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.theme-bootstrap', ['darkDefault' => request()->routeIs('feed')])

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/fontawesome.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/regular.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/solid.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/brands.min.css') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if (request()->routeIs('feed', 'bookmarks.index', 'search'))
            @vite(['resources/css/skuy-flow-feed.css', 'resources/css/skuy-flow-post-actions.css'])
        @elseif (request()->routeIs('user.profile'))
            @vite(['resources/css/skuy-flow-profile.css', 'resources/css/skuy-flow-post-actions.css', 'resources/js/skuy-flow-profile.js'])
        @elseif (request()->routeIs('user.edit'))
            @vite('resources/css/skuy-flow-profile.css')
        @endif
    </head>
    <body class="skuy-product antialiased">
        @if (auth()->check() && (bool) config('services.onesignal.enabled', false) && filled(config('services.onesignal.app_id')))
            <div
                data-onesignal-bootstrap
                data-app-id="{{ config('services.onesignal.app_id') }}"
                data-external-id="{{ \App\Support\OneSignalExternalId::forUser(auth()->user()) }}"
                hidden
                aria-hidden="true"
            ></div>
        @endif

        <div class="skuy-app-shell">
            @if (auth()->check())
                @include('layouts.navigation')
                <div class="skuy-app-shell__workspace">
            @else
                <x-guest-header :action-url="route('login')" :action-label="__('home.public.sign_in')" />
            @endif

            <a href="#main-content" class="skuy-skip-link">{{ __('navigation.skip_to_content') }}</a>

            @if (auth()->check())
                <x-restricted-account-notice class="{{ request()->routeIs('user.profile') ? 'skuy-shell-notice--profile' : '' }}{{ request()->routeIs('search', 'activity.index', 'announcements.*', 'messages.*', 'bookmarks.index') ? ' skuy-shell-notice--content' : '' }}" />
            @endif

            @isset($header)
                <header class="skuy-app-page-header">
                    <div class="skuy-app-page-header__inner">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="main-content" tabindex="-1">
                {{ $slot }}
            </main>

            @if (auth()->check())
                </div>
            @endif
        </div>

        <div
            x-data="{ showToast: false, toastMessage: '', toastTimeout: null }"
            x-on:show-toast.window="
                toastMessage = $event.detail;
                showToast = true;
                clearTimeout(toastTimeout);
                toastTimeout = setTimeout(() => { showToast = false }, 3000);
            "
            x-show="showToast"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            style="display: none;"
            role="status"
            aria-live="polite"
            class="skuy-toast"
        >
            <span class="skuy-toast__mark" aria-hidden="true">
                <x-fa-icon name="check" weight="solid" class="text-base" />
            </span>
            <span class="skuy-toast__message" x-text="toastMessage"></span>
        </div>

        <script>
            window.copyAndToast = function (text, message) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).catch(() => {});
                } else {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();

                    try {
                        document.execCommand('copy');
                    } catch (error) {
                        // Clipboard fallback is best effort on insecure local hosts.
                    }

                    document.body.removeChild(textArea);
                }

                window.dispatchEvent(new CustomEvent('show-toast', { detail: message }));
            };
        </script>
    </body>
</html>
