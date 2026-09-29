<?php

namespace Tests\Feature;

use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class H1SocialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_root_comments_can_be_used_as_reply_parents(): void
    {
        $author = User::factory()->create();
        $post = Post::create([
            'user_id' => $author->id,
            'content' => 'Post with a reply',
        ]);
        $root = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'Root comment',
        ]);
        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'First-level reply',
            'parent_id' => $root->id,
        ]);

        $response = $this->actingAs($author)->post(route('comments.store', $post), [
            'content' => 'Hidden third-level reply',
            'parent_id' => $reply->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('comments', [
            'content' => 'Hidden third-level reply',
        ]);
    }

    public function test_reposting_a_repost_targets_the_original_post(): void
    {
        $owner = User::factory()->create();
        $firstReposter = User::factory()->create();
        $actor = User::factory()->create();
        $original = Post::create([
            'user_id' => $owner->id,
            'content' => 'Original post',
        ]);
        $repost = Post::create([
            'user_id' => $firstReposter->id,
            'content' => null,
            'repost_id' => $original->id,
        ]);

        $this->actingAs($actor)
            ->post(route('posts.repost', $repost))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'user_id' => $actor->id,
            'repost_id' => $original->id,
            'content' => null,
        ]);
        $this->assertDatabaseMissing('posts', [
            'user_id' => $actor->id,
            'repost_id' => $repost->id,
        ]);
    }

    public function test_like_duplicate_error_is_classified_after_transaction_rolls_back(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Post for a simulated duplicate race',
        ]);
        $active = true;
        $injected = false;

        DB::listen(function (QueryExecuted $query) use (&$active, &$injected): void {
            if (! $active || ! str_contains(strtolower($query->sql), 'insert into "likes"')) {
                return;
            }

            $active = false;
            $injected = true;

            throw $this->postgresUniqueViolation('likes_user_id_post_id_unique');
        });

        try {
            $response = $this->actingAs($actor)->post(route('posts.like', $post));
        } finally {
            $active = false;
        }

        $this->assertTrue($injected);
        $response->assertRedirect();
        $this->assertDatabaseMissing('likes', [
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_follow_duplicate_error_is_classified_after_transaction_rolls_back(): void
    {
        $target = User::factory()->create();
        $actor = User::factory()->create();
        $active = true;
        $injected = false;

        DB::listen(function (QueryExecuted $query) use (&$active, &$injected): void {
            if (! $active || ! str_contains(strtolower($query->sql), 'insert into "follows"')) {
                return;
            }

            $active = false;
            $injected = true;

            throw $this->postgresUniqueViolation('follows_follower_id_following_id_unique');
        });

        try {
            $response = $this->actingAs($actor)->post(route('user.follow', $target));
        } finally {
            $active = false;
        }

        $this->assertTrue($injected);
        $response->assertRedirect();
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_like_is_rolled_back_when_its_notification_cannot_be_written(): void
    {
        [, $actor, $post] = $this->notificationActorsAndPost();
        $this->failNotificationWrites();

        $this->actingAs($actor)
            ->post(route('posts.like', $post))
            ->assertServerError();

        $this->assertDatabaseMissing('likes', [
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_follow_is_rolled_back_when_its_notification_cannot_be_written(): void
    {
        $target = User::factory()->create();
        $actor = User::factory()->create();
        $this->failNotificationWrites();

        $this->actingAs($actor)
            ->post(route('user.follow', $target))
            ->assertServerError();

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_comment_is_rolled_back_when_its_notification_cannot_be_written(): void
    {
        [, $actor, $post] = $this->notificationActorsAndPost();
        $this->failNotificationWrites();
        $this->app['cache']->clear();

        $response = $this->actingAs($actor)->post(route('comments.store', $post), ['content' => 'Atomic comment']);
        $response->assertServerError();

        $this->assertDatabaseMissing('comments', [
            'post_id' => $post->id,
            'user_id' => $actor->id,
            'content' => 'Atomic comment',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_postgresql_unique_violation_sqlstate_is_recognized_only_for_expected_constraints(): void
    {
        $classifiers = [
            [new LikeController(), 'isDuplicateLikeConflict', 'likes_user_id_post_id_unique'],
            [new CommentController(), 'isDuplicateCommentLikeConflict', 'comment_likes_comment_id_user_id_unique'],
            [new UserController(), 'isDuplicateFollowConflict', 'follows_follower_id_following_id_unique'],
            [new PostController(), 'isDuplicateRepostConflict', 'posts_user_id_repost_id_unique'],
        ];

        foreach ($classifiers as [$controller, $methodName, $constraint]) {
            $method = new ReflectionMethod($controller, $methodName);

            $this->assertTrue($method->invoke(
                $controller,
                $this->postgresUniqueViolation($constraint),
            ));
            $this->assertFalse($method->invoke(
                $controller,
                $this->postgresUniqueViolation('unrelated_unique_constraint'),
            ));
        }
    }

    private function postgresUniqueViolation(string $constraint): QueryException
    {
        $message = 'SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "'
            .$constraint.'"';
        $previous = new PDOException($message);
        $previous->errorInfo = ['23505', 7, $message];

        return new QueryException('pgsql', 'insert into test values (?)', [1], $previous);
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function notificationActorsAndPost(): array
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Post requiring an atomic notification',
        ]);

        return [$owner, $actor, $post];
    }

    private function failNotificationWrites(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_notification_write
            BEFORE INSERT ON notifications
            BEGIN
                SELECT RAISE(ABORT, 'forced notification failure');
            END
            SQL);
    }
}
