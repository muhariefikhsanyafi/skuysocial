<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Bookmark;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReactivePostActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_like_json_response_contains_authoritative_state_and_count(): void
    {
        $actor = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($actor)
            ->postJson(route('posts.like', $post), ['liked' => true])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'liked' => true,
                'like_count' => 1,
            ]);

        $this->actingAs($actor)
            ->postJson(route('posts.like', $post), ['liked' => false])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'liked' => false,
                'like_count' => 0,
            ]);
    }

    public function test_repost_json_desired_state_is_idempotent_and_returns_authoritative_count(): void
    {
        $actor = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $url = route('posts.repost', $post);

        $this->actingAs($actor)
            ->postJson($url, ['reposted' => true])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => true,
                'repost_count' => 1,
            ]);

        $this->postJson($url, ['reposted' => true])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => true,
                'repost_count' => 1,
            ]);
        $this->assertDatabaseCount('posts', 2);

        $this->postJson($url, ['reposted' => false])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => false,
                'repost_count' => 0,
            ]);

        $this->postJson($url, ['reposted' => false])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => false,
                'repost_count' => 0,
            ]);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_repost_json_rejects_invalid_desired_state_without_mutation(): void
    {
        $actor = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($actor)
            ->postJson(route('posts.repost', $post), ['reposted' => 'not-a-boolean'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_repost_json_without_a_desired_state_preserves_legacy_toggle_behavior(): void
    {
        $actor = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $url = route('posts.repost', $post);

        $this->actingAs($actor)
            ->postJson($url, [])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => true,
                'repost_count' => 1,
            ]);

        $this->postJson($url, [])
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'reposted' => false,
                'repost_count' => 0,
            ]);
    }

    public function test_bookmark_json_responses_report_authoritative_state(): void
    {
        $actor = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $saveResponse = $this->actingAs($actor)
            ->postJson(route('bookmarks.store', $post));
        $saveResponse
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'bookmarked' => true,
            ]);
        $this->assertFalse($saveResponse->headers->has('Location'));

        $this->postJson(route('bookmarks.store', $post))
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'bookmarked' => true,
            ]);
        $this->assertDatabaseCount('bookmarks', 1);

        $unsaveResponse = $this->deleteJson(route('bookmarks.destroy', $post));
        $unsaveResponse
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'bookmarked' => false,
            ]);
        $this->assertFalse($unsaveResponse->headers->has('Location'));

        $this->deleteJson(route('bookmarks.destroy', $post))
            ->assertOk()
            ->assertExactJson([
                'post_uid' => $post->uid,
                'bookmarked' => false,
            ]);
        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_restricted_user_remains_blocked_from_reactive_post_actions(): void
    {
        $actor = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $post = $this->createPost(User::factory()->create());
        Bookmark::query()->create([
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);
        Like::query()->create([
            'user_id' => $actor->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($actor)
            ->postJson(route('posts.like', $post), ['liked' => false])
            ->assertForbidden();
        $this->postJson(route('posts.repost', $post), ['reposted' => true])
            ->assertForbidden();
        $this->postJson(route('bookmarks.store', $post))
            ->assertForbidden();
        $this->deleteJson(route('bookmarks.destroy', $post))
            ->assertForbidden();

        $this->assertDatabaseHas('likes', ['user_id' => $actor->id, 'post_id' => $post->id]);
        $this->assertDatabaseHas('bookmarks', ['user_id' => $actor->id, 'post_id' => $post->id]);
        $this->assertDatabaseMissing('posts', ['user_id' => $actor->id, 'repost_id' => $post->id]);
    }

    private function createPost(User $user): Post
    {
        return Post::query()->create([
            'user_id' => $user->id,
            'content' => 'Reactive post action target.',
        ]);
    }
}
