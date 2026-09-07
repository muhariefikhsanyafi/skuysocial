<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');
        $returnTo = $request->validated('return_to');

        app()->setLocale($locale);
        $request->session()->put(config('localization.session_key', 'locale'), $locale);

        $secure = config('session.secure');

        if ($secure === null) {
            $secure = $request->isSecure();
        }

        $redirectTo = UpdateLocaleRequest::isSafeLocalPath($returnTo)
            ? $returnTo
            : '/';

        return redirect()->to($redirectTo)->withCookie(cookie(
            config('localization.cookie_name', 'skuysocial_locale'),
            $locale,
            (int) config('localization.cookie_lifetime', 525600),
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        ));
    }
}
