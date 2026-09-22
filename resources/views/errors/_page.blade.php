@php
    $secondaryUrl = $secondaryUrl ?? null;
    $secondaryLabel = $secondaryLabel ?? null;
    $contextTitle = $contextTitle ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $statusCode }} - {{ $title }} | SkuySocial</title>

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
    <body class="skuy-product min-h-screen antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <section
                aria-labelledby="error-title"
                aria-describedby="error-description"
                class="w-full max-w-2xl rounded-[var(--radius-large)] border border-[var(--skuy-border-subtle)] border-t-2 border-t-[var(--skuy-secondary)] bg-[var(--skuy-surface)] p-6 shadow-[var(--skuy-elevation-2)] sm:p-10"
            >
                <header class="flex items-center justify-between gap-4 border-b border-[var(--skuy-border-subtle)] pb-5">
                    <a
                        href="{{ url('/') }}"
                        class="text-lg font-bold text-[var(--skuy-text-primary)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--skuy-focus)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--skuy-surface)]"
                        aria-label="SkuySocial"
                    >
                        <span class="text-[var(--skuy-secondary)]">Skuy</span>Social
                    </a>

                    <span class="shrink-0 rounded-full bg-[var(--skuy-selected)] px-3 py-1 text-sm font-bold text-[var(--skuy-on-selected)]">
                        {{ $statusCode }}
                    </span>
                </header>

                <div class="mt-8">
                    @if (filled($contextTitle))
                        <p class="text-sm font-bold text-[var(--skuy-danger)]">
                            {{ $contextTitle }}
                        </p>
                    @endif

                    <h1 id="error-title" class="mt-2 break-words text-3xl font-bold leading-tight text-[var(--skuy-text-primary)] sm:text-4xl">
                        {{ $title }}
                    </h1>

                    <p id="error-description" class="mt-4 max-w-xl break-words text-base leading-relaxed text-[var(--skuy-text-secondary)] sm:text-lg">
                        {{ $body }}
                    </p>
                </div>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <a
                        href="{{ $primaryUrl }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-small)] bg-[var(--skuy-brand)] px-5 py-3 text-center text-sm font-bold text-[var(--skuy-on-brand)] transition-colors hover:bg-[var(--skuy-brand-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--skuy-focus)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--skuy-surface)]"
                    >
                        {{ $primaryLabel }}
                    </a>

                    @if (filled($secondaryUrl) && filled($secondaryLabel))
                        <a
                            href="{{ $secondaryUrl }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-5 py-3 text-center text-sm font-bold text-[var(--skuy-text-primary)] transition-colors hover:border-[var(--skuy-secondary)] hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--skuy-focus)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--skuy-surface)]"
                        >
                            {{ $secondaryLabel }}
                        </a>
                    @endif
                </div>
            </section>
        </main>
    </body>
</html>
