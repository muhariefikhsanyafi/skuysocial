<?php

namespace App\Http\Controllers;

use App\Models\Post;
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
            $post->likes()->create([
                'user_id' => $request->user()->id
            ]); 
        }

        // Kembalikan ke halaman sebelumnya
        return back();
    }
}