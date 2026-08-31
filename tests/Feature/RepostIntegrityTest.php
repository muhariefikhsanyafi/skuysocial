<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepostIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repost_first_action_creates_one_repost(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $response = $this->actingAs($actor)->post(route('posts.repost', $original));

        $response->assertRedirect();
        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseHas('posts', [
            'user_id' => $actor->id,
            'repost_id' => $original->id,
            'content' => null,
        ]);
    }

    public function test_database_rejects_duplicate_repost_for_same_user_and_post(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $this->actingAs($actor)->post(route('posts.repost', $original));

        $this->expectException(QueryException::class);

        DB::table('posts')->insert([
            'user_id' => $actor->id,
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_undo_repost_deletes_only_the_current_users_repost(): void
    {
        $owner = User::factory()->create();
        $actorA = User::factory()->create();
        $actorB = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $this->actingAs($actorA)->post(route('posts.repost', $original));
        $this->actingAs($actorB)->post(route('posts.repost', $original));
        $this->actingAs($actorA)->post(route('posts.repost', $original));

        $this->assertDatabaseMissing('posts', [
            'user_id' => $actorA->id,
            'repost_id' => $original->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'user_id' => $actorB->id,
            'repost_id' => $original->id,
        ]);
        $this->assertDatabaseHas('posts', ['id' => $original->id]);
    }

    public function test_repost_can_be_created_again_after_undo(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $this->actingAs($actor)->post(route('posts.repost', $original));
        $this->actingAs($actor)->post(route('posts.repost', $original));
        $this->actingAs($actor)->post(route('posts.repost', $original));

        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseHas('posts', [
            'user_id' => $actor->id,
            'repost_id' => $original->id,
        ]);
    }

    public function test_multiple_original_posts_with_null_repost_id_remain_valid(): void
    {
        $author = User::factory()->create();

        $first = $this->createPost($author, 'First original');
        $second = $this->createPost($author, 'Second original');

        $this->assertNull($first->repost_id);
        $this->assertNull($second->repost_id);
        $this->assertDatabaseCount('posts', 2);
    }

    public function test_repost_route_uses_post_uid(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $route = route('posts.repost', $original);

        $this->assertSame('uid', $original->getRouteKeyName());
        $this->assertStringContainsString('/post/'.$original->uid.'/repost', $route);
        $this->assertStringNotContainsString('/post/'.$original->id.'/repost', $route);

        $this->actingAs($actor)->post($route)->assertRedirect();
    }

    public function test_repost_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $original = $this->createPost($owner, 'Original post');

        $response = $this->post(route('posts.repost', $original));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_missing_post_uid_returns_not_found(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->post('/post/missing-post-uid/repost');

        $response->assertNotFound();
    }

    private function createPost(User $user, ?string $content): Post
    {
        return Post::create([
            'user_id' => $user->id,
            'content' => $content,
        ]);
    }
}
