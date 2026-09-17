<?php

namespace App\Http\Requests\Auth;

use App\Enums\ModerationState;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        app(TurnstileVerifier::class)->validate($this->input('cf-turnstile-response'), 'login');

        $validatedModerationState = null;

        if (! Auth::attemptWhen(
            $this->loginCredentials(),
            function (User $user) use (&$validatedModerationState): bool {
                $validatedModerationState = $user->moderationState();

                return $validatedModerationState?->allowsAuthentication() ?? false;
            },
            $this->boolean('remember'),
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => match ($validatedModerationState) {
                    ModerationState::Suspended => trans('moderation.login.suspended'),
                    ModerationState::Banned => trans('moderation.login.banned'),
                    default => trans('auth.failed'),
                },
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Resolve the submitted value against the existing email or username contract.
     *
     * @return array{email?: string, username?: string, password: string}
     */
    private function loginCredentials(): array
    {
        $identifier = $this->string('email')->toString();
        $password = $this->string('password')->toString();

        if (str_contains($identifier, '@')) {
            return [
                'email' => $identifier,
                'password' => $password,
            ];
        }

        return [
            'username' => strtolower($identifier),
            'password' => $password,
        ];
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
