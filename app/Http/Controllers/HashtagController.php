<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use Illuminate\Http\Request;

class HashtagController extends Controller
{
    public function show(Request $request, $name)
    {
        // 1. Cari tagar berdasarkan nama (huruf kecil)
        $hashtag = Hashtag::where('name', strtolower($name))->firstOrFail();
        
        // 2. Ambil semua postingan yang memiliki tagar ini, urutkan dari yang terbaru
        $posts = $hashtag->posts()
            ->withInteractiveListing($request->user()->getKey())
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->paginate(15);
        
        // 3. Tampilkan ke halaman khusus tagar
        return view('hashtag', compact('hashtag', 'posts'));
    }
}
