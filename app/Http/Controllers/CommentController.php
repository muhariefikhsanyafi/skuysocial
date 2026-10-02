<?php

namespace App\Http\Controllers;

use App\Jobs\SendSocialPushNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentPosted;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $targetPost = $post->interactionTarget();

        $validated = $request->validate([
            'content' => 'required|string|max:500',
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where(function ($query) use ($targetPost) {
                    $query
                        ->where('post_id', $targetPost->getKey())
                        ->whereNull('parent_id');
                }),
            ],
        ]);

        $comment = DB::transaction(function () use ($targetPost, $request, $validated): Comment {
            $comment = $targetPost->comments()->create([
                'user_id' => $request->user()->getKey(),
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            $actor = $request->user();
            $postOwnerId = (int) $targetPost->user_id;
            $parentComment = $comment->parent_id === null
                ? null
                : Comment::query()->with('user')->findOrFail($comment->parent_id);

            // Preserve post-owner notifications and add a reply's parent author once.
            $recipientIds = array_values(array_unique(array_filter([
                $postOwnerId === (int) $actor->getKey() ? null : $postOwnerId,
                $parentComment?->user_id === null ? null : (int) $parentComment->user_id,
            ], static fn (?int $recipientId): bool => $recipientId !== null && $recipientId !== (int) $actor->getKey())));

            foreach ($recipientIds as $recipientId) {
                $recipient = (int) $parentComment?->user_id === $recipientId
                    ? $parentComment->user
                    : $targetPost->user;
                $recipient->notify(new CommentPosted($actor, $targetPost, $comment));

                SendSocialPushNotification::dispatchAfterCommit(
                    $recipientId,
                    $comment->parent_id === null
                        ? SendSocialPushNotification::COMMENT
                        : SendSocialPushNotification::REPLY,
                    (int) $actor->getKey(),
                    (string) $actor->name,
                    (string) $targetPost->uid,
                    null,
                    parentCommentId: $comment->parent_id === null ? null : (int) $comment->parent_id,
                );
            }

            return $comment;
        });

        if (! $request->expectsJson()) {
            return back();
        }

        $comment->load('user');
        $parent = $comment->parent_id === null
            ? null
            : Comment::query()->findOrFail($comment->parent_id);
        $rootCommentCount = $targetPost->comments()->whereNull('parent_id')->count();
        $commentCount = $targetPost->comments()->count();
        $response = [
            'post_uid' => (string) $targetPost->uid,
            'comment_count' => (int) $commentCount,
            'root_comment_count' => (int) $rootCommentCount,
            'parent_reply_count' => $parent?->replies()
                ->where('post_id', $targetPost->getKey())
                ->count(),
            'comment' => $this->serializeCreatedComment($comment, $targetPost, $post),
        ];

        if ($request->headers->get('X-Skuy-Comment-Surface') === 'feed') {
            $response['feed_html'] = view('partials.feed-comment-created', [
                'comment' => $comment,
                'displayPost' => $targetPost,
            ])->render();
        }

        return response()->json($response, 201);
    }

    public function like(Request $request, Comment $comment): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            try {
                $comment->likes()->toggle($request->user()->getKey());
            } catch (QueryException $exception) {
                if (! $this->isDuplicateCommentLikeConflict($exception)) {
                    throw $exception;
                }
            }

            return back();
        }

        $validated = $request->validate([
            'liked' => ['required', 'boolean'],
        ]);
        $userId = $request->user()->getKey();

        try {
            if ((bool) $validated['liked']) {
                $comment->likes()->syncWithoutDetaching([$userId]);
            } else {
                $comment->likes()->detach($userId);
            }
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCommentLikeConflict($exception)) {
                throw $exception;
            }
        }

        return response()->json([
            'comment_id' => (int) $comment->getKey(),
            'liked' => $comment->likes()->where('users.id', $userId)->exists(),
            'like_count' => (int) $comment->likes()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCreatedComment(Comment $comment, Post $targetPost, Post $routePost): array
    {
        $commentId = (int) $comment->getKey();
        $isRoot = $comment->parent_id === null;

        return [
            'id' => $commentId,
            'post_uid' => (string) $targetPost->uid,
            'parent_id' => $comment->parent_id === null ? null : (int) $comment->parent_id,
            'content' => (string) $comment->content,
            'datetime' => $comment->created_at->toIso8601String(),
            'display_time' => $comment->created_at->locale(app()->getLocale())->diffForHumans(null, true),
            'author' => $this->serializeAuthor($comment->user),
            'like_count' => 0,
            'liked' => false,
            'replies_count' => 0,
            'replies' => [],
            'actions' => [
                'like_url' => route('comments.like', $comment),
                'reply_url' => route('comments.store', $targetPost),
                'share_url' => route('posts.show', $routePost).'#comment-'.$commentId,
            ],
            'reply_expansion_url' => $isRoot
                ? route('posts.show', [
                    'post' => $routePost,
                    'comments_page' => 1,
                    'replies_for' => $commentId,
                ]).'#comment-'.$commentId
                : null,
        ];
    }

    /**
     * @return array{id: int, name: string, username: string, avatar_url: ?string, is_verified: bool}
     */
    private function serializeAuthor(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'username' => (string) $user->username,
            'avatar_url' => is_string($user->avatar) && trim($user->avatar) !== ''
                ? asset('storage/'.$user->avatar)
                : null,
            'is_verified' => (bool) $user->is_verified,
        ];
    }

    private function isDuplicateCommentLikeConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if (! in_array((string) $sqlState, ['23000', '23505'], true)) {
            return false;
        }

        return str_contains($message, 'comment_likes_comment_id_user_id_unique')
            || (
                str_contains($message, 'comment_likes.comment_id')
                && str_contains($message, 'comment_likes.user_id')
            );
    }
}
