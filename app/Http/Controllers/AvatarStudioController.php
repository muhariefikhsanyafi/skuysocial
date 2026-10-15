<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvatarStudioRequest;
use App\Models\User;
use App\Services\GravatarAvatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AvatarStudioController extends Controller
{
    public function preview(Request $request, string $style, GravatarAvatar $gravatar): Response
    {
        abort_unless(in_array($style, GravatarAvatar::STYLES, true), 404);
        abort_unless(is_string($request->query('v')) && hash_equals($gravatar->hash($request->user()), $request->query('v')), 404);

        try {
            $body = $gravatar->image($request->user(), $style);
        } catch (Throwable) {
            return response('', 503)->header('Cache-Control', 'no-store');
        }

        return response($body)->withHeaders([
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function store(AvatarStudioRequest $request, GravatarAvatar $gravatar): JsonResponse
    {
        $validated = $request->validated();
        $path = null;
        $disk = Storage::disk('public');

        try {
            if ($validated['source'] === 'upload') {
                $path = $request->file('avatar')->store('avatars', 'public');
            } else {
                $body = $gravatar->image($request->user(), $validated['style']);
                $path = 'avatars/gravatar/'.$validated['style'].'-'.Str::uuid().'.png';
                if (! $disk->put($path, $body)) {
                    throw new RuntimeException('Avatar storage failed.');
                }
            }
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Avatar storage failed.');
            }

            DB::transaction(function () use ($request, $path): void {
                $owner = User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
                $owner->avatar = $path;
                if (! $owner->save()) {
                    throw new RuntimeException('Avatar update failed.');
                }
            });
        } catch (Throwable) {
            if (is_string($path)) {
                $disk->delete($path);
            }
            throw ValidationException::withMessages(['avatar' => __('avatar.failed')]);
        }

        return response()->json([
            'avatar_url' => asset('storage/'.$path),
            'style' => $validated['source'] === 'gravatar' ? $validated['style'] : null,
            'message' => __('avatar.saved'),
        ])->header('Cache-Control', 'no-store');
    }
}
