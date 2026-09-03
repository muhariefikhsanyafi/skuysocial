<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\Hashtag;

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
        $hashtags = collect();
        $topics = collect();

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

            // Cari tagar berdasarkan nama yang sudah dinormalisasi (maksimal 5)
            $hashtagQuery = str_starts_with($query, '#')
                ? substr($query, 1)
                : $query;
            $hashtagQuery = strtolower($hashtagQuery);

            if ($hashtagQuery !== '') {
                $hashtags = Hashtag::query()
                    ->where('name', 'like', "%{$hashtagQuery}%")
                    ->orderBy('name')
                    ->limit(5)
                    ->get();
            }

            // Cari topik unik berdasarkan q asli (maksimal 5)
            $topics = Post::query()
                ->whereNotNull('topic')
                ->where('topic', '<>', '')
                ->where('topic', 'like', "%{$query}%")
                ->select('topic')
                ->distinct()
                ->orderBy('topic')
                ->limit(5)
                ->pluck('topic');
        }

        // Kirim data ke tampilan halaman pencarian
        return view('search', compact('query', 'users', 'posts', 'hashtags', 'topics'));
    }
}
