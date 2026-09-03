<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Hashtag;
use App\Notifications\CommentPosted;
use App\Notifications\PostLiked;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchActivityPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_search_is_paginated_and_keeps_query_string_and_route_keys(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['username' => 'bounded-author']);
        $matchingPosts = [];

        for ($index = 0; $index < 21; $index++) {
            $matchingPosts[] = $this->createPost($author, "Bounded result {$index}");
        }

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'bounded']));
        $posts = $response->viewData('posts');

        $response->assertOk();
        $this->assertInstanceOf(Paginator::class, $posts);
        $this->assertCount(20, $posts->items());
        $this->assertTrue($posts->hasMorePages());
        $this->assertSame($matchingPosts[20]->id, $posts->first()->id);
        $this->assertTrue($posts->first()->relationLoaded('user'));
        $response->assertSee(route('posts.show', $matchingPosts[20]), false);
        $response->assertSee(route('user.profile', $author), false);
        $response->assertSee('q=bounded&amp;page=2', false);
    }

    public function test_empty_search_query_renders_without_search_results(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '']));

        $response->assertOk();
        $this->assertSame('', $response->viewData('query'));
        $this->assertTrue($response->viewData('users')->isEmpty());
        $this->assertTrue($response->viewData('posts')->isEmpty());
    }

    public function test_search_trims_query_before_searching(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author, 'A trimmed search result');

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '  trimmed  ']));

        $response->assertOk();
        $this->assertSame('trimmed', $response->viewData('query'));
        $this->assertSame($post->id, $response->viewData('posts')->first()->id);
    }

    public function test_whitespace_only_search_is_treated_as_empty_without_search_queries(): void
    {
        $viewer = User::factory()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '   ']));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $searchQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, ' from "users"') || str_contains($sql, ' from "posts"');
        });

        $response->assertOk();
        $this->assertSame('', $response->viewData('query'));
        $this->assertTrue($response->viewData('users')->isEmpty());
        $this->assertTrue($response->viewData('posts')->isEmpty());
        $this->assertCount(0, $searchQueries);
    }

    public function test_zero_is_treated_as_a_valid_search_query(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author, 'A post containing 0');

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '0']));

        $response->assertOk();
        $this->assertSame('0', $response->viewData('query'));
        $this->assertSame($post->id, $response->viewData('posts')->first()->id);
    }

    public function test_search_rejects_array_input_with_validation_error(): void
    {
        $viewer = User::factory()->create();

        $response = $this->from(route('search'))
            ->actingAs($viewer)
            ->get(route('search') . '?q%5B0%5D=bounded');

        $response->assertRedirect(route('search'));
        $response->assertSessionHasErrors('q');
    }

    public function test_search_rejects_queries_longer_than_one_hundred_characters(): void
    {
        $viewer = User::factory()->create();

        $response = $this->from(route('search'))
            ->actingAs($viewer)
            ->get(route('search', ['q' => str_repeat('a', 101)]));

        $response->assertRedirect(route('search'));
        $response->assertSessionHasErrors('q');
    }

    public function test_user_search_keeps_the_five_result_limit(): void
    {
        $viewer = User::factory()->create();

        for ($index = 1; $index <= 6; $index++) {
            User::factory()->create([
                'name' => "Search Result {$index}",
                'username' => "search-result-{$index}",
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'Search Result']));

        $response->assertOk();
        $this->assertCount(5, $response->viewData('users'));
    }

    public function test_search_returns_matching_hashtags_without_hash_and_uses_the_existing_route(): void
    {
        $viewer = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'laravel']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'larav']));

        $response
            ->assertOk()
            ->assertSee('#laravel')
            ->assertSee(route('tags.show', $hashtag->name), false);
        $this->assertSame(['laravel'], $response->viewData('hashtags')->pluck('name')->all());
    }

    public function test_search_normalizes_one_leading_hash_for_hashtags(): void
    {
        $viewer = User::factory()->create();
        Hashtag::create(['name' => 'laravel']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '#LARAVEL']));

        $response->assertOk();
        $this->assertSame(['laravel'], $response->viewData('hashtags')->pluck('name')->all());
    }

    public function test_search_removes_only_one_leading_hash_for_hashtags(): void
    {
        $viewer = User::factory()->create();
        Hashtag::create(['name' => 'laravel']);
        Hashtag::create(['name' => '#laravel']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '##laravel']));

        $response->assertOk();
        $this->assertSame(['#laravel'], $response->viewData('hashtags')->pluck('name')->all());
    }

    public function test_hashtag_search_is_unique_ordered_and_limited_to_five_results(): void
    {
        $viewer = User::factory()->create();
        foreach (['tag-05', 'tag-01', 'tag-04', 'tag-02', 'tag-03', 'tag-06'] as $name) {
            Hashtag::create(['name' => $name]);
        }

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'tag-']));
        $names = $response->viewData('hashtags')->pluck('name')->all();

        $response->assertOk();
        $this->assertSame(['tag-01', 'tag-02', 'tag-03', 'tag-04', 'tag-05'], $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_hash_only_search_does_not_run_a_broad_hashtag_query(): void
    {
        $viewer = User::factory()->create();
        Hashtag::create(['name' => 'laravel']);
        Hashtag::create(['name' => 'php']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '#']));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hashtagQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains(strtolower($query['query']), ' from "hashtags"');
        });

        $response->assertOk();
        $this->assertTrue($response->viewData('hashtags')->isEmpty());
        $this->assertCount(0, $hashtagQueries);
    }

    public function test_search_returns_distinct_non_empty_matching_topics_and_uses_the_existing_route(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $this->createPost($author, 'First backend post', ['topic' => 'backend']);
        $this->createPost($author, 'Second backend post', ['topic' => 'backend']);
        $this->createPost($author, 'Null topic post', ['topic' => null]);
        $this->createPost($author, 'Empty topic post', ['topic' => '']);
        $this->createPost($author, 'Frontend post', ['topic' => 'frontend']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'back']));

        $response
            ->assertOk()
            ->assertSee('backend')
            ->assertSee(route('topik.show', ['topic' => 'backend']), false);
        $this->assertSame(['backend'], $response->viewData('topics')->all());
    }

    public function test_topic_search_uses_the_original_query_without_hashtag_normalization(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $this->createPost($author, 'Hash topic', ['topic' => '#backend']);
        $this->createPost($author, 'Plain topic', ['topic' => 'backend']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => '#backend']));

        $response->assertOk();
        $this->assertSame(['#backend'], $response->viewData('topics')->all());
    }

    public function test_topic_search_is_ordered_and_limited_to_five_distinct_values(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        foreach (['topic-05', 'topic-01', 'topic-04', 'topic-02', 'topic-03', 'topic-06'] as $topic) {
            $this->createPost($author, "Post for {$topic}", ['topic' => $topic]);
        }
        $this->createPost($author, 'Duplicate topic', ['topic' => 'topic-01']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'topic-']));
        $topics = $response->viewData('topics')->all();

        $response->assertOk();
        $this->assertSame(['topic-01', 'topic-02', 'topic-03', 'topic-04', 'topic-05'], $topics);
        $this->assertSame($topics, array_values(array_unique($topics)));
    }

    public function test_search_omits_empty_hashtag_and_topic_sections_when_there_are_no_matches(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'no-such-discovery-result']));

        $response
            ->assertOk()
            ->assertDontSee('🏷️ Tagar', false)
            ->assertDontSee('🧵 Topik', false);
        $this->assertTrue($response->viewData('hashtags')->isEmpty());
        $this->assertTrue($response->viewData('topics')->isEmpty());
    }

    public function test_activity_is_paginated_and_marks_all_unread_as_read(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($actor, 'Activity post');

        for ($index = 0; $index < 21; $index++) {
            $recipient->notify(new PostLiked($actor, $post));
        }

        $response = $this->actingAs($recipient)->get(route('activity.index'));
        $notifications = $response->viewData('notifications');

        $response->assertOk();
        $this->assertInstanceOf(Paginator::class, $notifications);
        $this->assertCount(20, $notifications->items());
        $this->assertTrue($notifications->hasMorePages());
        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_activity_orders_notifications_by_created_at_then_id(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($actor, 'Ordered activity post');
        $timestamp = Carbon::parse('2026-08-31 12:00:00');

        for ($index = 0; $index < 3; $index++) {
            $recipient->notify(new PostLiked($actor, $post));
        }

        $recipient->notifications()->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $posts = $this->actingAs($recipient)
            ->get(route('activity.index'))
            ->viewData('notifications');

        $this->assertSame(
            $recipient->notifications()->orderByDesc('id')->pluck('id')->all(),
            $posts->getCollection()->pluck('id')->all(),
        );
    }

    public function test_activity_renders_notification_actor_payload_and_preserves_read_notifications(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create(['username' => 'activity-actor']);
        $post = $this->createPost($actor, 'Actor activity post');

        $recipient->notify(new PostLiked($actor, $post));
        $readNotification = $recipient->notifications()->latest('id')->first();
        $readNotification->markAsRead();
        $recipient->notify(new PostLiked($actor, $post));

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response->assertOk();
        $response->assertSee($actor->name);
        $response->assertSee(route('user.profile', $actor), false);
        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->assertNotNull($readNotification->fresh()->read_at);
    }

    public function test_activity_links_post_like_notification_to_the_target_post_without_post_lookup(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Post target like');

        $recipient->notify(new PostLiked($actor, $post));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $postQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains(strtolower($query['query']), ' from "posts"');
        });

        $response
            ->assertOk()
            ->assertSee(route('user.profile', $actor), false)
            ->assertSee(route('posts.show', $post), false)
            ->assertSee('Lihat postingan');
        $this->assertCount(0, $postQueries);
    }

    public function test_activity_links_comment_notification_to_the_target_post(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Post target comment');

        $recipient->notify(new CommentPosted($actor, $post));

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response
            ->assertOk()
            ->assertSee(route('user.profile', $actor), false)
            ->assertSee(route('posts.show', $post), false)
            ->assertSee('Lihat postingan');
    }

    public function test_activity_follow_notification_keeps_actor_navigation_without_post_target(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();

        $recipient->notify(new \App\Notifications\UserFollowed($actor));

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response
            ->assertOk()
            ->assertSee(route('user.profile', $actor), false)
            ->assertDontSee('Lihat postingan');
    }

    public function test_activity_handles_notification_with_missing_post_key(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Legacy notification post');

        $recipient->notify(new PostLiked($actor, $post));
        $notification = $recipient->notifications()->latest('id')->first();
        $data = $notification->data;
        unset($data['post_slug']);
        $notification->update(['data' => $data]);

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response->assertOk()->assertDontSee('Lihat postingan');
    }

    public function test_activity_handles_notification_with_null_post_key(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Null notification post');

        $recipient->notify(new PostLiked($actor, $post));
        $notification = $recipient->notifications()->latest('id')->first();
        $data = $notification->data;
        $data['post_slug'] = null;
        $notification->update(['data' => $data]);

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response->assertOk()->assertDontSee('Lihat postingan');
    }

    public function test_activity_handles_notification_with_empty_post_key(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Empty notification post');

        $recipient->notify(new PostLiked($actor, $post));
        $notification = $recipient->notifications()->latest('id')->first();
        $data = $notification->data;
        $data['post_slug'] = '';
        $notification->update(['data' => $data]);

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response->assertOk()->assertDontSee('Lihat postingan');
    }

    public function test_activity_keeps_post_link_when_the_target_post_was_deleted(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($recipient, 'Deleted notification post');
        $postUid = $post->uid;

        $recipient->notify(new PostLiked($actor, $post));
        $post->delete();

        $response = $this->actingAs($recipient)->get(route('activity.index'));

        $response
            ->assertOk()
            ->assertSee(route('posts.show', $postUid), false)
            ->assertSee('Lihat postingan');
    }

    public function test_feed_badge_counts_only_unread_notifications(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->createPost($actor, 'Badge post');

        $recipient->notify(new PostLiked($actor, $post));
        $readNotification = $recipient->notifications()->latest('id')->first();
        $readNotification->markAsRead();
        $recipient->notify(new PostLiked($actor, $post));
        $recipient->notify(new PostLiked($actor, $post));

        $response = $this->actingAs($recipient)->get(route('feed'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('unreadNotificationCount'));
        $response->assertSee('bg-red-500', false);
        $response->assertSee('2', false);
    }

    private function createPost(User $user, string $content, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => $content,
        ], $attributes));
    }
}
