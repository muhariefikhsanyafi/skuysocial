<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use App\Notifications\UserFollowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchActivityNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_search_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('search'));

        $response->assertOk();
    }

    public function test_search_returns_matching_user_and_post(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create([
            'name' => 'Searchable Person',
            'username' => 'searchable_user',
        ]);
        $post = Post::create([
            'user_id' => $author->id,
            'content' => 'A searchable post',
        ]);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'searchable']));

        $response
            ->assertOk()
            ->assertSee('Searchable Person')
            ->assertSee('@searchable_user')
            ->assertSee($post->content);
    }

    public function test_authenticated_user_can_view_activity_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('activity.index'));

        $response->assertOk();
    }

    public function test_like_sends_notification_to_post_owner_but_not_actor(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Post yang disukai',
        ]);

        $this->actingAs($actor)->post(route('posts.like', $post));

        Notification::assertSentTo($owner, PostLiked::class);

        Notification::fake();

        $this->actingAs($owner)->post(route('posts.like', $post));

        Notification::assertNothingSent();
    }

    public function test_follow_sends_notification_to_target_but_not_actor(): void
    {
        Notification::fake();

        $target = User::factory()->create();
        $actor = User::factory()->create();

        $this->actingAs($actor)->post(route('user.follow', $target));

        Notification::assertSentTo($target, UserFollowed::class);

        Notification::fake();

        $this->actingAs($target)->post(route('user.follow', $target));

        Notification::assertNothingSent();
    }

    public function test_notifications_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ]));
    }
}
