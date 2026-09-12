<?php

namespace App\Providers;

use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL; // <--- Wajib dipanggil untuk fitur HTTPS
use Illuminate\Support\ServiceProvider;

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
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || ! $event->user instanceof User) {
                return;
            }

            $request = request();

            if (! $request->hasSession()) {
                return;
            }

            $request->session()->put(
                EnforceAccountSession::SESSION_VERSION_KEY,
                $event->user->authSessionVersion() ?? -1,
            );
        });

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
