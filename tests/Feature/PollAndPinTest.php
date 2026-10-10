<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollAndPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_poll_with_a_post(): void
    {
        $author = User::factory()->create();

        $response = $this->actingAs($author)->post(route('posts.store'), [
            'content' => 'Coffee or home office?',
            'poll_question' => 'Where do you work best?',
            'poll_options' => ['Cafe', 'Home'],
        ]);

        $response->assertRedirect(route('feed'));
        $post = Post::query()->where('content', 'Coffee or home office?')->firstOrFail();
        $this->assertDatabaseHas('post_polls', ['post_id' => $post->id, 'question' => 'Where do you work best?']);
        $this->assertDatabaseHas('post_poll_options', ['label' => 'Cafe', 'position' => 0]);
        $this->assertDatabaseHas('post_poll_options', ['label' => 'Home', 'position' => 1]);
    }

    public function test_feed_displays_the_poll_question_to_sighted_users(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$post] = $this->createPoll($author);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertSee('<h3 class="skuy-poll__question">Choose one</h3>', false);
    }

    public function test_poll_vote_total_is_visible_on_public_profiles_to_guests(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        [, $pollId, $firstOptionId] = $this->createPoll($author);
        DB::table('post_poll_votes')->insert([
            'poll_id' => $pollId,
            'option_id' => $firstOptionId,
            'user_id' => $voter->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('user.profile', $author->username))
            ->assertOk()
            ->assertSeeText(trans_choice('polls.votes', 1, ['count' => 1]));
    }

    public function test_poll_vote_total_is_visible_to_a_viewer_who_has_voted(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        [, $pollId, $firstOptionId] = $this->createPoll($author);
        DB::table('post_poll_votes')->insert([
            'poll_id' => $pollId,
            'option_id' => $firstOptionId,
            'user_id' => $voter->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($voter)
            ->get(route('user.profile', $author->username))
            ->assertOk()
            ->assertSeeText(trans_choice('polls.votes', 1, ['count' => 1]));
    }

    public function test_poll_vote_is_idempotent_for_the_same_choice_and_rejects_a_second_choice(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        [$post, $pollId, $firstOptionId, $secondOptionId] = $this->createPoll($author);

        $firstResponse = $this->actingAs($voter)->from(route('feed'))->post(route('posts.poll-votes', $post), [
            'option_id' => $firstOptionId,
        ]);
        $firstResponse->assertRedirect(route('feed'));
        $this->assertDatabaseHas('post_poll_votes', [
            'poll_id' => $pollId,
            'option_id' => $firstOptionId,
            'user_id' => $voter->id,
        ]);

        $sameChoice = $this->actingAs($voter)->from(route('feed'))->post(route('posts.poll-votes', $post), [
            'option_id' => $firstOptionId,
        ]);
        $sameChoice->assertRedirect(route('feed'));

        $differentChoice = $this->actingAs($voter)->from(route('feed'))->post(route('posts.poll-votes', $post), [
            'option_id' => $secondOptionId,
        ]);
        $differentChoice->assertSessionHasErrors('option_id');
        $this->assertSame(1, DB::table('post_poll_votes')->where('poll_id', $pollId)->where('user_id', $voter->id)->count());
    }

    public function test_poll_vote_rejects_an_option_from_another_post(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        [$post] = $this->createPoll($author, 'First poll');
        [, , , $foreignOptionId] = $this->createPoll($author, 'Second poll');

        $this->actingAs($voter)
            ->from(route('feed'))
            ->post(route('posts.poll-votes', $post), ['option_id' => $foreignOptionId])
            ->assertSessionHasErrors('option_id');

        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_only_the_post_owner_can_pin_and_unpin_a_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $owner->posts()->create(['content' => 'Pin this post']);

        $this->actingAs($owner)->post(route('posts.pin', $post))->assertRedirect();
        $this->assertNotNull($post->fresh()->pinned_at);
        $this->assertDatabaseHas('user_pinned_posts', ['user_id' => $owner->id, 'post_id' => $post->id]);

        $this->actingAs($other)->post(route('posts.pin', $post))->assertForbidden();
        $this->assertNotNull($post->fresh()->pinned_at);

        $this->actingAs($owner)->post(route('posts.pin', $post))->assertRedirect();
        $this->assertNull($post->fresh()->pinned_at);
        $this->assertDatabaseMissing('user_pinned_posts', ['user_id' => $owner->id, 'post_id' => $post->id]);
    }

    public function test_a_user_can_have_only_one_pinned_post(): void
    {
        $owner = User::factory()->create();
        $first = $owner->posts()->create(['content' => 'First post']);
        $second = $owner->posts()->create(['content' => 'Second post']);

        $this->actingAs($owner)->post(route('posts.pin', $first))->assertRedirect();
        $this->actingAs($owner)->post(route('posts.pin', $second))->assertRedirect();

        $this->assertNull($first->fresh()->pinned_at);
        $this->assertNotNull($second->fresh()->pinned_at);
        $this->assertSame(1, Post::query()->where('user_id', $owner->id)->whereNotNull('pinned_at')->count());
        $this->assertDatabaseHas('user_pinned_posts', ['user_id' => $owner->id, 'post_id' => $second->id]);
    }

    public function test_poll_votes_have_indexes_for_option_counts_and_account_deletion_queries(): void
    {
        $indexColumns = array_column(\Illuminate\Support\Facades\Schema::getIndexes('post_poll_votes'), 'columns');

        $this->assertContains(['option_id'], $indexColumns);
        $this->assertContains(['user_id'], $indexColumns);
    }

    public function test_pin_registry_has_a_unique_user_key_in_the_database(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('user_pinned_posts'));

        $uniqueIndexColumns = collect(\Illuminate\Support\Facades\Schema::getIndexes('user_pinned_posts'))
            ->filter(static fn (array $index): bool => $index['unique'])
            ->pluck('columns')
            ->all();

        $this->assertContains(['user_id'], $uniqueIndexColumns);
    }

    private function createPoll(User $author, string $content = 'Poll post'): array
    {
        $post = $author->posts()->create(['content' => $content]);
        $pollId = DB::table('post_polls')->insertGetId([
            'post_id' => $post->id,
            'question' => 'Choose one',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstOptionId = DB::table('post_poll_options')->insertGetId([
            'poll_id' => $pollId,
            'label' => 'Cafe',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondOptionId = DB::table('post_poll_options')->insertGetId([
            'poll_id' => $pollId,
            'label' => 'Home',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$post, $pollId, $firstOptionId, $secondOptionId];
    }
}
