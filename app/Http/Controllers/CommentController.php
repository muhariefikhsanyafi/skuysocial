<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:500',
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where(function ($query) use ($post) {
                    $query
                        ->where('post_id', $post->getKey())
                        ->whereNull('parent_id');
                }),
            ],
        ]);

        DB::transaction(function () use ($post, $request, $validated): void {
            $post->comments()->create([
                'user_id' => $request->user()->id,
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            // --- PELATUK NOTIFIKASI BALASAN ---
            // Cegah kirim notifikasi jika user membalas postingannya sendiri
            if ($post->user_id !== $request->user()->id) {
                $post->user->notify(new \App\Notifications\CommentPosted($request->user(), $post));
            }
        });

        return back();
    }
	
	// Menyukai atau membatalkan suka pada komentar
    public function like(\App\Models\Comment $comment)
    {
        // Fitur 'toggle' akan menambah like jika belum, dan menghapus jika sudah ada
        try {
            $comment->likes()->toggle(auth()->id());
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCommentLikeConflict($exception)) {
                throw $exception;
            }
        }
        
        return back();
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
