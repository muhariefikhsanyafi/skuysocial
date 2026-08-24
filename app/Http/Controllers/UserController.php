<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Fungsi untuk menampilkan halaman profil
    public function show(User $user)
    {
        // Ambil SEMUA postingan khusus milik user ini saja (beserta like & komentarnya)
        $posts = $user->posts()->with(['user', 'likes', 'comments.user'])->latest()->get();
        
        return view('user-profile', compact('user', 'posts'));
    }
}