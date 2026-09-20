<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LikeConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_like_creates_exactly_one_row(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($actor)->post(route('posts.like', $post));

        $response->assertRedirect();
        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseHas('likes', [
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_json_like_returns_the_confirmed_state_count_and_post_uid(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($actor)->postJson(route('posts.like', $post), [
            'liked' => true,
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'liked' => true,
                'like_count' => 1,
            ]);
    }

    public function test_json_unlike_returns_the_confirmed_state_and_count(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        Like::create([
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);

        $response = $this->actingAs($actor)->postJson(route('posts.like', $post), [
            'liked' => false,
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'liked' => false,
                'like_count' => 0,
            ]);
    }

    public function test_repeated_json_like_intent_keeps_one_like_and_one_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($actor)->postJson(route('posts.like', $post), [
            'liked' => true,
        ]);
        $this->actingAs($actor)->postJson(route('posts.like', $post), [
            'liked' => true,
        ]);

        $this->assertDatabaseCount('likes', 1);
        Notification::assertSentTo($owner, PostLiked::class);
        $this->assertCount(1, Notification::sent($owner, PostLiked::class));
    }

    public function test_second_sequential_toggle_removes_the_like(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($actor)->post(route('posts.like', $post));
        $response = $this->actingAs($actor)->post(route('posts.like', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('likes', [
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_likes_from_different_users_remain_isolated(): void
    {
        $owner = User::factory()->create();
        $actorA = User::factory()->create();
        $actorB = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($actorA)->post(route('posts.like', $post));
        $this->actingAs($actorB)->post(route('posts.like', $post));
        $this->actingAs($actorA)->post(route('posts.like', $post));

        $this->assertDatabaseMissing('likes', [
            'user_id' => $actorA->id,
            'post_id' => $post->id,
        ]);
        $this->assertDatabaseHas('likes', [
            'user_id' => $actorB->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_database_rejects_duplicate_user_post_like_pair(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        Like::create([
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('likes')->insert([
            'user_id' => $actor->id,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_post_owner_receives_exactly_one_notification_after_first_like(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($actor)->post(route('posts.like', $post));

        Notification::assertSentTo($owner, PostLiked::class);
        $this->assertCount(1, Notification::sent($owner, PostLiked::class));
    }

    public function test_self_like_does_not_send_post_liked_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($owner)->post(route('posts.like', $post));

        Notification::assertNothingSent();
    }

    public function test_post_like_route_continues_to_use_post_uid(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $route = route('posts.like', $post);

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertStringContainsString('/posts/'.$post->uid.'/like', $route);
        $this->assertStringNotContainsString('/posts/'.$post->id.'/like', $route);
        $this->actingAs($actor)->post($route)->assertRedirect();
    }

    public function test_unique_like_conflict_is_treated_as_success_without_extra_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);
        $active = true;
        $matched = false;

        DB::listen(function (QueryExecuted $query) use (&$active, &$matched, $actor, $post): void {
            $sql = strtolower(ltrim($query->sql));

            if (! $active || $matched || ! str_starts_with($sql, 'select ') || ! str_contains($sql, 'likes')) {
                return;
            }

            $matched = true;
            DB::table('likes')->insert([
                'user_id' => $actor->id,
                'post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $response = $this->actingAs($actor)->post(route('posts.like', $post));
        } finally {
            $active = false;
        }

        $this->assertTrue($matched);
        $response->assertRedirect();
        $this->assertDatabaseCount('likes', 1);
        Notification::assertNothingSent();
    }

    public function test_non_related_like_database_errors_are_not_swallowed(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);
        $mutated = false;

        Like::creating(function (Like $like) use (&$mutated): void {
            if ($mutated) {
                return;
            }

            $mutated = true;
            $like->post_id = 999999999;
        });

        try {
            $this->withoutExceptionHandling();
            $this->expectException(QueryException::class);
            $this->actingAs($actor)->post(route('posts.like', $post));
        } finally {
            Like::flushEventListeners();
        }

        $this->assertTrue($mutated);
    }

    private function createPost(User $owner): Post
    {
        return Post::create([
            'user_id' => $owner->id,
            'content' => 'Post for like test',
        ]);
    }
}
