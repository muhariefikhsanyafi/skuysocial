<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
      x-data="{ darkMode: localStorage.getItem('theme') === 'dark' }" 
      x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))" 
      :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-[#101010] transition-colors duration-300">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
		<!-- NEO-BRUTALISM TOAST NOTIFICATION -->
    <div x-data="{ showToast: false, toastMessage: '', toastTimeout: null }"
         @show-toast.window="
            toastMessage = $event.detail;
            showToast = true;
            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => { showToast = false }, 3000);
         "
         x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-10"
         style="display: none;"
         class="fixed bottom-24 sm:bottom-12 left-1/2 transform -translate-x-1/2 z-[9999] px-4 py-3 bg-cyan-400 border-2 sm:border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] flex items-center gap-3 min-w-[280px] max-w-[90%]">
         
         <div class="bg-white border-2 border-black rounded-full p-1 flex-shrink-0 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
             <svg class="w-4 h-4 text-black" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
         </div>
         <span class="font-black text-black uppercase text-xs sm:text-sm tracking-wide" x-text="toastMessage"></span>
    </div>

    <!-- SCRIPT JALAN NINJA UNTUK COPY LINK & TOAST (ANTI-GAGAL) -->
    <script>
        window.copyAndToast = function(text, message) {
            // 1. Coba gunakan API Clipboard modern (Untuk Server HTTPS/Produksi)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).catch(() => {});
            } else {
                // 2. Fallback untuk HTTP lokal (.test)
                let textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-9999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try { document.execCommand('copy'); } catch (err) {}
                document.body.removeChild(textArea);
            }
            // 3. Panggil Pop-Up Toast Alpine
            window.dispatchEvent(new CustomEvent('show-toast', { detail: message }));
        };
    </script>
    </body>
</html>
