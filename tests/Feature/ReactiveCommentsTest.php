<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentPosted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReactiveCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reactive_top_level_comment_returns_public_authoritative_state(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($actor)->postJson(route('comments.store', $post), [
            'content' => '<script>new comment</script>',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'post_uid',
                'comment_count',
                'root_comment_count',
                'parent_reply_count',
                'comment' => [
                    'id',
                    'post_uid',
                    'parent_id',
                    'content',
                    'datetime',
                    'display_time',
                    'author' => ['id', 'name', 'username', 'avatar_url', 'is_verified'],
                    'like_count',
                    'liked',
                    'replies_count',
                    'replies',
                    'actions' => ['like_url', 'reply_url', 'share_url'],
                    'reply_expansion_url',
                ],
            ])
            ->assertJsonPath('post_uid', $post->uid)
            ->assertJsonPath('comment.post_uid', $post->uid)
            ->assertJsonPath('comment.parent_id', null)
            ->assertJsonPath('comment.content', '<script>new comment</script>')
            ->assertJsonPath('comment.author.username', $actor->username)
            ->assertJsonPath('comment.like_count', 0)
            ->assertJsonPath('comment.liked', false)
            ->assertJsonPath('comment.replies_count', 0)
            ->assertJsonPath('comment_count', 1)
            ->assertJsonPath('root_comment_count', 1)
            ->assertJsonPath('parent_reply_count', null);

        $this->assertArrayNotHasKey('feed_html', $response->json());
        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $actor->id,
            'parent_id' => null,
            'content' => '<script>new comment</script>',
        ]);
        Notification::assertSentTo($owner, CommentPosted::class);
    }

    public function test_feed_surface_gets_only_a_safe_rendered_comment_fragment(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Skuy-Comment-Surface' => 'feed'])
            ->postJson(route('comments.store', $post), [
                'content' => "<script>alert('x')</script>",
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['feed_html'])
            ->assertJsonPath('comment.post_uid', $post->uid)
            ->assertJsonPath('comment.parent_id', null);

        $html = $response->json('feed_html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('feedCommentComposer({', $html);
    }

    public function test_reactive_reply_returns_the_same_post_and_parent_relationship(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);
        $root = $this->createComment($post, $owner, 'Root comment');

        $response = $this->actingAs($actor)->postJson(route('comments.store', $post), [
            'content' => 'A new reply',
            'parent_id' => $root->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('post_uid', $post->uid)
            ->assertJsonPath('comment.post_uid', $post->uid)
            ->assertJsonPath('comment.parent_id', $root->id)
            ->assertJsonPath('comment.content', 'A new reply')
            ->assertJsonPath('comment_count', 2)
            ->assertJsonPath('root_comment_count', 1)
            ->assertJsonPath('parent_reply_count', 1);

        $this->assertArrayNotHasKey('feed_html', $response->json());
        $this->assertDatabaseHas('comments', [
            'id' => $response->json('comment.id'),
            'post_id' => $post->id,
            'parent_id' => $root->id,
            'content' => 'A new reply',
        ]);
        Notification::assertSentTo($owner, CommentPosted::class);
    }

    public function test_reactive_reply_rejects_cross_post_and_nested_parents(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);
        $otherPost = $this->createPost($owner);
        $foreignParent = $this->createComment($otherPost, $owner, 'Foreign parent');
        $root = $this->createComment($post, $owner, 'Valid root');
        $nestedParent = $this->createComment($post, $owner, 'Nested parent', ['parent_id' => $root->id]);

        foreach ([$foreignParent, $nestedParent] as $parent) {
            $this->actingAs($actor)
                ->postJson(route('comments.store', $post), [
                    'content' => 'Invalid reply',
                    'parent_id' => $parent->id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('parent_id');
        }

        $this->assertDatabaseMissing('comments', ['content' => 'Invalid reply']);
    }

    public function test_reactive_comment_like_desired_state_is_authoritative_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);
        $comment = $this->createComment($post, $owner, 'Like target');

        $liked = $this->actingAs($actor)
            ->postJson(route('comments.like', $comment), ['liked' => true]);
        $liked->assertOk()->assertExactJson([
            'comment_id' => $comment->id,
            'liked' => true,
            'like_count' => 1,
        ]);

        $this->postJson(route('comments.like', $comment), ['liked' => true])
            ->assertOk()
            ->assertExactJson([
                'comment_id' => $comment->id,
                'liked' => true,
                'like_count' => 1,
            ]);
        $this->assertDatabaseCount('comment_likes', 1);

        $this->postJson(route('comments.like', $comment), ['liked' => false])
            ->assertOk()
            ->assertExactJson([
                'comment_id' => $comment->id,
                'liked' => false,
                'like_count' => 0,
            ]);
        $this->postJson(route('comments.like', $comment), ['liked' => false])
            ->assertOk()
            ->assertExactJson([
                'comment_id' => $comment->id,
                'liked' => false,
                'like_count' => 0,
            ]);
        $this->assertDatabaseCount('comment_likes', 0);
    }

    public function test_concurrent_json_comment_like_conflict_returns_authoritative_state(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $comment = $this->createComment($this->createPost($owner), $owner, 'Concurrent like target');
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

        $response = $this->actingAs($actor)
            ->postJson(route('comments.like', $comment), ['liked' => true]);

        $this->assertTrue($injected);
        $response->assertOk()->assertExactJson([
            'comment_id' => $comment->id,
            'liked' => true,
            'like_count' => 1,
        ]);
        $this->assertDatabaseCount('comment_likes', 1);
    }

    public function test_invalid_reactive_comment_like_state_is_rejected(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $comment = $this->createComment($this->createPost($owner), $owner, 'Like target');

        $this->actingAs($actor)
            ->postJson(route('comments.like', $comment), ['liked' => 'not-a-boolean'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('liked');

        $this->assertDatabaseCount('comment_likes', 0);
    }

    public function test_restricted_user_cannot_use_reactive_comment_writes(): void
    {
        $restricted = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $owner = User::factory()->create();
        $post = $this->createPost($owner);
        $root = $this->createComment($post, $owner, 'Existing root');

        $this->actingAs($restricted)
            ->postJson(route('comments.store', $post), ['content' => 'Blocked root'])
            ->assertForbidden()
            ->assertJsonPath('code', 'moderation_state_forbids_social_write');
        $this->postJson(route('comments.store', $post), [
            'content' => 'Blocked reply',
            'parent_id' => $root->id,
        ])->assertForbidden();
        $this->postJson(route('comments.like', $root), ['liked' => true])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseCount('comment_likes', 0);
    }

    public function test_traditional_comment_creation_keeps_redirect_fallback(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($owner);

        $this->from('/feed')
            ->actingAs($actor)
            ->post(route('comments.store', $post), ['content' => 'Traditional comment'])
            ->assertRedirect('/feed');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $actor->id,
            'content' => 'Traditional comment',
            'parent_id' => null,
        ]);
        Notification::assertSentTo($owner, CommentPosted::class);
    }

    private function createPost(User $owner): Post
    {
        return Post::create([
            'user_id' => $owner->id,
            'content' => 'Post for reactive comment test',
        ]);
    }

    private function createComment(Post $post, User $owner, string $content, array $attributes = []): Comment
    {
        return Comment::query()->create(array_merge([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'content' => $content,
        ], $attributes));
    }
}
