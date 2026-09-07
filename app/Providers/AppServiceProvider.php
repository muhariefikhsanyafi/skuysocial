<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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

        ResetPassword::toMailUsing(function ($notifiable, $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expiration = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Atur Ulang Kata Sandi SkuySocial')
                ->view([
                    'html' => 'emails.password-reset',
                    'text' => 'emails.password-reset-text',
                ], compact('url', 'expiration'));
        });
    }
}
