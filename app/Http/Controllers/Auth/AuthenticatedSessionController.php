<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LocalePreference $locales): RedirectResponse
    {
        $effectiveGuestLocale = $locales->currentLocale();

        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $locale = $locales->supported($user->locale) ?? $effectiveGuestLocale;
        $cookie = $locales->synchronize($request, $locale, $user);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')->withCookie($cookie);
        }

        return redirect()->intended(route('dashboard', absolute: false))->withCookie($cookie);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
