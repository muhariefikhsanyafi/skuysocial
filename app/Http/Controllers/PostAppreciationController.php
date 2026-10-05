<?php

namespace App\Http\Controllers;

use App\Actions\GivePostAppreciation;
use App\Actions\PostAppreciationResult;
use App\Enums\AppreciationType;
use App\Enums\PostAppreciationStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostAppreciationController extends Controller
{
    public function store(
        Request $request,
        Post $post,
        GivePostAppreciation $giveAppreciation,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $allowedTypes = array_map(
            static fn (AppreciationType $type): string => $type->value,
            AppreciationType::cases(),
        );
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in($allowedTypes)],
        ], [
            'type.required' => __('appreciation.validation.type_required'),
            'type.string' => __('appreciation.validation.type_invalid'),
            'type.in' => __('appreciation.validation.type_invalid'),
        ]);

        $targetPost = $post->interactionTarget();
        $result = $giveAppreciation->handle(
            $user,
            $targetPost,
            AppreciationType::from($validated['type']),
        );
        $message = $this->message($result);
        $isError = in_array($result->status, [
            PostAppreciationStatus::InsufficientPoints,
            PostAppreciationStatus::SelfAppreciationForbidden,
        ], true);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result->status->value,
                'type' => $result->type?->value,
                'appreciation_count' => $targetPost->appreciations()->count(),
                'message' => $message,
            ], match ($result->status) {
                PostAppreciationStatus::Appreciated => 201,
                PostAppreciationStatus::AlreadyAppreciated => 200,
                PostAppreciationStatus::InsufficientPoints => 422,
                PostAppreciationStatus::SelfAppreciationForbidden => 403,
            });
        }

        return back()->with([
            'post_appreciation_feedback' => $message,
            'post_appreciation_feedback_error' => $isError,
            'post_appreciation_post_uid' => (string) $targetPost->uid,
        ]);
    }

    private function message(PostAppreciationResult $result): string
    {
        return match ($result->status) {
            PostAppreciationStatus::Appreciated => __('appreciation.feedback.success', [
                'type' => __('appreciation.types.'.$result->type->value),
                'cost' => GivePostAppreciation::COST,
                'unit' => __('points.unit'),
            ]),
            PostAppreciationStatus::AlreadyAppreciated => __('appreciation.feedback.already_appreciated', [
                'type' => __('appreciation.types.'.$result->type->value),
            ]),
            PostAppreciationStatus::InsufficientPoints => __('appreciation.feedback.insufficient_points', [
                'cost' => GivePostAppreciation::COST,
                'unit' => __('points.unit'),
            ]),
            PostAppreciationStatus::SelfAppreciationForbidden => __('appreciation.feedback.self_appreciation_forbidden'),
        };
    }
}
