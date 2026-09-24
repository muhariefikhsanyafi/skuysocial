<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentPasswordConfirmation
{
    public const CONFIRMATION_WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        if (
            is_int($confirmedAt)
            && $confirmedAt <= now()->timestamp
            && now()->timestamp - $confirmedAt <= self::CONFIRMATION_WINDOW_SECONDS
        ) {
            return $next($request);
        }

        $intendedUrl = $request->isMethodSafe()
            ? $request->getRequestUri()
            : route('security.two-factor.show', absolute: false);
        $request->session()->put('url.intended', $intendedUrl);

        return redirect()->route('password.confirm');
    }
}
