<?php

namespace App\Http\Controllers;

use App\Enums\MessageEligibility;
use App\Enums\ProfileVisibility;
use App\Http\Requests\ProfilePresentationUpdateRequest;
use App\Jobs\SendSocialPushNotification;
use App\Models\Comment;
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
            ->withExists($bookmarkState)
            ->withPollPresentation($viewerId);
        $addPresentationData = static function ($query) use ($viewerId, $loadDisplayPost, $bookmarkState) {
            if ($viewerId !== null) {
                return $query->withInteractiveListing((int) $viewerId);
            }

            return $query
                ->with([
                    'user',
                    'originalPost' => $loadDisplayPost,
                ])
                ->withExists($bookmarkState)
                ->withPollPresentation($viewerId);
        };

        $isFollowing = $viewer !== null
            && $viewer->isNot($user)
            && $viewer->isFollowing($user);
        $canStartConversation = $viewer !== null
            && $eligibility->evaluate($viewer, $user) === MessageEligibility::Allowed;
        $request->attributes->set('profile_message_target', $canStartConversation ? $user->username : null);
        $followersCount = (int) $user->followers_count;
        $followingsCount = (int) $user->followings_count;
        $profileFollowers = $followersCount > 0
            ? $user->followers()
                ->when($viewer === null, static fn ($query) => $query->where('users.profile_visibility', ProfileVisibility::Public->value))
                ->orderByDesc('follows.created_at')
                ->orderByDesc('follows.id')
                ->limit(4)
                ->get(['users.id', 'users.name', 'users.username', 'users.avatar'])
            : collect();
        $profileFollowings = $followingsCount > 0
            ? $user->followings()
                ->when($viewer === null, static fn ($query) => $query->where('users.profile_visibility', ProfileVisibility::Public->value))
                ->orderByDesc('follows.created_at')
                ->orderByDesc('follows.id')
                ->limit(4)
                ->get(['users.id', 'users.name', 'users.username', 'users.avatar'])
            : collect();

        $similarAccounts = User::query()
            ->where('users.id', '<>', $user->id)
            ->when($viewer !== null, static function ($query) use ($viewer): void {
                $query->where('users.id', '<>', $viewer->id)
                    ->whereNotIn('users.id', $viewer->followings()->select('users.id'));
            })
            ->whereHas('posts')
            ->when($viewer === null, static fn ($query) => $query->where('users.profile_visibility', ProfileVisibility::Public->value))
            ->withMax('posts', 'created_at')
            ->orderByDesc('posts_max_created_at')
            ->orderBy('users.id')
            ->limit(2)
            ->get(['users.id', 'users.name', 'users.username', 'users.avatar']);

        $requestedTab = $request->query('tab');
        $activeTab = is_string($requestedTab) && in_array($requestedTab, ['threads', 'replies', 'media', 'reposts', 'likes'], true)
            ? $requestedTab
            : 'threads';
        $profileReplies = null;

        if ($activeTab === 'replies') {
            $profileReplies = Comment::query()
                ->where('comments.user_id', $user->id)
                ->whereHas('post')
                ->when($viewer === null, static fn ($query) => $query->whereHas(
                    'post.user',
                    static fn ($authorQuery) => $authorQuery->where('profile_visibility', ProfileVisibility::Public->value),
                ))
                ->with(['user', 'post.user'])
                ->orderByDesc('comments.created_at')
                ->orderByDesc('comments.id')
                ->simplePaginate(15)
                ->withQueryString();
            $posts = Post::query()->whereRaw('1 = 0')->simplePaginate(15);
        } else {
            if ($activeTab === 'likes') {
                $tabPosts = Post::query()
                    ->select('posts.*')
                    ->join('likes', 'likes.post_id', '=', 'posts.id')
                    ->where('likes.user_id', $user->id);
            } else {
                $tabPosts = $user->posts()->select('posts.*');

                if ($activeTab === 'media') {
                    $tabPosts->whereHasMedia();
                } elseif ($activeTab === 'reposts') {
                    $tabPosts->whereNotNull('repost_id');
                }
            }

            $postsQuery = $addPresentationData($tabPosts);
            if ($activeTab === 'threads') {
                $postsQuery->orderByRaw('CASE WHEN posts.pinned_at IS NULL THEN 1 ELSE 0 END');
            }
            $posts = $postsQuery
                ->when($activeTab === 'likes', static fn ($query) => $query->orderByDesc('likes.created_at')->orderByDesc('likes.id'))
                ->when($activeTab !== 'likes', static fn ($query) => $query->orderByDesc('posts.created_at')->orderByDesc('posts.id'))
                ->simplePaginate(15)
                ->withQueryString();
        }

        return view('profile', compact(
            'user',
            'posts',
            'activeTab',
            'isFollowing',
            'canStartConversation',
            'followersCount',
            'followingsCount',
            'profileFollowers',
            'profileFollowings',
            'similarAccounts',
            'profileReplies',
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
