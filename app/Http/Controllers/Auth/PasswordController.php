<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password and invalidate stale authenticated state.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $sessionVersion = DB::transaction(function () use ($request, $validated): ?int {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getAuthIdentifier());
            $currentVersion = $user->authSessionVersion();

            if (
                ! Hash::check($validated['current_password'], $user->getAuthPassword())
                || $currentVersion === null
                || $currentVersion === PHP_INT_MAX
            ) {
                return null;
            }

            $nextVersion = $currentVersion + 1;
            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'auth_session_version' => $nextVersion,
                'remember_token' => Str::random(60),
            ])->save();

            return $nextVersion;
        });

        if ($sessionVersion === null) {
            return back()->withErrors([
                'current_password' => trans('auth.password'),
            ], 'updatePassword');
        }

        $request->session()->put(EnforceAccountSession::SESSION_VERSION_KEY, $sessionVersion);
        $request->session()->regenerate();

        return back()->with('status', 'password-updated');
    }
}
