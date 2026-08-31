<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
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
                    $query->where('post_id', $post->getKey());
                }),
            ],
        ]);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

		// --- PELATUK NOTIFIKASI BALASAN ---
        // Cegah kirim notifikasi jika user membalas postingannya sendiri
        if ($post->user_id !== auth()->id()) {
            $post->user->notify(new \App\Notifications\CommentPosted(auth()->user(), $post));
        }

        return back();
    }
	
	// Menyukai atau membatalkan suka pada komentar
    public function like(\App\Models\Comment $comment)
    {
        // Fitur 'toggle' akan menambah like jika belum, dan menghapus jika sudah ada
        $comment->likes()->toggle(auth()->id());
        
        return back();
    }
}
