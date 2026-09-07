<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve and apply the locale for every web request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // app()->setLocale() also updates app.locale, so restore the configured
        // request baseline before resolving this request's preference.
        $configuredLocale = config('localization.default', config('app.locale'));
        config(['app.locale' => $configuredLocale]);

        $supported = array_keys(config('localization.supported', []));
        $fallback = $configuredLocale;

        if (! in_array($fallback, $supported, true)) {
            $fallback = $supported[0] ?? 'id';
        }

        $sessionLocale = $request->session()->get(config('localization.session_key', 'locale'));
        $cookieLocale = $request->cookie(config('localization.cookie_name', 'skuysocial_locale'));

        $locale = collect([$sessionLocale, $cookieLocale, config('app.locale')])
            ->first(fn (mixed $candidate): bool => is_string($candidate)
                && in_array($candidate, $supported, true));

        app()->setLocale($locale ?? $fallback);

        return $next($request);
    }
}
