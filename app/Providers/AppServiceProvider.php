<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <--- Wajib dipanggil untuk fitur HTTPS

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa semua URL (termasuk CSS Vite) menjadi HTTPS jika sedang online (production)
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}