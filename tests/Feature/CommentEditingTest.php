<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_root_comment_via_json_without_changing_relationships(): void
    {
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'content' => 'Post']);
        $comment = Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'content' => 'Before']);

        $response = $this->actingAs($author)
            ->patchJson(route('comments.update', $comment), ['content' => '<script>edited</script>']);

        $response->assertOk()
            ->assertJsonPath('comment_id', $comment->id)
            ->assertJsonPath('content', '<script>edited</script>')
            ->assertJsonStructure(['updated_at']);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'user_id' => $author->id,
            'post_id' => $post->id,
            'parent_id' => null,
            'content' => '<script>edited</script>',
        ]);
    }

    public function test_author_can_edit_reply_with_browser_redirect_fallback(): void
    {
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'content' => 'Post']);
        $parent = Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'content' => 'Root']);
        $reply = Comment::create(['user_id' => $author->id, 'post_id' => $post->id,
            'parent_id' => $parent->id, 'content' => 'Old reply']);

        $this->actingAs($author)->from('/feed')
            ->patch(route('comments.update', $reply), ['content' => 'New reply'])
            ->assertRedirect('/feed');

        $this->assertDatabaseHas('comments', [
            'id' => $reply->id, 'parent_id' => $parent->id,
            'post_id' => $post->id, 'content' => 'New reply',
        ]);
    }

    public function test_other_users_cannot_edit_comments_even_when_post_owner(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'Post']);
        $comment = Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'content' => 'Original']);

        $this->actingAs($owner)
            ->patchJson(route('comments.update', $comment), ['content' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Original', $comment->fresh()->content);
    }

    public function test_invalid_content_is_rejected_without_mutating_comment(): void
    {
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'content' => 'Post']);
        $comment = Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'content' => 'Original']);

        foreach (['', str_repeat('x', 501)] as $invalid) {
            $this->actingAs($author)
                ->patchJson(route('comments.update', $comment), ['content' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('content');
        }

        $this->assertSame('Original', $comment->fresh()->content);
    }

    public function test_guest_and_restricted_accounts_cannot_edit_comments(): void
    {
        $author = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $post = Post::create(['user_id' => $author->id, 'content' => 'Post']);
        $comment = Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'content' => 'Original']);

        $this->patchJson(route('comments.update', $comment), ['content' => 'Guest edit'])->assertUnauthorized();
        $this->actingAs($author)
            ->patchJson(route('comments.update', $comment), ['content' => 'Restricted edit'])
            ->assertForbidden();
        $this->assertSame('Original', $comment->fresh()->content);
    }

    public function test_edit_control_escapes_untrusted_content_and_is_author_only(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'content' => 'Post']);
        $comment = Comment::create(['user_id' => $author->id, 'post_id' => $post->id,
            'content' => '<script>alert(1)</script>']);

        $this->be($author);
        $html = view('partials.comment-edit-control', ['comment' => $comment])->render();
        $this->assertStringContainsString('name="content"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->be($other);
        $otherHtml = view('partials.comment-edit-control', ['comment' => $comment])->render();
        $this->assertStringNotContainsString('name="content"', $otherHtml);
    }
}
