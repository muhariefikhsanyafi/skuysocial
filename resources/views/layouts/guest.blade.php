<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('auth.common.page_title') }} - {{ config('app.name', 'SkuySocial') }}</title>

        @include('partials.theme-bootstrap')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/css/skuy-flow-guest.css', 'resources/js/app.js'])
    </head>
    <body class="skuy-product skuy-guest-surface antialiased" data-flow-guest="auth">
        <div
            class="skuy-guest-shell"
            x-data="{ darkMode: document.documentElement.classList.contains('dark') }"
            x-init="$watch('darkMode', value => window.skuyTheme.persist(value))"
            x-effect="document.documentElement.classList.toggle('dark', darkMode)"
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
