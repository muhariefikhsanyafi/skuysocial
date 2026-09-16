<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'SkuySocial') }} | {{ __('home.public.page_title_suffix') }}</title>

        <script>
            const savedTheme = window.localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (savedTheme === 'dark' || (savedTheme !== 'light' && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="skuy-product skuy-guest-surface antialiased">
        <div
            class="skuy-guest-shell skuy-public-shell"
            x-data="{ darkMode: document.documentElement.classList.contains('dark') }"
            x-init="$watch('darkMode', value => localStorage.setItem('theme', value ? 'dark' : 'light'))"
            x-effect="document.documentElement.classList.toggle('dark', darkMode)"
        >
            @if (Route::has('login'))
                @auth
                    <x-guest-header :action-url="url('/feed')" :action-label="__('home.public.go_to_feed')" />
                @else
                    <x-guest-header :action-url="route('login')" :action-label="__('home.public.sign_in')" />
                @endauth
            @else
                <x-guest-header />
            @endif

            <main id="main-content" class="skuy-welcome">
                <div class="skuy-welcome__frame">
                    <section class="skuy-welcome__hero" aria-labelledby="welcome-title">
                        <div class="skuy-welcome__hero-copy">
                            <h1 id="welcome-title">{{ __('home.public.hero_title') }}</h1>
                            <p>{{ __('home.public.hero_description') }}</p>

                            <div class="skuy-welcome__actions">
                                @auth
                                    <a href="{{ url('/feed') }}" class="skuy-public-button skuy-public-button--primary">
                                        {{ __('home.public.go_to_feed') }}
                                    </a>
                                @else
                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="skuy-public-button skuy-public-button--primary">
                                            {{ __('home.public.sign_up') }}
                                        </a>
                                    @endif

                                    @if (Route::has('login'))
                                        <a href="{{ route('login') }}" class="skuy-public-button skuy-public-button--secondary">
                                            {{ __('home.public.sign_in') }}
                                        </a>
                                    @endif
                                @endauth
                            </div>
                        </div>

                        <figure class="skuy-thread-preview">
                            <div class="skuy-thread-preview__topbar">
                                <span class="skuy-thread-preview__brand"><span class="skuy-guest-brand__accent">Skuy</span>Social</span>
                                <span class="skuy-thread-preview__label">{{ __('home.public.preview_title') }}</span>
                            </div>

                            <div class="skuy-thread-preview__content">
                                <article class="skuy-preview-thread">
                                    <div class="skuy-preview-identity">
                                        <span class="skuy-preview-avatar" aria-hidden="true">
                                            <span class="skuy-preview-avatar__shape"></span>
                                        </span>
                                        <div class="skuy-preview-identity__copy">
                                            <p class="skuy-preview-identity__name">{{ __('home.public.preview_account') }}</p>
                                            <p class="skuy-preview-identity__meta">{{ __('home.public.preview_thread_label') }}</p>
                                        </div>
                                    </div>

                                    <p class="skuy-preview-thread__text">{{ __('home.public.preview_body') }}</p>

                                    <div class="skuy-preview-actions" aria-hidden="true">
                                        <span class="skuy-preview-action skuy-preview-action--reaction">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"></path>
                                            </svg>
                                            <span>{{ __('home.public.preview_like') }}</span>
                                        </span>
                                        <span class="skuy-preview-action">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"></path>
                                            </svg>
                                            <span>{{ __('home.public.preview_reply') }}</span>
                                        </span>
                                        <span class="skuy-preview-action">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m17 1 4 4-4 4"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m7 23-4-4 4-4"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                                            </svg>
                                            <span>{{ __('home.public.preview_repost') }}</span>
                                        </span>
                                        <span class="skuy-preview-action">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-6-3-6 3V3Z"></path>
                                            </svg>
                                            <span>{{ __('home.public.preview_save') }}</span>
                                        </span>
                                    </div>
                                </article>

                                <div class="skuy-preview-reply">
                                    <span class="skuy-preview-avatar skuy-preview-avatar--small" aria-hidden="true">
                                        <span class="skuy-preview-avatar__shape"></span>
                                    </span>
                                    <div>
                                        <p class="skuy-preview-identity__name">{{ __('home.public.preview_account') }}</p>
                                        <p class="skuy-preview-reply__text">{{ __('home.public.preview_reply_body') }}</p>
                                    </div>
                                </div>
                            </div>

                            <figcaption class="skuy-thread-preview__caption">
                                <span>{{ __('home.public.preview_title') }}</span>
                                <span>{{ __('home.public.preview_note') }}</span>
                            </figcaption>
                        </figure>
                    </section>

                    <section class="skuy-welcome-story skuy-welcome-story--opening" aria-labelledby="welcome-story-one">
                        <div class="skuy-welcome-story__index" aria-hidden="true">01</div>
                        <div class="skuy-welcome-story__content">
                            <h2 id="welcome-story-one">{{ __('home.public.value_one_heading') }}</h2>
                            <p>{{ __('home.public.value_one_copy') }}</p>
                        </div>
                    </section>

                    <section class="skuy-welcome-story skuy-welcome-story--discover" aria-labelledby="welcome-story-two">
                        <div class="skuy-welcome-story__index" aria-hidden="true">02</div>
                        <div class="skuy-welcome-story__content">
                            <h2 id="welcome-story-two">{{ __('home.public.value_two_heading') }}</h2>
                            <p>{{ __('home.public.value_two_copy') }}</p>
                        </div>
                    </section>

                    <section class="skuy-welcome-story skuy-welcome-story--participate" aria-labelledby="welcome-story-three">
                        <div class="skuy-welcome-story__index" aria-hidden="true">03</div>
                        <div class="skuy-welcome-story__content">
                            <h2 id="welcome-story-three">{{ __('home.public.value_three_heading') }}</h2>
                            <p>{{ __('home.public.value_three_copy') }}</p>
                        </div>
                    </section>

                    <section id="join" class="skuy-welcome__closing" aria-labelledby="welcome-closing-title">
                        <div>
                            <h2 id="welcome-closing-title">{{ __('home.public.closing_heading') }}</h2>
                            <p>{{ __('home.public.closing_copy') }}</p>
                        </div>

                        <div class="skuy-welcome__closing-actions">
                            @auth
                                <a href="{{ url('/feed') }}" class="skuy-public-button skuy-public-button--primary">
                                    {{ __('home.public.go_to_feed') }}
                                </a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="skuy-public-button skuy-public-button--primary">
                                        {{ __('home.public.sign_up') }}
                                    </a>
                                @endif

                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="skuy-welcome__quiet-link">
                                        {{ __('home.public.sign_in') }}
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </section>
                </div>
            </main>

            <footer class="skuy-guest-footer">
                <span>&copy; {{ date('Y') }} {{ __('home.public.footer') }}</span>
            </footer>
        </div>
    </body>
</html>
