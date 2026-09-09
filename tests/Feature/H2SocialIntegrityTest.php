<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentPosted;
use App\Notifications\PostLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class H2SocialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repost_interaction_target_is_the_original_post(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $original = $this->createPost($owner, 'Original interaction target');
        $repost = $this->createRepost($reposter, $original);

        $this->assertTrue($original->interactionTarget()->is($original));
        $this->assertTrue($repost->interactionTarget()->is($original));
    }

    public function test_like_toggle_shares_one_state_across_original_and_repost_routes(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original liked content');
        $repost = $this->createRepost($reposter, $original);

        $this->actingAs($actor)
            ->post(route('posts.like', $repost))
            ->assertRedirect();

        $this->assertDatabaseHas('likes', [
            'user_id' => $actor->id,
            'post_id' => $original->id,
        ]);
        $this->assertDatabaseMissing('likes', [
            'user_id' => $actor->id,
            'post_id' => $repost->id,
        ]);
        Notification::assertSentTo(
            $owner,
            PostLiked::class,
            fn (PostLiked $notification): bool => $notification->post->is($original)
                && $notification->toArray($owner)['post_slug'] === $original->uid,
        );
        Notification::assertNotSentTo($reposter, PostLiked::class);

        $this->actingAs($actor)
            ->post(route('posts.like', $original))
            ->assertRedirect();

        $this->assertDatabaseMissing('likes', [
            'user_id' => $actor->id,
            'post_id' => $original->id,
        ]);
        $this->assertDatabaseCount('likes', 0);
    }

    public function test_bookmark_routes_share_one_original_post_state(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $viewer = User::factory()->create();
        $original = $this->createPost($owner, 'Original saved content');
        $repost = $this->createRepost($reposter, $original);

        $this->actingAs($viewer)
            ->post(route('bookmarks.store', $repost))
            ->assertRedirect();
        $this->actingAs($viewer)
            ->post(route('bookmarks.store', $original))
            ->assertRedirect();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $original->id,
        ]);
        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $repost->id,
        ]);
        $this->assertDatabaseCount('bookmarks', 1);

        $this->actingAs($viewer)
            ->delete(route('bookmarks.destroy', $repost))
            ->assertRedirect();

        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_comments_and_replies_through_a_repost_belong_to_the_original_post(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $commenter = User::factory()->create();
        $original = $this->createPost($owner, 'Original commented content');
        $repost = $this->createRepost($reposter, $original);

        $this->actingAs($commenter)
            ->post(route('comments.store', $repost), [
                'content' => 'Root through repost',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $root = Comment::query()
            ->where('content', 'Root through repost')
            ->firstOrFail();

        $this->actingAs($commenter)
            ->post(route('comments.store', $repost), [
                'content' => 'Reply through repost',
                'parent_id' => $root->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'post_id' => $original->id,
            'content' => 'Root through repost',
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('comments', [
            'post_id' => $original->id,
            'content' => 'Reply through repost',
            'parent_id' => $root->id,
        ]);
        $this->assertDatabaseMissing('comments', [
            'post_id' => $repost->id,
        ]);
        $this->assertCount(2, Notification::sent($owner, CommentPosted::class));
        Notification::assertSentTo(
            $owner,
            CommentPosted::class,
            fn (CommentPosted $notification): bool => $notification->post->is($original),
        );
        Notification::assertNotSentTo($reposter, CommentPosted::class);
    }

    public function test_repost_route_toggles_the_original_when_given_a_repost_uid(): void
    {
        $owner = User::factory()->create();
        $firstReposter = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original reposted content');
        $submittedRepost = $this->createRepost($firstReposter, $original);

        $this->actingAs($actor)
            ->post(route('posts.repost', $submittedRepost))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'user_id' => $actor->id,
            'repost_id' => $original->id,
            'content' => null,
        ]);
        $this->assertDatabaseMissing('posts', [
            'user_id' => $actor->id,
            'repost_id' => $submittedRepost->id,
        ]);

        $this->actingAs($actor)
            ->post(route('posts.repost', $submittedRepost))
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', [
            'user_id' => $actor->id,
            'repost_id' => $original->id,
        ]);
        $this->assertDatabaseHas('posts', ['id' => $submittedRepost->id]);
        $this->assertDatabaseHas('posts', ['id' => $original->id]);
    }

    public function test_repost_wrapper_cannot_be_edited_but_can_be_deleted_by_its_owner(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $otherUser = User::factory()->create();
        $original = $this->createPost($owner, 'Original immutable through wrapper');
        $repost = $this->createRepost($reposter, $original);

        $this->actingAs($reposter)
            ->get(route('posts.edit', $repost))
            ->assertForbidden();
        $this->actingAs($reposter)
            ->patch(route('posts.update', $repost), [
                'content' => 'Quote content must not be created',
            ])
            ->assertForbidden();

        $this->assertNull($repost->fresh()->content);

        $this->actingAs($otherUser)
            ->delete(route('posts.destroy', $repost))
            ->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $repost->id]);

        $this->actingAs($reposter)
            ->delete(route('posts.destroy', $repost))
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $repost->id]);
        $this->assertDatabaseHas('posts', ['id' => $original->id]);
    }

    private function createPost(User $user, string $content): Post
    {
        return Post::query()->create([
            'user_id' => $user->id,
            'content' => $content,
        ]);
    }

    private function createRepost(User $user, Post $original): Post
    {
        return Post::query()->create([
            'user_id' => $user->id,
            'repost_id' => $original->id,
            'content' => null,
        ]);
    }
}
