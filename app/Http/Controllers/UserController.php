<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 1. Menampilkan Halaman Profil
    public function show($username)
    {
        // Cari user berdasarkan username, jika tidak ada, tampilkan 404
        $user = User::query()
            ->withCount(['followings', 'followers'])
            ->where('username', $username)
            ->firstOrFail();

        $isFollowing = auth()->id() !== $user->id
            && auth()->user()->isFollowing($user);
        $followersCount = (int) $user->followers_count;
        $followingsCount = (int) $user->followings_count;
        
        // Ambil postingan miliknya (termasuk hasil repost)
        $posts = $user->posts()
            ->with(['user', 'originalPost.user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(15);

        return view('profile', compact(
            'user',
            'posts',
            'isFollowing',
            'followersCount',
            'followingsCount',
        ));
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
            try {
                $currentUser->followings()->attach($user->id);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateFollowConflict($exception)) {
                    throw $exception;
                }

                return back();
            }

			// --- PELATUK NOTIFIKASI FOLLOW ---
            $user->notify(new \App\Notifications\UserFollowed($currentUser));
        }

        return back();
    }

    private function isDuplicateFollowConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if ((string) $sqlState !== '23000') {
            return false;
        }

        return str_contains($message, 'follows_follower_id_following_id_unique')
            || (
                str_contains($message, 'follows.follower_id')
                && str_contains($message, 'follows.following_id')
            );
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
            'bio' => 'nullable|string|max:255',
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
