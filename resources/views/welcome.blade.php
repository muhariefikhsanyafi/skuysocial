<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'SkuySocial') }} - {{ __('home.public.page_title_suffix') }}</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-yellow-300 min-h-screen flex flex-col selection:bg-pink-400 selection:text-black font-sans">
        
        <!-- Navbar -->
        <nav class="border-b-4 border-black bg-white py-4 px-4 sm:px-12 flex justify-between items-center gap-2 z-10 relative">
            <div class="font-black text-xl sm:text-2xl tracking-widest uppercase border-4 border-black px-4 py-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] bg-pink-400">
                SkuySocial
            </div>
            
            <div class="flex items-center gap-2 sm:gap-4">
                <x-language-switcher />

                <div class="flex items-center gap-2 sm:gap-4">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/feed') }}" class="font-black uppercase border-4 border-black px-4 py-2 sm:px-5 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all bg-emerald-400 inline-block text-sm sm:text-base">
                            {{ __('home.public.go_to_feed') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="font-black uppercase border-4 border-black px-4 py-2 sm:px-5 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all bg-white inline-block text-sm sm:text-base">
                            {{ __('home.public.sign_in') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="font-black uppercase border-4 border-black px-4 py-2 sm:px-5 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] transition-all bg-cyan-400 hidden sm:inline-block text-sm sm:text-base">
                                {{ __('home.public.sign_up') }}
                            </a>
                        @endif
                    @endauth
                @endif
                </div>
            </div>
        </nav>

        <!-- Hero Section (Konten Utama) -->
        <main class="flex-grow flex flex-col justify-center items-center text-center px-4 sm:px-6 relative overflow-hidden z-0 py-20">
            <!-- Ornamen Dekorasi Brutalist -->
            <div class="absolute top-10 left-10 w-20 h-20 sm:w-40 sm:h-40 bg-pink-400 border-4 border-black rounded-full shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] -z-10"></div>
            <div class="absolute bottom-20 right-10 w-32 h-32 sm:w-64 sm:h-64 bg-emerald-400 border-4 border-black shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] rotate-12 -z-10"></div>

            <h1 class="text-6xl sm:text-8xl md:text-9xl font-black uppercase text-black drop-shadow-[4px_4px_0px_rgba(255,255,255,1)] tracking-tighter mb-6 leading-none">
                {{ __('home.public.hero_title_line_one') }}<br>{{ __('home.public.hero_title_line_two') }}
            </h1>
            
            <p class="text-lg sm:text-2xl font-bold max-w-2xl bg-white border-4 border-black p-4 sm:p-6 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] mb-12 leading-relaxed">
                {{ __('home.public.hero_description') }}
            </p>

            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/feed') }}" class="text-xl sm:text-2xl font-black uppercase bg-pink-400 border-4 border-black px-10 py-4 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[8px] hover:translate-x-[8px] transition-all">
                        {{ __('home.public.start_scrolling') }} ➔
                    </a>
                @else
                    <a href="{{ route('register') }}" class="text-xl sm:text-2xl font-black uppercase bg-emerald-400 border-4 border-black px-10 py-4 shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[8px] hover:translate-x-[8px] transition-all flex items-center gap-3">
                        {{ __('home.public.join_now') }}
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                @endauth
            @endif
        </main>

        <!-- Footer -->
        <footer class="border-t-4 border-black bg-white p-6 text-center font-bold text-sm sm:text-base relative z-10">
            &copy; {{ date('Y') }} {{ __('home.public.footer') }}
        </footer>
        
    </body>
</html>
