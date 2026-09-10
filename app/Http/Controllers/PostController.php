<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class PostController extends Controller
{
    // Menampilkan halaman Feed
    public function index(Request $request)
    {
        $isEnhancedFeedRequest = $request->header('X-Feed-Fragment') === '1';
        $viewerId = $request->user()->getKey();
        $loadDisplayPost = function ($query) use ($viewerId): void {
            $query
                ->with(['user', 'feedComments.user', 'feedComments.feedReplies.user'])
                ->withCount([
                    'likes',
                    'comments',
                    'comments as root_comments_count' => fn (Builder $query) => $query->whereNull('parent_id'),
                    'reposts',
                ])
                ->withExists([
                    'likes as liked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                    'reposts as reposted_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                    'bookmarks as bookmarked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                ]);
        };

        try {
            $posts = Post::query()
                ->with([
                    'user',
                    'feedComments.user',
                    'feedComments.feedReplies.user',
                    'originalPost' => $loadDisplayPost,
                ])
                ->withCount([
                    'likes',
                    'comments',
                    'comments as root_comments_count' => fn (Builder $query) => $query->whereNull('parent_id'),
                    'reposts',
                ])
                ->withExists([
                    'likes as liked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                    'reposts as reposted_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                    'bookmarks as bookmarked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->cursorPaginate(10);
        } catch (UnexpectedValueException $exception) {
            if (! $isEnhancedFeedRequest) {
                throw $exception;
            }

            abort(422);
        }

        if ($isEnhancedFeedRequest) {
            abort_if($posts->cursor() === null, 422);

            return response()
                ->json([
                    'html' => view('partials.feed-threads', compact('posts'))->render(),
                    'has_more' => $posts->hasMorePages(),
                    'next_url' => $posts->nextPageUrl(),
                ])
                ->header('Cache-Control', 'no-store, private')
                ->header('Vary', 'X-Feed-Fragment');
        }

        $unreadNotificationCount = $request->user()->unreadNotifications()->count();

        $availableTopics = Post::query()
            ->whereNotNull('topic')
            ->where('topic', '<>', '')
            ->distinct()
            ->orderBy('topic')
            ->limit(20)
            ->pluck('topic');

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

        return view('feed', compact(
            'posts',
            'trendingHashtags',
            'unreadNotificationCount',
            'availableTopics',
        ));
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

        $imagePaths = [];

        try {
            // Jika user mengunggah file gambar (multi)
            if ($request->hasFile('images')) {
                // Looping untuk menyimpan setiap gambar yang dipilih
                foreach ($request->file('images') as $image) {
                    $path = $image->store('posts', 'public');
                    if (! is_string($path)) {
                        throw new RuntimeException('Failed to store a post image.');
                    }

                    $imagePaths[] = $path;
                }
                // Masukkan array lokasi gambar ke data validasi kolom 'images'
                $validated['images'] = $imagePaths;
            }

            DB::transaction(function () use ($request, $validated): void {
                // Simpan postingan atas nama user yang sedang login
                $post = $request->user()->posts()->create($validated);

                $this->syncHashtags($post);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePaths);

            throw $exception;
        }

        return redirect()->route('feed');
    }
    
    // Menampilkan Halaman Detail Postingan Tunggal
    public function show(Request $request, Post $post)
    {
        $viewerId = $request->user()->getKey();

        $post->load(['user', 'originalPost.user']);

        $displayPost = $post->interactionTarget();
        $displayPost
            ->loadCount(['likes', 'comments', 'reposts'])
            ->loadExists([
                'likes as liked_by_current_user' => fn (Builder $query) => $query->where('user_id', $viewerId),
                'reposts as reposted_by_current_user' => fn (Builder $query) => $query->where('user_id', $viewerId),
                'bookmarks as bookmarked_by_current_user' => fn (Builder $query) => $query->where('user_id', $viewerId),
            ]);

        $loadReplyData = function ($query) use ($viewerId): void {
            $query
                ->with('user')
                ->withCount('likes')
                ->withExists([
                    'likes as liked_by_current_user' => fn (Builder $query) => $query->where('user_id', $viewerId),
                ]);
        };

        $commentsPerPage = 20;
        $expandedComment = null;

        if ($request->has('replies_for')) {
            $requestedReplyRootId = $request->query('replies_for');

            abort_unless(is_string($requestedReplyRootId) && ctype_digit($requestedReplyRootId), 404);

            $expandedComment = $displayPost->detailComments()->find((int) $requestedReplyRootId);

            abort_unless($expandedComment instanceof Comment, 404);

            $precedingRootCount = $displayPost->detailComments()
                ->where(function (Builder $query) use ($expandedComment): void {
                    $query
                        ->where('created_at', '>', $expandedComment->created_at)
                        ->orWhere(function (Builder $query) use ($expandedComment): void {
                            $query
                                ->where('created_at', $expandedComment->created_at)
                                ->where('id', '>', $expandedComment->getKey());
                        });
                })
                ->count();
            $canonicalCommentsPage = intdiv($precedingRootCount, $commentsPerPage) + 1;

            if (Paginator::resolveCurrentPage('comments_page') !== $canonicalCommentsPage) {
                $routeParameters = [
                    'post' => $post,
                    'comments_page' => $canonicalCommentsPage,
                    'replies_for' => $expandedComment->getKey(),
                ];
                $replyCursor = $request->query('replies_cursor');

                if (is_string($replyCursor) && $replyCursor !== '') {
                    $routeParameters['replies_cursor'] = $replyCursor;
                }

                return redirect(
                    route('posts.show', $routeParameters).'#comment-'.$expandedComment->getKey()
                );
            }
        }

        $comments = $displayPost->detailComments()
            ->with([
                'user',
                'feedReplies' => $loadReplyData,
            ])
            ->withCount(['likes', 'replies'])
            ->withExists([
                'likes as liked_by_current_user' => fn (Builder $query) => $query->where('user_id', $viewerId),
            ])
            ->simplePaginate($commentsPerPage, ['*'], 'comments_page');

        $expandedReplyRootId = null;
        $expandedReplies = null;

        if ($expandedComment instanceof Comment) {
            $expandedReplyRootId = $expandedComment->getKey();
            $expandedReplyQuery = $expandedComment->detailReplies()
                ->where('post_id', $displayPost->getKey());
            $loadReplyData($expandedReplyQuery);

            $expandedReplies = $expandedReplyQuery
                ->cursorPaginate(10, ['*'], 'replies_cursor')
                ->withQueryString();
        }

        return view('show-post', compact(
            'post',
            'displayPost',
            'comments',
            'expandedReplyRootId',
            'expandedReplies',
        ));
    }
	
	// Logika Repost (Posting Ulang)
    public function repost(Post $post)
    {
        $targetPost = $post->interactionTarget();

        // Cari apakah user sudah merepost postingan ini sebelumnya
        $actorId = auth()->id();
        $existingRepost = Post::where('user_id', $actorId)
                              ->where('repost_id', $targetPost->id)
                              ->first();

        if ($existingRepost) {
            // Jika sudah diklik, maka batalkan (hapus data repost)
            $existingRepost->delete();
        } else {
            // Jika belum, ciptakan postingan baru sebagai repost
            try {
                auth()->user()->posts()->create([
                    'repost_id' => $targetPost->id,
                    'content' => null, // Teks dikosongkan karena ini murni repost
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateRepostConflict($exception)) {
                    throw $exception;
                }
            }
        }

        return back();
    }

    private function isDuplicateRepostConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if (! in_array((string) $sqlState, ['23000', '23505'], true)) {
            return false;
        }

        return str_contains($message, 'posts_user_id_repost_id_unique')
            || (
                str_contains($message, 'posts.user_id')
                && str_contains($message, 'posts.repost_id')
            );
    }
 
	// Menampilkan halaman form edit
    public function edit(Post $post)
    {
        // Tolak jika yang mencoba mengedit bukan pemiliknya
        abort_if($post->user_id !== auth()->id(), 403);
        abort_if($post->repost_id !== null, 403);

        return view('edit-post', compact('post'));
    }

    // Menyimpan perubahan ke database
    public function update(Request $request, Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        abort_if($post->repost_id !== null, 403);
        
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($post, $validated): void {
            $post->update($validated);
            $this->syncHashtags($post);
        });

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

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $post->getConnection()->transaction(function () use ($post, $hashtagNames): void {
                    $hashtagIds = [];
                    foreach ($hashtagNames as $name) {
                        $hashtagIds[] = Hashtag::firstOrCreate(['name' => $name])->id;
                    }

                    $post->hashtags()->sync($hashtagIds);
                });

                return;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2 || ! $this->isDuplicateHashtagPivotConflict($exception)) {
                    throw $exception;
                }
            }
        }
    }

    private function isDuplicateHashtagPivotConflict(UniqueConstraintViolationException $exception): bool
    {
        if (strtolower((string) $exception->index) === 'hashtag_post_post_id_hashtag_id_unique') {
            return true;
        }

        $columns = array_map(
            static fn ($column): string => strtolower(trim((string) $column)),
            $exception->columns,
        );
        sort($columns);

        return $columns === ['hashtag_id', 'post_id']
            && str_contains(strtolower($exception->getSql()), 'hashtag_post');
    }
	
	// Fungsi untuk menampilkan postingan berdasarkan topik
    public function topic(Request $request, $topic)
    {
        // Cari postingan yang kolom 'topic'-nya sama dengan parameter URL
        $posts = Post::query()
                    ->where('topic', $topic)
                    ->withInteractiveListing($request->user()->getKey())
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(15);

        // Lempar data postingan dan nama topiknya ke halaman view 'topic'
        return view('topic', compact('posts', 'topic'));
    }
}
