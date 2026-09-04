<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // 1. Menampilkan Halaman Profil
    public function show(Request $request, $username)
    {
        // Cari user berdasarkan username, jika tidak ada, tampilkan 404
        $user = User::query()
            ->withCount(['followings', 'followers'])
            ->where('username', $username)
            ->firstOrFail();

        $viewerId = $request->user()->getKey();
        $bookmarkState = [
            'bookmarks as bookmarked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
        ];
        $loadDisplayPost = fn ($query) => $query
            ->with('user')
            ->withExists($bookmarkState);

        $isFollowing = auth()->id() !== $user->id
            && auth()->user()->isFollowing($user);
        $followersCount = (int) $user->followers_count;
        $followingsCount = (int) $user->followings_count;
        
        $activeTab = $request->query('tab') === 'likes' ? 'likes' : 'threads';

        if ($activeTab === 'likes') {
            $posts = Post::query()
                ->select('posts.*')
                ->join('likes', 'likes.post_id', '=', 'posts.id')
                ->where('likes.user_id', $user->id)
                ->with([
                    'user',
                    'originalPost' => $loadDisplayPost,
                ])
                ->withExists($bookmarkState)
                ->orderByDesc('likes.created_at')
                ->orderByDesc('likes.id')
                ->simplePaginate(15)
                ->withQueryString();
        } else {
            // Ambil postingan miliknya (termasuk hasil repost)
            $posts = $user->posts()
                ->with([
                    'user',
                    'originalPost' => $loadDisplayPost,
                ])
                ->withExists($bookmarkState)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->simplePaginate(15);
        }

        return view('profile', compact(
            'user',
            'posts',
            'activeTab',
            'isFollowing',
            'followersCount',
            'followingsCount',
        ));
    }

    public function followers(User $user)
    {
        $users = $user->followers()
            ->orderByDesc('follows.created_at')
            ->orderByDesc('follows.id')
            ->simplePaginate(15);

        return $this->followListView($user, $users, 'Pengikut', 'Belum ada pengikut');
    }

    public function following(User $user)
    {
        $users = $user->followings()
            ->orderByDesc('follows.created_at')
            ->orderByDesc('follows.id')
            ->simplePaginate(15);

        return $this->followListView($user, $users, 'Mengikuti', 'Belum mengikuti siapa pun');
    }

    private function followListView(User $profileUser, $users, string $title, string $emptyMessage)
    {
        $listedUserIds = $users->getCollection()
            ->pluck('id')
            ->reject(fn ($id): bool => (int) $id === (int) auth()->id())
            ->values();

        $followedUserIds = $listedUserIds->isEmpty()
            ? collect()
            : DB::table('follows')
                ->where('follower_id', auth()->id())
                ->whereIn('following_id', $listedUserIds)
                ->pluck('following_id');

        return view('follow-list', compact(
            'profileUser',
            'users',
            'title',
            'emptyMessage',
            'followedUserIds',
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
                DB::transaction(function () use ($currentUser, $user): void {
                    $currentUser->followings()->attach($user->id);

                    // --- PELATUK NOTIFIKASI FOLLOW ---
                    $user->notify(new \App\Notifications\UserFollowed($currentUser));
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateFollowConflict($exception)) {
                    throw $exception;
                }
            }
        }

        return back();
    }

    private function isDuplicateFollowConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if (! in_array((string) $sqlState, ['23000', '23505'], true)) {
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
