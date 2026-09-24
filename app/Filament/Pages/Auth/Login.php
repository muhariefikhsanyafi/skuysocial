<?php

namespace App\Filament\Pages\Auth;

use App\Models\TwoFactorAuthentication;
use App\Models\User;
use App\Services\TurnstileVerifier;
use App\Services\TwoFactorLoginChallengeService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Hidden::make('turnstile_token'),
                View::make('filament.auth.turnstile'),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $request = request();

        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();
        $provider = $authGuard->getProvider();
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);

        $candidate = app(Timebox::class)->call(
            function (Timebox $timebox) use ($authGuard, $provider, $credentials, $remember): Authenticatable {
                $this->fireAttemptingEvent($authGuard, $credentials, $remember);
                $user = $provider->retrieveByCredentials($credentials);

                if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
                    $this->userUndertakingMultiFactorAuthentication = null;
                    $this->fireFailedEvent($authGuard, $user, $credentials);
                    $this->throwFailureValidationException();
                }

                if (! $this->isUserAllowedToAccessPanel($user)) {
                    $this->userUndertakingMultiFactorAuthentication = null;
                    $this->fireFailedEvent($authGuard, $user, $credentials);
                    $this->throwFailureValidationException();
                }

                $timebox->returnEarly();

                return $user;
            },
            (int) config('auth.timebox_duration', 200_000),
        );

        $result = DB::transaction(function () use ($candidate, $authGuard, $provider, $credentials, $remember): string {
            $user = User::query()->lockForUpdate()->find($candidate->getAuthIdentifier());

            if (
                ! $user instanceof User
                || ! Hash::check((string) ($credentials['password'] ?? ''), $user->getAuthPassword())
                || ! $this->isUserAllowedToAccessPanel($user)
            ) {
                return 'failed';
            }

            $security = TwoFactorAuthentication::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($security instanceof TwoFactorAuthentication && $security->isEnabled()) {
                $request = request();
                $request->session()->forget('url.intended');
                $request->session()->regenerate();

                app(TwoFactorLoginChallengeService::class)->begin(
                    $user,
                    $request,
                    $remember,
                    'admin',
                    '/admin',
                );

                return 'challenge';
            }

            $provider->rehashPasswordIfRequired($user, $credentials);
            $authGuard->login($user, $remember);
            session()->regenerate();

            return 'authenticated';
        });

        if ($result === 'challenge') {
            $this->redirectRoute('two-factor.challenge');

            return null;
        }

        if ($result !== 'authenticated') {
            $this->userUndertakingMultiFactorAuthentication = null;
            $this->fireFailedEvent($authGuard, $candidate, $credentials);
            $this->throwFailureValidationException();
        }

        return app(LoginResponse::class);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        try {
            app(TurnstileVerifier::class)->validate($data['turnstile_token'] ?? null, 'login');
        } catch (ValidationException $exception) {
            $this->data['turnstile_token'] = null;
            $this->dispatch('admin-turnstile-reset');

            throw ValidationException::withMessages([
                'data.turnstile_token' => $exception->errors()['cf-turnstile-response']
                    ?? [__('auth.security.verification_failed')],
            ]);
        }

        return parent::getCredentialsFromFormData($data);
    }
}
