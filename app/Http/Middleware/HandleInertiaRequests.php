<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $moderationState = $user instanceof User ? $user->moderationState() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User
                    ? $user->only(['id', 'name', 'username'])
                    : null,
            ],
            'locale' => app()->getLocale(),
            'flash' => [
                'status' => function () use ($request): ?string {
                    $status = $request->session()->get('status');

                    return is_string($status) ? $status : null;
                },
            ],
            'moderation' => [
                'state' => $moderationState?->value,
                'allows_social_writes' => $user instanceof User && $user->canPerformSocialWrites(),
            ],
        ];
    }
}
