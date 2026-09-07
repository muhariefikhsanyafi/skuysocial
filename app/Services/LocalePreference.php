<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie;

class LocalePreference
{
    /**
     * Return a locale only when it is in the configured exact allowlist.
     */
    public function supported(mixed $locale): ?string
    {
        if (! is_string($locale)) {
            return null;
        }

        return array_key_exists($locale, config('localization.supported', []))
            ? $locale
            : null;
    }

    /**
     * Return the stable configured application locale for a new request.
     */
    public function configuredLocale(): string
    {
        return $this->supported(config('localization.default'))
            ?? $this->fallbackLocale();
    }

    /**
     * Return the currently active locale, constrained to the allowlist.
     */
    public function currentLocale(): string
    {
        return $this->supported(app()->getLocale())
            ?? $this->configuredLocale();
    }

    /**
     * Resolve locale priority without persisting any state.
     */
    public function resolve(Request $request): string
    {
        return $this->supported($request->user()?->locale)
            ?? $this->supported($request->session()->get(
                config('localization.session_key', 'locale')
            ))
            ?? $this->supported($request->cookie(
                config('localization.cookie_name', 'skuysocial_locale')
            ))
            ?? $this->supported(config('app.locale'))
            ?? $this->fallbackLocale();
    }

    /**
     * Persist and synchronize an explicitly selected or authenticated locale.
     */
    public function synchronize(Request $request, string $locale, ?User $user = null): Cookie
    {
        $locale = $this->supported($locale)
            ?? throw new InvalidArgumentException('Unsupported locale.');

        if ($user !== null && $user->locale !== $locale) {
            $user->locale = $locale;
            $user->save();
        }

        app()->setLocale($locale);
        $request->session()->put(config('localization.session_key', 'locale'), $locale);

        $secure = config('session.secure');

        if ($secure === null) {
            $secure = $request->isSecure();
        }

        return cookie(
            config('localization.cookie_name', 'skuysocial_locale'),
            $locale,
            (int) config('localization.cookie_lifetime', 525600),
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        );
    }

    /**
     * Return a known supported fallback locale.
     */
    private function fallbackLocale(): string
    {
        $supported = array_keys(config('localization.supported', []));

        return $this->supported(config('app.fallback_locale'))
            ?? ($supported[0] ?? 'id');
    }
}
