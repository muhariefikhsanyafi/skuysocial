<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TurnstileVerifier
{
    public function validate(mixed $token, string $expectedAction): void
    {
        if (! config('services.turnstile.enabled')) {
            return;
        }

        if (! $this->verify($token, $expectedAction)) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Verifikasi keamanan gagal. Silakan coba lagi.',
            ]);
        }
    }

    private function verify(mixed $token, string $expectedAction): bool
    {
        $secret = config('services.turnstile.secret_key');
        $hostname = config('services.turnstile.hostname');
        $verifyUrl = config('services.turnstile.verify_url');

        if (
            ! is_string($token)
            || trim($token) === ''
            || ! is_string($secret)
            || trim($secret) === ''
            || ! is_string($hostname)
            || trim($hostname) === ''
            || ! is_string($verifyUrl)
            || trim($verifyUrl) === ''
            || $expectedAction === ''
        ) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post($verifyUrl, [
                    'secret' => $secret,
                    'response' => $token,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $payload = $response->json();

        return is_array($payload)
            && ($payload['success'] ?? null) === true
            && is_string($payload['hostname'] ?? null)
            && strtolower($payload['hostname']) === strtolower($hostname)
            && is_string($payload['action'] ?? null)
            && ($payload['action'] ?? null) === $expectedAction;
    }
}
