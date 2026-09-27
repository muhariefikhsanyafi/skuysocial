<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
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
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'topic' => 'nullable|string|max:50',
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

        if (! config('frontend.post_detail_inertia')) {
            if ($request->header('X-Inertia')) {
                return Inertia::location($request->fullUrl());
            }

            return view('show-post', compact(
                'post',
                'displayPost',
                'comments',
                'expandedReplyRootId',
                'expandedReplies',
            ));
        }

        return Inertia::render('Post/Show', $this->postDetailProps(
            $request,
            $post,
            $displayPost,
            $comments,
            $expandedReplyRootId,
            $expandedReplies,
        ));
    }

    /**
     * Build the explicit page contract used by the React Post Detail pilot.
     *
     * @return array<string, mixed>
     */
    private function postDetailProps(
        Request $request,
        Post $post,
        Post $displayPost,
        Paginator $comments,
        ?int $expandedReplyRootId,
        ?CursorPaginator $expandedReplies,
    ): array {
        $viewer = $request->user();
        $viewerId = $viewer->getKey();
        $isRepost = $post->getKey() !== $displayPost->getKey();
        $shareBaseUrl = $request->fullUrl();
        $canPerformSocialWrites = $viewer->canPerformSocialWrites();

        $canEdit = $post->user_id === $viewerId && $post->repost_id === null;
        $canDelete = $post->user_id === $viewerId;
        $canEditForPage = $canEdit && $canPerformSocialWrites;
        $canDeleteForPage = $canDelete && $canPerformSocialWrites;

        return [
            'post' => [
                'uid' => (string) $post->uid,
                'url' => route('posts.show', $post),
                'is_repost' => $isRepost,
                'reposted_by' => $isRepost ? $this->serializeAuthor($post->user) : null,
            ],
            'display_post' => $this->serializeDisplayPost(
                $displayPost,
                $canEditForPage,
                $canDeleteForPage,
                $canPerformSocialWrites,
            ),
            'comments' => [
                'data' => $comments->getCollection()
                    ->map(fn (Comment $comment): array => $this->serializeRootComment(
                        $comment,
                        $post,
                        $displayPost,
                        $comments->currentPage(),
                        $shareBaseUrl,
                    ))
                    ->values()
                    ->all(),
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'has_more' => $comments->hasMorePages(),
                'next_url' => $comments->nextPageUrl(),
                'previous_url' => $comments->previousPageUrl(),
            ],
            'expanded_reply_root_id' => $expandedReplyRootId,
            'expanded_replies' => $expandedReplies === null ? null : [
                'data' => $expandedReplies->getCollection()
                    ->map(fn (Comment $reply): array => $this->serializeComment(
                        $reply,
                        $displayPost,
                        $shareBaseUrl,
                        false,
                        'reply',
                    ))
                    ->values()
                    ->all(),
                'has_more' => $expandedReplies->hasMorePages(),
                'next_url' => $expandedReplies->nextPageUrl(),
                'previous_url' => $expandedReplies->previousPageUrl(),
                'per_page' => $expandedReplies->perPage(),
            ],
            'viewer' => [
                'id' => $viewerId,
                'author' => $this->serializeAuthor($viewer),
                'can_perform_social_writes' => $canPerformSocialWrites,
            ],
            'routes' => [
                'feed' => route('feed'),
                'like' => route('posts.like', $displayPost),
                'repost' => route('posts.repost', $displayPost),
                'bookmark_store' => route('bookmarks.store', $displayPost),
                'bookmark_destroy' => route('bookmarks.destroy', $displayPost),
                'comment' => route('comments.store', $displayPost),
                'share' => $request->url(),
                'tags_base' => url('/tags'),
                'edit' => $canEditForPage ? route('posts.edit', $post) : null,
                'delete' => $canDeleteForPage ? route('posts.destroy', $post) : null,
            ],
            'translations' => $this->postDetailTranslations(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDisplayPost(
        Post $post,
        bool $canEdit,
        bool $canDelete,
        bool $canPerformSocialWrites,
    ): array {
        return [
            'uid' => (string) $post->uid,
            'content' => (string) ($post->content ?? ''),
            'datetime' => $post->created_at->toIso8601String(),
            'display_time' => $post->created_at->locale(app()->getLocale())->diffForHumans(null, true),
            'author' => $this->serializeAuthor($post->user),
            'topic' => $post->topic,
            'media' => $this->serializeMedia($post),
            'interactions' => [
                'liked' => (bool) ($post->liked_by_current_user ?? false),
                'like_count' => (int) ($post->likes_count ?? 0),
                'bookmarked' => (bool) ($post->bookmarked_by_current_user ?? false),
                'reposted' => (bool) ($post->reposted_by_current_user ?? false),
                'repost_count' => (int) ($post->reposts_count ?? 0),
                'comment_count' => (int) ($post->comments_count ?? 0),
            ],
            'capabilities' => [
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
                'can_perform_social_writes' => $canPerformSocialWrites,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAuthor(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'username' => (string) $user->username,
            'avatar_url' => is_string($user->avatar) && trim($user->avatar) !== ''
                ? asset('storage/'.$user->avatar)
                : null,
            'is_verified' => (bool) $user->is_verified,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeMedia(Post $post): array
    {
        $mediaPaths = [];
        $modernMedia = is_array($post->images) ? $post->images : [];

        foreach ($modernMedia as $mediaPath) {
            if (is_string($mediaPath) && trim($mediaPath) !== '' && ! in_array($mediaPath, $mediaPaths, true)) {
                $mediaPaths[] = $mediaPath;
            }
        }

        $legacyImage = $post->getAttribute('image');
        if ($mediaPaths === [] && is_string($legacyImage) && trim($legacyImage) !== '') {
            $mediaPaths[] = $legacyImage;
        }

        return array_values(array_map(
            static fn (string $mediaPath): array => [
                'type' => 'image',
                'url' => asset('storage/'.$mediaPath),
                'full_url' => asset('storage/'.$mediaPath),
                'alt' => __('threads.media.photo'),
                'full_alt' => __('threads.media.full_photo'),
            ],
            $mediaPaths,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRootComment(
        Comment $comment,
        Post $routePost,
        Post $displayPost,
        int $commentsPage,
        string $shareBaseUrl,
    ): array {
        $serialized = $this->serializeComment($comment, $displayPost, $shareBaseUrl, true);
        $serialized['reply_expansion_url'] = route('posts.show', [
            'post' => $routePost,
            'comments_page' => $commentsPage,
            'replies_for' => $comment->getKey(),
        ]).'#comment-'.$comment->getKey();

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(
        Comment $comment,
        Post $displayPost,
        string $shareBaseUrl,
        bool $includeReplies,
        string $anchor = 'comment',
    ): array {
        $serialized = [
            'id' => (int) $comment->getKey(),
            'content' => (string) $comment->content,
            'datetime' => $comment->created_at->toIso8601String(),
            'display_time' => $comment->created_at->locale(app()->getLocale())->diffForHumans(null, true),
            'author' => $this->serializeAuthor($comment->user),
            'like_count' => (int) ($comment->likes_count ?? 0),
            'liked' => (bool) ($comment->liked_by_current_user ?? false),
            'replies_count' => (int) ($comment->replies_count ?? 0),
            'replies' => [],
            'actions' => [
                'like_url' => route('comments.like', $comment),
                'reply_url' => route('comments.store', $displayPost),
                'share_url' => $shareBaseUrl.'#'.$anchor.'-'.$comment->getKey(),
            ],
        ];

        if ($includeReplies && $comment->relationLoaded('feedReplies')) {
            $serialized['replies'] = $comment->feedReplies
                ->map(fn (Comment $reply): array => $this->serializeComment(
                    $reply,
                    $displayPost,
                    $shareBaseUrl,
                    false,
                    'reply',
                ))
                ->values()
                ->all();
        }

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function postDetailTranslations(): array
    {
        return [
            'back_to_feed' => __('navigation.back_to_feed'),
            'thread' => __('threads.labels.thread'),
            'replies' => __('threads.labels.replies'),
            'like' => __('threads.labels.like'),
            'unlike' => __('threads.actions.unlike'),
            'reply' => __('threads.actions.reply'),
            'repost' => __('threads.actions.repost'),
            'save' => __('threads.actions.save'),
            'remove_from_saved' => __('threads.actions.remove_from_saved'),
            'share' => __('threads.actions.share'),
            'send' => __('threads.actions.send'),
            'view_more_replies' => __('threads.actions.view_more_replies'),
            'image_coming_soon' => __('threads.image_coming_soon'),
            'repost_coming_soon' => __('threads.repost_coming_soon'),
            'open_options' => __('threads.actions.open_options'),
            'verified' => __('threads.verified'),
            'avatar_of' => __('threads.media.avatar_of', ['name' => ':name']),
            'reposted_by' => __('threads.reposted_by'),
            'confirm_delete' => __('threads.confirm_delete'),
            'edit' => __('threads.actions.edit'),
            'delete' => __('threads.actions.delete'),
            'comments_reply_to' => __('threads.comments.reply_to'),
            'thread_link_copied' => __('threads.thread_link_copied'),
            'comment_link_copied' => __('threads.comment_link_copied'),
            'reply_link_copied' => __('threads.reply_link_copied'),
            'photo' => __('threads.media.photo'),
            'full_photo' => __('threads.media.full_photo'),
            'close_preview' => __('threads.media.close_preview'),
            'previous_image' => __('threads.media.previous_image'),
            'next_image' => __('threads.media.next_image'),
            'pagination_previous' => str_replace('&laquo;', '«', __('pagination.previous')),
            'pagination_next' => str_replace('&raquo;', '»', __('pagination.next')),
            'feedback' => [
                'liked' => __('threads.feedback.liked'),
                'unliked' => __('threads.feedback.unliked'),
                'session_expired' => __('threads.feedback.session_expired'),
                'restricted' => __('threads.feedback.restricted'),
                'invalid' => __('threads.feedback.invalid'),
                'failed' => __('threads.feedback.failed'),
                'network' => __('threads.feedback.network'),
            ],
        ];
    }

    // Logika Repost (Posting Ulang)
    public function repost(Request $request, Post $post)
    {
        $targetPost = $post->interactionTarget();
        $actorId = $request->user()->getKey();
        $isJson = $request->expectsJson();
        $desiredReposted = null;

        if ($isJson && $request->exists('reposted')) {
            $desiredReposted = (bool) $request->validate([
                'reposted' => ['required', 'boolean'],
            ])['reposted'];
        }

        $existingRepost = Post::query()
            ->where('user_id', $actorId)
            ->where('repost_id', $targetPost->getKey())
            ->first();
        $shouldBeReposted = $desiredReposted ?? ! $existingRepost;

        if ($shouldBeReposted && ! $existingRepost) {
            try {
                $request->user()->posts()->create([
                    'repost_id' => $targetPost->getKey(),
                    'content' => null,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateRepostConflict($exception)) {
                    throw $exception;
                }
            }
        } elseif (! $shouldBeReposted && $existingRepost) {
            $existingRepost->delete();
        }

        if ($isJson) {
            return response()->json([
                'post_uid' => (string) $targetPost->uid,
                'reposted' => Post::query()
                    ->where('user_id', $actorId)
                    ->where('repost_id', $targetPost->getKey())
                    ->exists(),
                'repost_count' => $targetPost->reposts()->count(),
            ]);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('posts.show', $targetPost));
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
    public function destroy(Request $request, Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        $post->delete();

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('feed'));
        }

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
