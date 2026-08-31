<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        // Normalize and validate the search input before using it in a query.
        $rawQuery = $request->input('q');

        if (is_string($rawQuery)) {
            $request->merge(['q' => trim($rawQuery)]);
        }

        $query = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ])['q'] ?? '';

        $users = collect();
        $posts = collect();

        // Jika user mengetik sesuatu, mulai pencarian ke database
        if ($query !== '') {
            // 1. Cari berdasarkan Nama atau Username (Maksimal ambil 5)
            $users = User::where('name', 'like', "%{$query}%")
                         ->orWhere('username', 'like', "%{$query}%")
                         ->take(5)
                         ->get();

            // 2. Cari di dalam teks konten postingan (Maksimal ambil 20)
            $posts = Post::with('user')
                         ->where('content', 'like', "%{$query}%")
                         ->orderByDesc('created_at')
                         ->orderByDesc('id')
                         ->simplePaginate(20)
                         ->withQueryString();
        }

        // Kirim data ke tampilan halaman pencarian
        return view('search', compact('query', 'users', 'posts'));
    }
}
