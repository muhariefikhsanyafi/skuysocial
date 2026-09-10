<?php

namespace App\Filament\Pages\Auth;

use App\Services\TurnstileVerifier;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
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
