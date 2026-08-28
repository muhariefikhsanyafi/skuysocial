<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:500',
            'parent_id' => 'nullable|exists:comments,id', // <-- Tambahkan validasi ini
        ]);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'parent_id' => $request->parent_id, // <-- Tangkap parent_id dari form
        ]);

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