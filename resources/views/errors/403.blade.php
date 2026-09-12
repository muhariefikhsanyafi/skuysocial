@php
    $user = auth()->user();

    if (request()->is('admin', 'admin/*') && $user instanceof \App\Models\User) {
        $locale = app(\App\Services\LocalePreference::class)->supported($user->locale);

        if ($locale !== null) {
            app()->setLocale($locale);
        }
    }

    $isRestrictedAdministrator = request()->is('admin', 'admin/*')
        && $user instanceof \App\Models\User
        && \App\Enums\UserRole::tryFrom((string) $user->role) === \App\Enums\UserRole::Administrator
        && $user->hasVerifiedEmail();
    $isRestrictedDenial = $user instanceof \App\Models\User
        && $user->moderationState() === \App\Enums\ModerationState::Restricted
        && (
            $exception instanceof \App\Exceptions\RestrictedAccountActionException
            || $isRestrictedAdministrator
        );
    $homeUrl = $user instanceof \App\Models\User ? route('feed') : url('/');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ darkMode: localStorage.getItem('theme') === 'dark' }"
      :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>403 - {{ __('moderation.forbidden.title') }} | SkuySocial</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-red-400 font-sans text-black antialiased transition-colors duration-300 dark:bg-[#101010]">
        <main class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
            <section aria-labelledby="forbidden-title" class="w-full max-w-3xl border-4 border-black bg-yellow-300 p-5 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:border-8 sm:p-10 sm:shadow-[12px_12px_0px_0px_rgba(0,0,0,1)] dark:bg-yellow-400">
                <p class="mb-5 text-base font-black uppercase tracking-wider sm:text-lg">SkuySocial</p>

                <p class="inline-block border-4 border-black bg-white px-4 py-2 text-4xl font-black leading-none shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:text-6xl" aria-hidden="true">
                    403
                </p>

                <h1 id="forbidden-title" class="mt-6 break-words text-3xl font-black leading-tight sm:text-5xl">
                    {{ __('moderation.forbidden.title') }}
                </h1>

                @if ($isRestrictedDenial)
                    <div class="mt-6 border-4 border-black bg-white p-4 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:p-5">
                        <p class="text-lg font-black sm:text-xl">
                            {{ __('moderation.restricted.notice_title') }}
                        </p>
                        <p class="mt-2 break-words text-base font-semibold leading-relaxed sm:text-lg">
                            {{ __('moderation.restricted.denial_body') }}
                        </p>
                    </div>
                @else
                    <p class="mt-5 break-words text-base font-bold leading-relaxed sm:text-xl">
                        {{ __('moderation.forbidden.generic_body') }}
                    </p>
                @endif

                <a href="{{ $homeUrl }}" class="mt-8 inline-flex min-h-[44px] w-full items-center justify-center border-4 border-black bg-cyan-400 px-6 py-3 text-center text-base font-black text-black shadow-[5px_5px_0px_0px_rgba(0,0,0,1)] transition-all hover:translate-x-[5px] hover:translate-y-[5px] hover:shadow-none focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-black focus-visible:ring-offset-4 focus-visible:ring-offset-yellow-300 sm:w-auto sm:text-lg dark:focus-visible:ring-offset-yellow-400">
                    {{ __('moderation.forbidden.back_to_home') }}
                </a>
            </section>
        </main>
    </body>
</html>
