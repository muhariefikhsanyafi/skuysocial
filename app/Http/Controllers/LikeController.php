<?php

namespace App\Http\Controllers;

use App\Jobs\SendSocialPushNotification;
use App\Models\Post;
use App\Notifications\PostLiked;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function toggle(Post $post, Request $request)
    {
        $targetPost = $post->interactionTarget();
        $isJson = $request->expectsJson();
        $userId = $request->user()->id;
        $desiredLiked = null;

        if ($isJson && $request->exists('liked')) {
            $liked = $request->input('liked');

            if (is_bool($liked)) {
                $desiredLiked = $liked;
            } elseif ($liked === 1 || $liked === '1') {
                $desiredLiked = true;
            } elseif ($liked === 0 || $liked === '0') {
                $desiredLiked = false;
            } else {
                return response()->json([
                    'message' => trans('threads.feedback.invalid'),
                    'code' => 'invalid_like_request',
                ], 422);
            }
        }

        // Cari apakah user ini sudah menyukai postingan ini
        $like = $targetPost->likes()->where('user_id', $userId)->first();
        $newlyLiked = false;

        if ($like && $desiredLiked !== true) {
            // Jika sudah like, hapus (Unlike)
            $like->delete();
        } elseif (! $like && $desiredLiked !== false) {
            try {
                DB::transaction(function () use ($targetPost, $userId, $request, &$newlyLiked): void {
                    // Jika belum, buat Like baru
                    $targetPost->likes()->create([
                        'user_id' => $userId,
                    ]);

                    $newlyLiked = true;

                    // --- PELATUK NOTIFIKASI LIKE ---
                    // Cegah kirim notifikasi jika user menyukai postingannya sendiri
                    if ($targetPost->user_id !== $userId) {
                        $targetPost->user->notify(new PostLiked($request->user(), $targetPost));
                    }
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateLikeConflict($exception)) {
                    throw $exception;
                }
            }
        }

        if ($isJson) {
            if ($newlyLiked && $targetPost->user_id !== $userId) {
                SendSocialPushNotification::dispatchAfterCommit(
                    (int) $targetPost->user_id,
                    SendSocialPushNotification::LIKE,
                    (int) $userId,
                    (string) $request->user()->name,
                    (string) $targetPost->uid,
                );
            }

            return response()->json([
                'post_uid' => (string) $targetPost->uid,
                'liked' => $targetPost->likes()->where('user_id', $userId)->exists(),
                'like_count' => $targetPost->likes()->count(),
            ]);
        }

        if ($newlyLiked && $targetPost->user_id !== $userId) {
            SendSocialPushNotification::dispatchAfterCommit(
                (int) $targetPost->user_id,
                SendSocialPushNotification::LIKE,
                (int) $userId,
                (string) $request->user()->name,
                (string) $targetPost->uid,
            );
        }

        // Kembalikan ke halaman sebelumnya
        return back();
    }

    private function isDuplicateLikeConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if (! in_array((string) $sqlState, ['23000', '23505'], true)) {
            return false;
        }

        return str_contains($message, 'likes_user_id_post_id_unique')
            || (
                str_contains($message, 'likes.user_id')
                && str_contains($message, 'likes.post_id')
            );
    }
}
