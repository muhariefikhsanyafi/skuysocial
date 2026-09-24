<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\TwoFactorLoginChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginChallengeController extends Controller
{
    public function show(
        Request $request,
        TwoFactorLoginChallengeService $challenges,
        LocalePreference $locales,
    ): Response {
        $challenge = $challenges->active($request);

        if ($challenge === null || ! $challenge->user instanceof User) {
            return $this->leaveChallenge($request, $challenges);
        }

        $locale = $locales->supported($challenge->user->locale) ?? $locales->currentLocale();
        $cookie = $locales->synchronize($request, $locale);

        return $this->noStore(response()->view('auth.two-factor-challenge')->withCookie($cookie));
    }

    public function verify(
        Request $request,
        TwoFactorLoginChallengeService $challenges,
        LocalePreference $locales,
    ): Response|RedirectResponse {
        $code = $request->input('code');

        if (! is_string($code) || strlen($code) > 64) {
            return back()->withErrors(['code' => trans('auth.two_factor.invalid')]);
        }

        if ($challenges->active($request) === null) {
            return $this->leaveChallenge($request, $challenges);
        }

        $challenge = $challenges->complete($request, $code);

        if ($challenge === null) {
            if ($challenges->active($request) === null) {
                return $this->leaveChallenge($request, $challenges);
            }

            return back()->withErrors(['code' => trans('auth.two_factor.invalid')]);
        }

        $request->session()->forget(TwoFactorLoginChallengeService::SESSION_KEY);
        $request->session()->forget('url.intended');

        $user = $challenge->user;

        if (! $user instanceof User) {
            return $this->leaveChallenge($request, $challenges);
        }

        Auth::guard('web')->login($user, $challenge->remember);
        $request->session()->regenerate();

        $locale = $locales->supported($user->locale) ?? $locales->currentLocale();
        $cookie = $locales->synchronize($request, $locale, $user);
        $target = $challenge->source === 'admin'
            ? '/admin'
            : ($challenge->intended_url ?? route('dashboard', absolute: false));

        return redirect()->to($target)->withCookie($cookie);
    }

    public function cancel(Request $request, TwoFactorLoginChallengeService $challenges): RedirectResponse
    {
        $source = $challenges->clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($source === 'admin' ? '/admin/login' : route('login'));
    }

    private function leaveChallenge(Request $request, TwoFactorLoginChallengeService $challenges): RedirectResponse
    {
        $source = $challenges->clear($request);

        return redirect($source === 'admin' ? '/admin/login' : route('login'));
    }

    private function noStore(View|Response $response): Response
    {
        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
