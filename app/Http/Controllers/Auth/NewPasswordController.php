<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $turnstile->validate($request->input('cf-turnstile-response'), 'reset_password');

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $updatedUser = DB::transaction(function () use ($user, $request): User {
                    $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
                    $sessionVersion = $lockedUser->authSessionVersion();

                    if ($sessionVersion === null || $sessionVersion === PHP_INT_MAX) {
                        throw new \LogicException('The account authentication state is invalid.');
                    }

                    $lockedUser->forceFill([
                        'password' => Hash::make($request->string('password')->toString()),
                        'remember_token' => Str::random(60),
                        'auth_session_version' => $sessionVersion + 1,
                    ])->save();

                    return $lockedUser;
                });

                event(new PasswordReset($updatedUser));
            },
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
