<?php

namespace App\Http\Middleware;

use App\Enums\ModerationState;
use App\Exceptions\RestrictedAccountActionException;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveModerationState
{
    public const DENIAL_CODE = 'moderation_state_forbids_social_write';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->canPerformSocialWrites()) {
            return $next($request);
        }

        if (! $user instanceof User || $user->moderationState() !== ModerationState::Restricted) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => trans('moderation.denials.social_write'),
                'code' => self::DENIAL_CODE,
            ], Response::HTTP_FORBIDDEN);
        }

        throw new RestrictedAccountActionException;
    }
}
