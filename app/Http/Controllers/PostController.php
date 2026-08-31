<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    // Menampilkan halaman Feed
    public function index()
    {
        // Ambil semua postingan beserta nama pembuatnya, urutkan dari yang terbaru
        // (Kode asli Anda tetap dipertahankan)
        $posts = Post::with(['user', 'likes', 'comments.user', 'originalPost.user'])->latest()->get();

        // --- AWAL LOGIKA SEDANG HANGAT (TRENDING HASHTAGS) ---
        $trendingHashtags = Hashtag::query()
            ->select('hashtags.name as name')
            ->selectRaw('COUNT(DISTINCT hashtag_post.post_id) as post_count')
            ->join('hashtag_post', 'hashtags.id', '=', 'hashtag_post.hashtag_id')
            ->groupBy('hashtags.id', 'hashtags.name')
            ->orderByDesc('post_count')
            ->orderBy('hashtags.name')
            ->limit(5)
            ->get()
            ->mapWithKeys(fn (Hashtag $hashtag): array => [
                $hashtag->name => (int) $hashtag->post_count,
            ])
            ->all();

        // Tambahkan variabel $trendingHashtags ke dalam compact() agar bisa dipakai di tampilan
        return view('feed', compact('posts', 'trendingHashtags'));
    }

    // Menyimpan postingan baru ke database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            // Aturan multi-gambar: maksimal 4 gambar, tiap gambar max 5MB (5120 KB)
            'images'   => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'topic'    => 'nullable|string|max:50',
        ]);

        // Jika user mengunggah file gambar (multi)
        if ($request->hasFile('images')) {
            $imagePaths = [];
            // Looping untuk menyimpan setiap gambar yang dipilih
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('posts', 'public');
            }
            // Masukkan array lokasi gambar ke data validasi kolom 'images'
            $validated['images'] = $imagePaths;
        }

        // Simpan postingan atas nama user yang sedang login dan tampung ke variabel $post
        $post = $request->user()->posts()->create($validated);

        $this->syncHashtags($post);

        return redirect()->route('feed');
    }
    
    // Menampilkan Halaman Detail Postingan Tunggal
    public function show(Post $post)
    {
        // Memuat relasi yang dibutuhkan agar tidak berat (N+1 query)
        $post->load(['user', 'likes', 'comments.user', 'comments.replies.user']);
        
        return view('show-post', compact('post'));
    }
	
	// Logika Repost (Posting Ulang)
    public function repost(Post $post)
    {
        // Cari apakah user sudah merepost postingan ini sebelumnya
        $existingRepost = Post::where('user_id', auth()->id())
                              ->where('repost_id', $post->id)
                              ->first();

        if ($existingRepost) {
            // Jika sudah diklik, maka batalkan (hapus data repost)
            $existingRepost->delete();
        } else {
            // Jika belum, ciptakan postingan baru sebagai repost
            auth()->user()->posts()->create([
                'repost_id' => $post->id,
                'content' => null, // Teks dikosongkan karena ini murni repost
            ]);
        }

        return back();
    }
 
	// Menampilkan halaman form edit
    public function edit(Post $post)
    {
        // Tolak jika yang mencoba mengedit bukan pemiliknya
        abort_if($post->user_id !== auth()->id(), 403);
        return view('edit-post', compact('post'));
    }

    // Menyimpan perubahan ke database
    public function update(Request $request, Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $post->update($validated);
        $this->syncHashtags($post);
        return redirect()->route('feed');
    }

    // Menghapus postingan
    public function destroy(Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        $post->delete();
        return back();
    }

    private function syncHashtags(Post $post): void
    {
        preg_match_all('/#([a-zA-Z0-9_]+)/', $post->content ?? '', $matches);

        $hashtagNames = array_values(array_unique(array_map(
            'strtolower',
            $matches[1] ?? [],
        )));

        $hashtagIds = [];
        foreach ($hashtagNames as $name) {
            $hashtagIds[] = Hashtag::firstOrCreate(['name' => $name])->id;
        }

        $post->hashtags()->sync($hashtagIds);
    }
	
	// Fungsi untuk menampilkan postingan berdasarkan topik
    public function topic($topic)
    {
        // Cari postingan yang kolom 'topic'-nya sama dengan parameter URL
        $posts = \App\Models\Post::with(['user', 'likes', 'comments.user', 'comments.replies.user'])
                    ->where('topic', $topic)
                    ->latest()
                    ->get();

        // Lempar data postingan dan nama topiknya ke halaman view 'topic'
        return view('topic', compact('posts', 'topic'));
    }
}
