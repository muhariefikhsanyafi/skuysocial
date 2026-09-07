<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Services\LocalePreference;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(
        UpdateLocaleRequest $request,
        LocalePreference $locales,
    ): RedirectResponse
    {
        $locale = $request->validated('locale');
        $returnTo = $request->validated('return_to');
        $cookie = $locales->synchronize($request, $locale, $request->user());

        $redirectTo = UpdateLocaleRequest::isSafeLocalPath($returnTo)
            ? $returnTo
            : '/';

        return redirect()->to($redirectTo)->withCookie($cookie);
    }
}
