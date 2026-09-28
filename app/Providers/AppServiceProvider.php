<?php

namespace App\Providers;

use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use App\Services\TwoFactorLoginChallengeService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL; // <--- Wajib dipanggil untuk fitur HTTPS
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.navigation', static function (\Illuminate\View\View $view): void {
            $user = auth()->user();

            if (! $user instanceof User) {
                return;
            }

            $view->with('unreadNotificationCount', $user->unreadNotifications()->count())
                ->with('unreadMessageCount', $user->unreadMessageCount());
        });

        RateLimiter::for('two-factor-login', function (Request $request): array {
            $state = $request->session()->get(TwoFactorLoginChallengeService::SESSION_KEY);
            $userId = is_array($state) && is_numeric($state['user_id'] ?? null)
                ? (string) $state['user_id']
                : 'none';
            $session = hash('sha256', $request->session()->getId());
            $ip = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(5)->by('two-factor:user-session:'.$userId.':'.$session),
                ...($userId === 'none' ? [] : [Limit::perMinute(10)->by('two-factor:account:'.$userId)]),
                Limit::perMinute(20)->by('two-factor:ip:'.$ip),
            ];
        });

        RateLimiter::for('two-factor-security', function (Request $request): array {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
            $ip = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(5)->by('two-factor-security:user:'.$userId),
                Limit::perMinute(20)->by('two-factor-security:ip:'.$ip),
            ];
        });

        RateLimiter::for('password-confirmation', function (Request $request): array {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
            $ip = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(5)->by('password-confirmation:user:'.$userId),
                Limit::perMinute(20)->by('password-confirmation:ip:'.$ip),
            ];
        });

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
