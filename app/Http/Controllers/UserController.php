<?php

namespace App\Http\Controllers;

use App\Enums\MessageEligibility;
use App\Enums\ProfileVisibility;
use App\Http\Requests\ProfilePresentationUpdateRequest;
use App\Jobs\SendSocialPushNotification;
use App\Models\Post;
use App\Models\User;
use App\Services\MessagingEligibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UserController extends Controller
{
    // 1. Menampilkan Halaman Profil
    public function show(Request $request, string $username, MessagingEligibility $eligibility)
    {
        // Cari user berdasarkan username, jika tidak ada, tampilkan 404
        $user = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $viewer = $request->user();

        if ($user->profile_visibility === ProfileVisibility::MembersOnly && $viewer === null) {
            return redirect()->guest(route('login'));
        }

        $user->load('profileLinks')->loadCount(['followings', 'followers']);
        $viewerId = $viewer?->getKey();
        $bookmarkState = [
            'bookmarks as bookmarked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
        ];
        $loadDisplayPost = fn ($query) => $query
            ->with('user')
            ->withExists($bookmarkState);

        $isFollowing = $viewer !== null
            && $viewer->isNot($user)
            && $viewer->isFollowing($user);
        $canStartConversation = $viewer !== null
            && $eligibility->evaluate($viewer, $user) === MessageEligibility::Allowed;
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
            'canStartConversation',
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

        return $this->followListView(
            $user,
            $users,
            __('profile.followers'),
            __('profile.empty.followers'),
        );
    }

    public function following(User $user)
    {
        $users = $user->followings()
            ->orderByDesc('follows.created_at')
            ->orderByDesc('follows.id')
            ->simplePaginate(15);

        return $this->followListView(
            $user,
            $users,
            __('profile.following'),
            __('profile.empty.following'),
        );
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

                    SendSocialPushNotification::dispatchAfterCommit(
                        (int) $user->getKey(),
                        SendSocialPushNotification::FOLLOW,
                        (int) $currentUser->getKey(),
                        (string) $currentUser->name,
                    );
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
    public function edit(Request $request, string $username)
    {
        $user = $request->user();
        abort_unless($username === $user->username, 404);
        $user->load('profileLinks');

        return view('edit-profile', compact('user'));
    }

    public function update(ProfilePresentationUpdateRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        if (array_key_exists('bio', $validated)) {
            $user->bio = $validated['bio'];
        }
        if (array_key_exists('profile_visibility', $validated)) {
            $user->profile_visibility = $validated['profile_visibility'];
        }

        $disk = Storage::disk('public');
        $oldAvatar = $user->avatar;
        $oldCover = $user->cover;
        $newAvatar = null;
        $newCover = null;
        $newPaths = [];

        try {
            if ($request->hasFile('avatar')) {
                $newAvatar = $request->file('avatar')->store('avatars', 'public');
                if (! is_string($newAvatar)) {
                    throw new RuntimeException('Failed to store the profile avatar.');
                }

                $newPaths[] = $newAvatar;
                $user->avatar = $newAvatar;
            }

            if ($request->hasFile('cover')) {
                $newCover = $request->file('cover')->store('covers', 'public');
                if (! is_string($newCover)) {
                    throw new RuntimeException('Failed to store the profile cover.');
                }

                $newPaths[] = $newCover;
                $user->cover = $newCover;
            }

            DB::transaction(function () use ($user, $validated): void {
                if (! $user->save()) {
                    throw new RuntimeException('Failed to save profile changes.');
                }

                $replaceProfileLinks = array_key_exists('profile_links', $validated)
                    || array_key_exists('profile_links_present', $validated);

                if (! $replaceProfileLinks) {
                    return;
                }

                $user->profileLinks()->delete();

                foreach (array_values($validated['profile_links'] ?? []) as $sortOrder => $link) {
                    $user->profileLinks()->create([
                        'label' => $link['label'],
                        'url' => $link['url'],
                        'sort_order' => $sortOrder,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($newPaths);

            throw $exception;
        }

        $obsoletePaths = array_filter([
            $newAvatar !== null && $newAvatar !== $oldAvatar ? $oldAvatar : null,
            $newCover !== null && $newCover !== $oldCover ? $oldCover : null,
        ]);
        $disk->delete(array_values(array_unique($obsoletePaths)));

        return redirect()->route('user.profile', ['username' => $user->username]);
    }
}
