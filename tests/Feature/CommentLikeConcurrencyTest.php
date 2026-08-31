<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommentLikeConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_comment_like_attaches_exactly_one_pivot(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();

        $response = $this->actingAs($actor)->post(route('comments.like', $comment));

        $response->assertRedirect();
        $this->assertDatabaseCount('comment_likes', 1);
        $this->assertDatabaseHas('comment_likes', [
            'comment_id' => $comment->id,
            'user_id' => $actor->id,
        ]);
    }

    public function test_second_sequential_comment_like_toggle_detaches_the_pivot(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();

        $this->actingAs($actor)->post(route('comments.like', $comment));
        $response = $this->actingAs($actor)->post(route('comments.like', $comment));

        $response->assertRedirect();
        $this->assertDatabaseMissing('comment_likes', [
            'comment_id' => $comment->id,
            'user_id' => $actor->id,
        ]);
    }

    public function test_comment_likes_from_different_users_remain_isolated(): void
    {
        [$comment, $actorA] = $this->createCommentAndActor();
        $actorB = User::factory()->create();

        $this->actingAs($actorA)->post(route('comments.like', $comment));
        $this->actingAs($actorB)->post(route('comments.like', $comment));
        $this->actingAs($actorA)->post(route('comments.like', $comment));

        $this->assertDatabaseMissing('comment_likes', [
            'comment_id' => $comment->id,
            'user_id' => $actorA->id,
        ]);
        $this->assertDatabaseHas('comment_likes', [
            'comment_id' => $comment->id,
            'user_id' => $actorB->id,
        ]);
    }

    public function test_database_rejects_duplicate_comment_like_pair(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unique_comment_like_conflict_does_not_produce_http_500(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();
        $injected = false;

        DB::listen(function (QueryExecuted $query) use (&$injected, $comment, $actor): void {
            $sql = strtolower(ltrim($query->sql));

            if ($injected || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'comment_likes')) {
                return;
            }

            $injected = true;
            DB::table('comment_likes')->insert([
                'comment_id' => $comment->id,
                'user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($actor)->post(route('comments.like', $comment));

        $this->assertTrue($injected);
        $response->assertRedirect();
        $this->assertDatabaseCount('comment_likes', 1);
    }

    public function test_non_related_comment_like_database_errors_are_not_swallowed(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();
        $injected = false;

        DB::listen(function (QueryExecuted $query) use (&$injected, $comment, $actor): void {
            $sql = strtolower(ltrim($query->sql));

            if ($injected || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'comment_likes')) {
                return;
            }

            $injected = true;
            DB::table('comment_likes')->insert([
                'comment_id' => $comment->id,
                'user_id' => 999999999,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->withoutExceptionHandling();
            $this->expectException(QueryException::class);
            $this->actingAs($actor)->post(route('comments.like', $comment));
        } finally {
            $injected = true;
        }

        $this->assertTrue($injected);
    }

    public function test_existing_comment_like_route_behavior_remains_redirect(): void
    {
        [$comment, $actor] = $this->createCommentAndActor();

        $response = $this->actingAs($actor)->post(route('comments.like', $comment));

        $response->assertRedirect();
    }

    /**
     * @return array{0: Comment, 1: User}
     */
    private function createCommentAndActor(): array
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Post for comment like test',
        ]);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'content' => 'Comment for like test',
        ]);

        return [$comment, $actor];
    }
}
