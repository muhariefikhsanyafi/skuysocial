<?php

namespace App\Http\Requests\Auth;

use App\Enums\ModerationState;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
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
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validate the primary credential without creating a session.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        app(TurnstileVerifier::class)->validate($this->input('cf-turnstile-response'), 'login');

        $credentials = $this->loginCredentials();
        $remember = $this->shouldRemember();
        $validatedModerationState = null;
        $provider = Auth::guard('web')->getProvider();

        $user = app(Timebox::class)->call(
            function (Timebox $timebox) use ($provider, $credentials, $remember, &$validatedModerationState): ?User {
                event(new Attempting('web', $credentials, $remember));

                $candidate = $provider->retrieveByCredentials($credentials);

                if (! $candidate instanceof User || ! $provider->validateCredentials($candidate, $credentials)) {
                    event(new Failed('web', $candidate, $credentials));

                    return null;
                }

                $validatedModerationState = $candidate->moderationState();

                if (! $validatedModerationState?->allowsAuthentication()) {
                    event(new Failed('web', $candidate, $credentials));

                    return null;
                }

                event(new Validated('web', $candidate));
                $timebox->returnEarly();

                return $candidate;
            },
            (int) config('auth.timebox_duration', 200000),
        );

        if (! $user instanceof User) {
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

        return $user;
    }

    public function shouldRemember(): bool
    {
        if (! $this->boolean('remember')) {
            return false;
        }

        $credentials = $this->loginCredentials();
        $query = User::query()->whereNotNull('email_verified_at');

        if (isset($credentials['email'])) {
            $query->where('email', $credentials['email']);
        } else {
            $query->where('username', $credentials['username']);
        }

        return $query->exists();
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
