<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $turnstile->validate($request->input('cf-turnstile-response'), 'forgot_password');

        Password::sendResetLink(
            $request->only('email')
        );

        // Do not reveal whether the submitted address belongs to an account.
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
