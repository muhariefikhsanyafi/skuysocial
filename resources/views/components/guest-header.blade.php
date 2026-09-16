@props([
    'actionUrl' => null,
    'actionLabel' => null,
])

<header class="skuy-guest-header">
    <div class="skuy-guest-header__inner">
        <a href="{{ url('/') }}" class="skuy-guest-brand" aria-label="SkuySocial">
            <span class="skuy-guest-brand__accent">Skuy</span><span>Social</span>
        </a>

        <nav class="skuy-guest-header__nav" aria-label="{{ __('home.public.primary_navigation') }}">
            <x-language-switcher />
            <x-theme-toggle />

            @if (filled($actionUrl) && filled($actionLabel))
                <a href="{{ $actionUrl }}" class="skuy-guest-header__action">
                    {{ $actionLabel }}
                </a>
            @endif
        </nav>
    </div>
</header>
