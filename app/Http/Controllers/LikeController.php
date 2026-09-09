<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Notifications\PostLiked; // Tambahkan baris ini
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function toggle(Post $post, Request $request)
    {
        $targetPost = $post->interactionTarget();

        // Cari apakah user ini sudah menyukai postingan ini
        $like = $targetPost->likes()->where('user_id', $request->user()->id)->first();

        if ($like) {
            // Jika sudah like, hapus (Unlike)
            $like->delete(); 
        } else {
            try {
                DB::transaction(function () use ($targetPost, $request): void {
                    // Jika belum, buat Like baru
                    $targetPost->likes()->create([
                        'user_id' => $request->user()->id
                    ]);

                    // --- PELATUK NOTIFIKASI LIKE ---
                    // Cegah kirim notifikasi jika user menyukai postingannya sendiri
                    if ($targetPost->user_id !== $request->user()->id) {
                        $targetPost->user->notify(new PostLiked($request->user(), $targetPost));
                    }
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateLikeConflict($exception)) {
                    throw $exception;
                }
            }
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
