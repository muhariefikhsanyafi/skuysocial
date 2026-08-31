<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_post_detail(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Detail content']);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));

        $response->assertOk();
        $this->assertSame($post->id, $response->viewData('post')->id);
    }

    public function test_guest_is_redirected_to_login_from_post_detail(): void
    {
        $post = $this->createPost(User::factory()->create());

        $this->get(route('posts.show', $post))
            ->assertRedirect(route('login'));
    }

    public function test_invalid_uid_returns_not_found_for_authenticated_user(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/post/missing-post-uid')
            ->assertNotFound();
    }

    public function test_post_detail_uses_uid_binding_and_renders_author_and_content(): void
    {
        $author = User::factory()->create(['username' => 'detail-author']);
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Visible detail content']);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertStringContainsString('/post/'.$post->uid, route('posts.show', $post));
        $response->assertSee('detail-author')
            ->assertSee('Visible detail content');
    }

    public function test_post_detail_exposes_scalar_post_counts_and_interaction_state(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($author);

        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        Like::create(['post_id' => $post->id, 'user_id' => $other->id]);
        $root = $this->createComment($post, $other, 'Root detail comment');
        $this->createComment($post, $viewer, 'Reply detail comment', ['parent_id' => $root->id]);
        $this->createPost($other, ['repost_id' => $post->id, 'content' => null]);
        $this->createPost($viewer, ['repost_id' => $post->id, 'content' => null]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $displayPost = $response->viewData('displayPost');

        $this->assertSame(2, $displayPost->likes_count);
        $this->assertSame(2, $displayPost->comments_count);
        $this->assertSame(2, $displayPost->reposts_count);
        $this->assertTrue((bool) $displayPost->liked_by_current_user);
        $this->assertTrue((bool) $displayPost->reposted_by_current_user);
        $this->assertFalse($displayPost->relationLoaded('likes'));
        $response->assertSee('Root detail comment');
    }

    public function test_repost_detail_uses_original_content_author_and_preserves_actor(): void
    {
        $originalAuthor = User::factory()->create(['username' => 'original-detail-author']);
        $reposter = User::factory()->create(['username' => 'detail-reposter']);
        $viewer = User::factory()->create();
        $original = $this->createPost($originalAuthor, ['content' => 'Original detail content']);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $repost));

        $this->assertSame($original->id, $response->viewData('displayPost')->id);
        $response->assertSee('Original detail content')
            ->assertSee('original-detail-author')
            ->assertSee('detail-reposter');
    }

    public function test_root_comments_are_paginated_separately_from_all_comment_count(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 21; $number++) {
            $root = $this->createComment($post, $author, "Root {$number}");
            $this->createComment($post, $viewer, "Reply {$number}", ['parent_id' => $root->id]);
        }

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $comments = $response->viewData('comments');
        $displayPost = $response->viewData('displayPost');

        $this->assertInstanceOf(Paginator::class, $comments);
        $this->assertCount(20, $comments->items());
        $this->assertFalse($comments->getCollection()->contains(fn (Comment $comment): bool => $comment->parent_id !== null));
        $this->assertSame(42, $displayPost->comments_count);
        $this->assertTrue($comments->hasMorePages());
    }

    public function test_root_comments_use_deterministic_created_at_and_id_ordering(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $sameTime = Carbon::create(2026, 9, 1, 12, 0, 0);

        $older = $this->createComment($post, $author, 'Older root', [
            'created_at' => $sameTime->copy()->subMinute(),
            'updated_at' => $sameTime->copy()->subMinute(),
        ]);
        $sameTimeLowerId = $this->createComment($post, $author, 'Same time lower id', [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);
        $sameTimeHigherId = $this->createComment($post, $author, 'Same time higher id', [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');

        $this->assertSame(
            [$sameTimeHigherId->id, $sameTimeLowerId->id, $older->id],
            $comments->getCollection()->pluck('id')->all(),
        );
    }

    public function test_replies_are_bounded_ordered_and_include_scalar_like_state_and_users(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $replyAuthor = User::factory()->create(['username' => 'reply-detail-author']);
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Root with replies');
        $sameTime = Carbon::create(2026, 9, 1, 12, 0, 0);

        $this->createComment($post, $replyAuthor, 'Reply one', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSecond(),
            'updated_at' => $sameTime->copy()->addSecond(),
        ]);
        $replyTwo = $this->createComment($post, $replyAuthor, 'Reply two', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSeconds(2),
            'updated_at' => $sameTime->copy()->addSeconds(2),
        ]);
        $replyThree = $this->createComment($post, $replyAuthor, 'Reply three', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSeconds(3),
            'updated_at' => $sameTime->copy()->addSeconds(3),
        ]);
        $replyTwo->likes()->attach($viewer->id);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');
        $loadedRoot = $comments->first();

        $this->assertCount(2, $loadedRoot->feedReplies);
        $this->assertSame([$replyThree->id, $replyTwo->id], $loadedRoot->feedReplies->pluck('id')->all());
        foreach ($loadedRoot->feedReplies as $reply) {
            $this->assertTrue($reply->relationLoaded('user'));
            $this->assertArrayHasKey('likes_count', $reply->getAttributes());
            $this->assertArrayHasKey('liked_by_current_user', $reply->getAttributes());
            $this->assertFalse($reply->relationLoaded('likes'));
        }
        $this->assertSame('reply-detail-author', $loadedRoot->feedReplies->first()->user->username);
    }

    public function test_reply_limit_is_applied_per_root_comment(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($rootNumber = 1; $rootNumber <= 2; $rootNumber++) {
            $root = $this->createComment($post, $author, "Multiple root {$rootNumber}");

            for ($replyNumber = 1; $replyNumber <= 3; $replyNumber++) {
                $this->createComment($post, $viewer, "Root {$rootNumber} reply {$replyNumber}", [
                    'parent_id' => $root->id,
                ]);
            }
        }

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');

        $this->assertCount(2, $comments->items());
        foreach ($comments as $root) {
            $this->assertTrue($root->relationLoaded('feedReplies'));
            $this->assertCount(2, $root->feedReplies);
        }
    }

    public function test_root_and_reply_like_counts_and_current_user_state_are_scalar(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Liked root');
        $reply = $this->createComment($post, $other, 'Liked reply', ['parent_id' => $root->id]);

        $root->likes()->attach([$viewer->id, $other->id]);
        $reply->likes()->attach($viewer->id);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');
        $loadedRoot = $comments->first();
        $loadedReply = $loadedRoot->feedReplies->first();

        $this->assertSame(2, $loadedRoot->likes_count);
        $this->assertTrue((bool) $loadedRoot->liked_by_current_user);
        $this->assertSame(1, $loadedReply->likes_count);
        $this->assertTrue((bool) $loadedReply->liked_by_current_user);
        $this->assertFalse($loadedRoot->relationLoaded('likes'));
        $this->assertFalse($loadedReply->relationLoaded('likes'));
    }

    public function test_detail_does_not_issue_per_comment_or_reply_like_relation_queries(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 3; $number++) {
            $root = $this->createComment($post, $author, "Query root {$number}");
            $this->createComment($post, $viewer, "Query reply {$number}", ['parent_id' => $root->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('posts.show', $post));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $perCommentLikeQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower(trim($query['query']));

            return str_starts_with($sql, 'select "users".')
                && str_contains($sql, 'from "users" inner join "comment_likes"');
        });

        $this->assertCount(0, $perCommentLikeQueries);
    }

    public function test_detail_with_more_than_one_comment_page_still_renders_first_page(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 21; $number++) {
            $this->createComment($post, $author, "Volume root {$number}");
        }

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $comments = $response->viewData('comments');

        $response->assertOk();
        $this->assertCount(20, $comments->items());
        $this->assertTrue($comments->hasMorePages());

        $secondPage = $this->actingAs($viewer)
            ->get(route('posts.show', $post).'?comments_page=2');
        $secondPageComments = $secondPage->viewData('comments');

        $secondPage->assertOk()->assertSee('Volume root 1');
        $this->assertSame('Volume root 1', $secondPageComments->first()->content);
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Detail post',
        ], $attributes));
    }

    private function createComment(Post $post, User $user, string $content, array $attributes = []): Comment
    {
        return Comment::create(array_merge([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => null,
        ], $attributes));
    }
}
