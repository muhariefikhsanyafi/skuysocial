@php
    $skuyV2Page = match (true) {
        request()->routeIs('login') => 'auth-login',
        request()->routeIs('register') => 'auth-register',
        request()->routeIs('password.request') => 'auth-forgot',
        request()->routeIs('password.reset') => 'auth-reset',
        default => null,
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" @if ($skuyV2Page) data-skuy-v2-page="{{ $skuyV2Page }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('auth.common.page_title') }} - {{ config('app.name', 'SkuySocial') }}</title>

        <script>
            const savedTheme = window.localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            const useDarkTheme = savedTheme === 'dark' || (savedTheme !== 'light' && prefersDark);

            document.documentElement.classList.toggle('dark', useDarkTheme);
            document.documentElement.dataset.theme = useDarkTheme ? 'dark' : 'light';
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if ($skuyV2Page)
            @vite(['resources/css/skuy-design-v2.css'])
        @endif
    </head>
    <body class="skuy-product skuy-guest-surface antialiased">
        <div
            class="skuy-guest-shell"
            x-data="{ darkMode: document.documentElement.classList.contains('dark') }"
            x-init="$watch('darkMode', value => localStorage.setItem('theme', value ? 'dark' : 'light'))"
            x-effect="document.documentElement.classList.toggle('dark', darkMode); document.documentElement.dataset.theme = darkMode ? 'dark' : 'light'"
        >
            <x-guest-header />

            <main class="skuy-auth-main">
                <div class="skuy-auth-panel">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
