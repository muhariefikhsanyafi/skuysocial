<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountSession
{
    public const SESSION_VERSION_KEY = 'auth_session_version';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $storedVersion = $this->normalizeVersion(
            $request->session()->get(self::SESSION_VERSION_KEY, 0),
        );
        $currentVersion = $user->authSessionVersion();
        $state = $user->moderationState();

        if (
            $state === null
            || ! $state->allowsAuthenticatedSession()
            || $storedVersion === null
            || $currentVersion === null
            || $storedVersion !== $currentVersion
        ) {
            $this->revokeCurrentSession($request);

            return $next($request);
        }

        if (! $request->session()->has(self::SESSION_VERSION_KEY)) {
            $request->session()->put(self::SESSION_VERSION_KEY, 0);
        }

        return $next($request);
    }

    private function normalizeVersion(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $version = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return $version === false ? null : $version;
    }

    private function revokeCurrentSession(Request $request): void
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        } else {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
