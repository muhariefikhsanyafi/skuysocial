<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        Request $request,
        TurnstileVerifier $turnstile,
        LocalePreference $locales,
    ): RedirectResponse {
        if (is_string($request->input('username'))) {
            $request->merge([
                'username' => strtolower($request->input('username')),
            ]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users', 'regex:/^[a-zA-Z0-9_.]+$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $turnstile->validate($request->input('cf-turnstile-response'), 'register');

        $user = new User([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $locale = $locales->currentLocale();
        $user->locale = $locale;
        $user->role = UserRole::User->value;
        $user->moderation_state = ModerationState::Active->value;
        $user->moderation_state_changed_at = null;
        $user->moderation_state_version = 0;
        $user->auth_session_version = 0;
        $user->save();
        $user->refresh();

        event(new Registered($user));

        Auth::login($user);

        $cookie = $locales->synchronize($request, $locale);

        return redirect(route('dashboard', absolute: false))->withCookie($cookie);
    }
}
