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

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_render_feed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));

        $response->assertOk();
        $response->assertViewHas('posts', function ($posts): bool {
            return $posts instanceof Paginator;
        });
    }

    public function test_feed_orders_posts_by_created_at_then_id_descending(): void
    {
        $user = User::factory()->create();
        $sameTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        $older = $this->createPost($user, [
            'created_at' => $sameTime->copy()->subMinute(),
            'updated_at' => $sameTime->copy()->subMinute(),
        ]);
        $tieLower = $this->createPost($user, [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);
        $tieHigher = $this->createPost($user, [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);

        $response = $this->actingAs($user)->get(route('feed'));
        $ids = $response->viewData('posts')->getCollection()->pluck('id')->all();

        $this->assertSame([$tieHigher->id, $tieLower->id, $older->id], $ids);
    }

    public function test_feed_uses_fifteen_item_pagination_and_page_two_contains_next_posts(): void
    {
        $user = User::factory()->create();
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        $posts = collect();

        for ($number = 1; $number <= 16; $number++) {
            $posts->push($this->createPost($user, [
                'content' => "Feed post {$number}",
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]));
        }

        $pageOne = $this->actingAs($user)->get(route('feed'));
        $pageOnePosts = $pageOne->viewData('posts');

        $this->assertCount(15, $pageOnePosts->getCollection());
        $this->assertTrue($pageOnePosts->hasMorePages());

        $pageTwo = $this->actingAs($user)->get(route('feed', ['page' => 2]));
        $pageTwoPosts = $pageTwo->viewData('posts');

        $this->assertCount(1, $pageTwoPosts->getCollection());
        $this->assertSame($posts->first()->id, $pageTwoPosts->getCollection()->first()->id);
        $this->assertFalse($pageTwoPosts->hasMorePages());
    }

    public function test_repost_remains_a_feed_item_and_displays_original_content_and_author(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, [
            'content' => 'The original feed content',
        ]);
        $repost = $this->createPost($actor, [
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($actor)->get(route('feed'));

        $response->assertOk()
            ->assertSee('The original feed content')
            ->assertSee($owner->username)
            ->assertSee($actor->username);

        $this->assertTrue(
            $response->viewData('posts')->getCollection()->contains('id', $repost->id)
        );
    }

    public function test_feed_exposes_counts_interaction_state_and_ownership_without_loading_collection_helpers(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($owner);

        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        Like::create(['post_id' => $post->id, 'user_id' => $other->id]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $other->id,
            'content' => 'A root comment',
            'parent_id' => null,
        ]);
        Comment::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'content' => 'A reply',
            'parent_id' => $comment->id,
        ]);

        $this->createPost($viewer, [
            'repost_id' => $post->id,
            'content' => null,
        ]);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $displayPost = $response->viewData('posts')->getCollection()->first(
            fn (Post $feedPost): bool => $feedPost->id === $post->id
        );

        $this->assertNotNull($displayPost);
        $this->assertSame(2, $displayPost->likes_count);
        $this->assertSame(2, $displayPost->comments_count);
        $this->assertSame(1, $displayPost->root_comments_count);
        $this->assertSame(1, $displayPost->reposts_count);
        $this->assertTrue((bool) $displayPost->liked_by_me);
        $this->assertTrue((bool) $displayPost->reposted_by_me);
        $this->assertSame($owner->id, $displayPost->user_id);
    }

    public function test_feed_uses_post_uid_and_user_username_in_public_links(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($viewer)->get(route('feed'));

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertSame('username', $owner->getRouteKeyName());
        $response->assertSee(route('posts.show', $post), false);
        $response->assertSee(route('user.profile', $owner), false);
    }

    public function test_feed_owner_sees_edit_link_for_owned_post(): void
    {
        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($owner)->get(route('feed'));

        $response->assertSee(route('posts.edit', $post), false);
    }

    public function test_feed_uses_a_bounded_comment_preview_and_keeps_the_full_root_count(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        for ($number = 1; $number <= 4; $number++) {
            $this->createComment($post, $author, "Root comment {$number}", [
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('feed'));
        $feedPost = $response->viewData('posts')->getCollection()->firstWhere('id', $post->id);

        $this->assertSame(4, $feedPost->root_comments_count);
        $this->assertCount(3, $feedPost->feedComments);
        $response->assertSee('Root comment 4')
            ->assertDontSee('Root comment 1');
    }

    public function test_feed_reply_preview_is_bounded_and_eager_loads_each_reply_user(): void
    {
        $author = User::factory()->create();
        $replyAuthors = User::factory()->count(3)->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Preview root comment');

        foreach ($replyAuthors as $index => $replyAuthor) {
            $number = $index + 1;
            $this->createComment($post, $replyAuthor, "Reply preview {$number}", [
                'parent_id' => $root->id,
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('feed'));
        $feedPost = $response->viewData('posts')->getCollection()->firstWhere('id', $post->id);
        $previewRoot = $feedPost->feedComments->first();

        $this->assertCount(2, $previewRoot->feedReplies);
        foreach ($previewRoot->feedReplies as $reply) {
            $this->assertTrue($reply->relationLoaded('user'));
        }

        $response->assertSee('Reply preview 3')
            ->assertSee($replyAuthors[2]->username)
            ->assertDontSee('Reply preview 1');
    }

    public function test_comments_and_replies_outside_feed_preview_remain_available_on_post_detail(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Post with complete comments']);
        $outsideRoot = $this->createComment($post, $author, 'Older root available on detail');
        $outsideReplyAuthor = User::factory()->create();
        $outsideReply = $this->createComment($post, $outsideReplyAuthor, 'Older reply available on detail', [
            'parent_id' => $outsideRoot->id,
        ]);

        for ($number = 1; $number <= 3; $number++) {
            $root = $this->createComment($post, $author, "Preview root {$number}", [
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);

            $this->createComment($post, $author, "Preview reply {$number}", [
                'parent_id' => $root->id,
            ]);
        }

        $feedResponse = $this->actingAs($viewer)->get(route('feed'));
        $feedResponse->assertDontSee($outsideRoot->content)
            ->assertDontSee($outsideReply->content);

        $detailResponse = $this->actingAs($viewer)->get(route('posts.show', $post));
        $detailResponse->assertOk()
            ->assertSee($outsideRoot->content)
            ->assertSee($outsideReply->content)
            ->assertSee($outsideReplyAuthor->username);
    }

    public function test_repost_feed_item_uses_bounded_original_post_comments(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, ['content' => 'Original post with preview']);

        for ($number = 1; $number <= 4; $number++) {
            $this->createComment($original, $owner, "Original comment {$number}", [
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);
        }

        $repost = $this->createPost($actor, [
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($actor)->get(route('feed'));
        $feedRepost = $response->viewData('posts')->getCollection()->firstWhere('id', $repost->id);

        $this->assertTrue($feedRepost->originalPost->relationLoaded('feedComments'));
        $this->assertFalse($feedRepost->originalPost->relationLoaded('comments'));
        $this->assertSame(4, $feedRepost->originalPost->root_comments_count);
        $this->assertCount(3, $feedRepost->originalPost->feedComments);
        $response->assertSee($original->content)
            ->assertSee('Original comment 4')
            ->assertDontSee('Original comment 1');
    }

    public function test_feed_comment_and_reply_user_queries_do_not_grow_per_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Single post root');
        $this->createComment($post, $viewer, 'Single post reply', ['parent_id' => $root->id]);

        $onePostQueryCount = $this->measureFeedQueryCount($viewer);

        for ($number = 1; $number <= 2; $number++) {
            $additionalPost = $this->createPost($author, ['content' => "Additional post {$number}"]);
            $additionalRoot = $this->createComment($additionalPost, $author, "Additional root {$number}");
            $this->createComment($additionalPost, $viewer, "Additional reply {$number}", [
                'parent_id' => $additionalRoot->id,
            ]);
        }

        $threePostQueryCount = $this->measureFeedQueryCount($viewer);

        $this->assertSame($onePostQueryCount, $threePostQueryCount);
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'A feed post',
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

    private function measureFeedQueryCount(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($viewer)->get(route('feed'));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    }
}
