<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Notifications\PostLiked; // Tambahkan baris ini
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Post $post, Request $request)
    {
        // Cari apakah user ini sudah menyukai postingan ini
        $like = $post->likes()->where('user_id', $request->user()->id)->first();

        if ($like) {
            // Jika sudah like, hapus (Unlike)
            $like->delete(); 
        } else {
            // Jika belum, buat Like baru
            try {
                $post->likes()->create([
                    'user_id' => $request->user()->id
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateLikeConflict($exception)) {
                    throw $exception;
                }

                return back();
            }

            // --- PELATUK NOTIFIKASI LIKE ---
            // Cegah kirim notifikasi jika user menyukai postingannya sendiri
            if ($post->user_id !== $request->user()->id) {
                $post->user->notify(new PostLiked($request->user(), $post));
            }
        }

        // Kembalikan ke halaman sebelumnya
        return back();
    }

    private function isDuplicateLikeConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if ((string) $sqlState !== '23000') {
            return false;
        }

        return str_contains($message, 'likes_user_id_post_id_unique')
            || (
                str_contains($message, 'likes.user_id')
                && str_contains($message, 'likes.post_id')
            );
    }
}
