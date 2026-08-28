<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 1. Menampilkan Halaman Profil
    public function show($username)
    {
        // Cari user berdasarkan username, jika tidak ada, tampilkan 404
        $user = User::where('username', $username)->firstOrFail();
        
        // Ambil postingan miliknya (termasuk hasil repost)
        $posts = $user->posts()->with(['user', 'likes', 'comments.user', 'originalPost.user'])->latest()->get();

        return view('profile', compact('user', 'posts'));
    }

    // 2. Tombol Aksi Ikuti / Batal Mengikuti
    public function toggleFollow(User $user)
    {
        // Cegah user mengikuti dirinya sendiri
        if (auth()->id() === $user->id) {
            return back();
        }

        $currentUser = auth()->user();

        // Jika sudah follow, maka batalkan (unfollow). Jika belum, maka follow!
        if ($currentUser->isFollowing($user)) {
            $currentUser->followings()->detach($user->id);
        } else {
            $currentUser->followings()->attach($user->id);
        }

        return back();
    }
	
	// Menampilkan Halaman Edit Profil
    public function edit()
    {
        $user = auth()->user();
        return view('edit-profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'bio' => 'nullable|string|max:160',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user->name = $validated['name'];
        $user->bio = $validated['bio'];

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }
        
        if ($request->hasFile('cover')) {
            if ($user->cover) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->cover);
            }
            $user->cover = $request->file('cover')->store('covers', 'public');
        }

        $user->save();

        return redirect()->route('user.profile', $user->username);
    }
}