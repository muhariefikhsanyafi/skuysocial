<?php

namespace App\Http\Middleware;

use App\Services\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private LocalePreference $locales)
    {
    }

    /**
     * Resolve and apply the locale for every web request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // app()->setLocale() also updates app.locale, so restore the configured
        // request baseline before resolving this request's preference.
        $configuredLocale = $this->locales->configuredLocale();
        config(['app.locale' => $configuredLocale]);

        app()->setLocale($this->locales->resolve($request));

        return $next($request);
    }
}
