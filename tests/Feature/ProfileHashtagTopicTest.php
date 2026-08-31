<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileHashtagTopicTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_profile_renders_paginated_posts(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, 'Profile post');

        $response = $this->actingAs($user)->get(route('user.profile', $user));

        $response->assertOk();
        $response->assertViewIs('profile');
        $response->assertViewHas('user', $user);
        $this->assertInstanceOf(Paginator::class, $response->viewData('posts'));
        $this->assertSame($post->uid, $response->viewData('posts')->first()->uid);
    }

    public function test_other_profile_uses_username_and_post_uid_route_keys(): void
    {
        $owner = User::factory()->create(['username' => 'profile-owner']);
        $viewer = User::factory()->create();
        $post = $this->createPost($owner, 'Other profile post');

        $response = $this->actingAs($viewer)->get(route('user.profile', $owner));

        $response->assertOk();
        $response->assertSee(route('user.profile', $owner), false);
        $response->assertSee(route('posts.show', $post), false);
        $this->assertSame('profile-owner', $owner->getRouteKey());
        $this->assertSame($post->uid, $post->getRouteKey());
    }

    public function test_profile_orders_posts_by_created_at_then_id_and_paginates(): void
    {
        $user = User::factory()->create();
        $timestamp = Carbon::parse('2026-08-31 10:00:00');

        $older = $this->createPost($user, 'Older');
        $older->update(['created_at' => $timestamp->copy()->subMinute()]);
        $firstTie = $this->createPost($user, 'First tie');
        $firstTie->update(['created_at' => $timestamp]);
        $secondTie = $this->createPost($user, 'Second tie');
        $secondTie->update(['created_at' => $timestamp]);

        $posts = $this->actingAs($user)
            ->get(route('user.profile', $user))
            ->viewData('posts');

        $this->assertSame([
            $secondTie->id,
            $firstTie->id,
            $older->id,
        ], $posts->getCollection()->pluck('id')->all());

        for ($index = 0; $index < 15; $index++) {
            $this->createPost($user, "Paged post {$index}");
        }

        $firstPage = $this->actingAs($user)
            ->get(route('user.profile', $user))
            ->viewData('posts');
        $secondPage = $this->actingAs($user)
            ->get(route('user.profile', $user).'?page=2')
            ->viewData('posts');

        $this->assertCount(15, $firstPage->items());
        $this->assertCount(3, $secondPage->items());
        $this->assertTrue($firstPage->hasMorePages());
    }

    public function test_hashtag_listing_paginates_and_preserves_total_count(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'laravel']);

        for ($index = 0; $index < 16; $index++) {
            $post = $this->createPost($user, "Hashtag post {$index}");
            $post->hashtags()->attach($hashtag->id);
        }

        $firstPage = $this->actingAs($user)
            ->get(route('tags.show', $hashtag->name))
            ->viewData('posts');
        $secondPage = $this->actingAs($user)
            ->get(route('tags.show', $hashtag->name).'?page=2')
            ->viewData('posts');

        $this->assertInstanceOf(LengthAwarePaginator::class, $firstPage);
        $this->assertSame(16, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertCount(1, $secondPage->items());
    }

    public function test_topic_listing_paginates_and_orders_by_created_at_then_id(): void
    {
        $user = User::factory()->create();
        $timestamp = Carbon::parse('2026-08-31 11:00:00');
        $older = $this->createPost($user, 'Older topic post', 'php');
        $older->update(['created_at' => $timestamp->copy()->subMinute()]);
        $firstTie = $this->createPost($user, 'First topic tie', 'php');
        $firstTie->update(['created_at' => $timestamp]);
        $secondTie = $this->createPost($user, 'Second topic tie', 'php');
        $secondTie->update(['created_at' => $timestamp]);

        $orderedPosts = $this->actingAs($user)
            ->get(route('topik.show', 'php'))
            ->viewData('posts');

        $this->assertSame([
            $secondTie->id,
            $firstTie->id,
            $older->id,
        ], $orderedPosts->getCollection()->pluck('id')->all());

        for ($index = 0; $index < 15; $index++) {
            $this->createPost($user, "Topic post {$index}", 'php');
        }

        $posts = $this->actingAs($user)
            ->get(route('topik.show', 'php'))
            ->viewData('posts');

        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertSame(18, $posts->total());
        $this->assertCount(15, $posts->items());
        $this->assertTrue($posts->hasMorePages());
    }

    public function test_profile_repost_renders_original_post_and_author(): void
    {
        $originalAuthor = User::factory()->create(['username' => 'original-author']);
        $reposter = User::factory()->create(['username' => 'reposter']);
        $original = $this->createPost($originalAuthor, 'Original content');
        $repost = $this->createPost($reposter, null, null, $original->id);

        $response = $this->actingAs($reposter)->get(route('user.profile', $reposter));

        $response->assertOk();
        $response->assertSee('Original content');
        $response->assertSee('original-author');
        $posts = $response->viewData('posts');
        $this->assertTrue($posts->first()->relationLoaded('originalPost'));
        $this->assertTrue($posts->first()->originalPost->relationLoaded('user'));
        $this->assertSame($repost->id, $posts->first()->id);
    }

    public function test_hashtag_listing_uses_scalar_counts_state_and_eager_loaded_comment_users(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'optimasi']);
        $post = $this->createPost($owner, 'Hashtag interaction post');
        $post->hashtags()->attach($hashtag->id);
        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        Like::create(['post_id' => $post->id, 'user_id' => $other->id]);
        $comment = Comment::create([
            'user_id' => $other->id,
            'post_id' => $post->id,
            'content' => 'Root comment',
        ]);
        Comment::create([
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'parent_id' => $comment->id,
            'content' => 'Reply comment',
        ]);

        $response = $this->actingAs($viewer)->get(route('tags.show', $hashtag->name));
        $listedPost = $response->viewData('posts')->first();

        $response->assertOk();
        $this->assertSame(2, $listedPost->likes_count);
        $this->assertSame(2, $listedPost->comments_count);
        $this->assertSame(1, $listedPost->root_comments_count);
        $this->assertTrue($listedPost->liked_by_me);
        $this->assertFalse($listedPost->reposted_by_me);
        $this->assertTrue($listedPost->relationLoaded('comments'));
        $this->assertTrue($listedPost->comments->first()->relationLoaded('user'));
        $this->assertTrue($listedPost->comments->first()->replies->first()->relationLoaded('user'));
    }

    public function test_topic_listing_preserves_interaction_state_and_route_keys(): void
    {
        $owner = User::factory()->create(['username' => 'topic-owner']);
        $viewer = User::factory()->create();
        $post = $this->createPost($owner, 'Topic interaction post', 'backend');
        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        $comment = Comment::create([
            'user_id' => $owner->id,
            'post_id' => $post->id,
            'content' => 'Topic comment',
        ]);
        Comment::create([
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'parent_id' => $comment->id,
            'content' => 'Topic reply',
        ]);

        $response = $this->actingAs($viewer)->get(route('topik.show', 'backend'));
        $listedPost = $response->viewData('posts')->first();

        $response->assertOk();
        $response->assertSee(route('posts.like', $post), false);
        $response->assertSee(route('user.profile', $owner), false);
        $this->assertSame(1, $listedPost->likes_count);
        $this->assertSame(2, $listedPost->comments_count);
        $this->assertTrue($listedPost->liked_by_me);
        $this->assertFalse($listedPost->reposted_by_me);
        $this->assertTrue($listedPost->comments->first()->replies->first()->relationLoaded('user'));
    }

    public function test_hashtag_reply_user_queries_do_not_grow_per_post(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'nplusone']);

        $firstPost = $this->createPost($owner, 'First N+1 post');
        $firstPost->hashtags()->attach($hashtag->id);
        $this->createCommentWithReply($firstPost, $owner, $viewer);

        $onePostQueries = $this->measureHashtagQueryCount($hashtag, $viewer);

        for ($index = 0; $index < 2; $index++) {
            $post = $this->createPost($owner, "Additional N+1 post {$index}");
            $post->hashtags()->attach($hashtag->id);
            $this->createCommentWithReply($post, $owner, $viewer);
        }

        $threePostQueries = $this->measureHashtagQueryCount($hashtag, $viewer);

        $this->assertSame($onePostQueries, $threePostQueries);
    }

    private function createPost(
        User $user,
        ?string $content,
        ?string $topic = null,
        ?int $repostId = null
    ): Post {
        return Post::create([
            'user_id' => $user->id,
            'content' => $content,
            'topic' => $topic,
            'repost_id' => $repostId,
        ]);
    }

    private function createCommentWithReply(Post $post, User $owner, User $viewer): void
    {
        $comment = Comment::create([
            'user_id' => $owner->id,
            'post_id' => $post->id,
            'content' => 'Root comment',
        ]);
        Comment::create([
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'parent_id' => $comment->id,
            'content' => 'Reply comment',
        ]);
    }

    private function measureHashtagQueryCount(Hashtag $hashtag, User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($viewer)->get(route('tags.show', $hashtag->name));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    }
}
