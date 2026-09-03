<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = $request->user()->getKey();

        $posts = Post::query()
            ->select('posts.*')
            ->join('bookmarks', 'bookmarks.post_id', '=', 'posts.id')
            ->where('bookmarks.user_id', $viewerId)
            ->with(['user', 'originalPost.user'])
            ->orderByDesc('bookmarks.created_at')
            ->orderByDesc('bookmarks.id')
            ->simplePaginate(15);

        return view('bookmarks', compact('posts'));
    }

    public function store(Post $post, Request $request)
    {
        $targetPost = $post->originalPost ?? $post;

        try {
            Bookmark::query()->create([
                'user_id' => $request->user()->getKey(),
                'post_id' => $targetPost->getKey(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateBookmarkConflict($exception)) {
                throw $exception;
            }
        }

        return back();
    }

    public function destroy(Post $post, Request $request)
    {
        $targetPost = $post->originalPost ?? $post;

        Bookmark::query()
            ->where('user_id', $request->user()->getKey())
            ->where('post_id', $targetPost->getKey())
            ->delete();

        return back();
    }

    private function isDuplicateBookmarkConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = strtolower($exception->getMessage());

        if (! in_array((string) $sqlState, ['23000', '23505'], true)) {
            return false;
        }

        return str_contains($message, 'bookmarks_user_id_post_id_unique')
            || (
                str_contains($message, 'bookmarks.user_id')
                && str_contains($message, 'bookmarks.post_id')
            );
    }
}
