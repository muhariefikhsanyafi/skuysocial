<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HashtagTrendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hashtags_from_a_new_post_are_synced_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Post #Laravel #laravel #P1',
        ]);

        $response->assertRedirect(route('feed'));

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertDatabaseCount('hashtag_post', 2);
        $this->assertSame(
            ['laravel', 'p1'],
            Hashtag::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_editing_a_post_syncs_removed_and_new_hashtags(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Post #lama #tetap',
        ]);

        $post = Post::query()->latest('id')->firstOrFail();
        $oldHashtag = Hashtag::query()->where('name', 'lama')->firstOrFail();
        $keptHashtag = Hashtag::query()->where('name', 'tetap')->firstOrFail();

        $response = $this->actingAs($user)->patch(route('posts.update', $post), [
            'content' => 'Post #baru #tetap',
        ]);

        $response->assertRedirect(route('feed'));

        $newHashtag = Hashtag::query()->where('name', 'baru')->firstOrFail();

        $this->assertDatabaseMissing('hashtag_post', [
            'post_id' => $post->id,
            'hashtag_id' => $oldHashtag->id,
        ]);
        $this->assertDatabaseHas('hashtag_post', [
            'post_id' => $post->id,
            'hashtag_id' => $keptHashtag->id,
        ]);
        $this->assertDatabaseHas('hashtag_post', [
            'post_id' => $post->id,
            'hashtag_id' => $newHashtag->id,
        ]);
    }

    public function test_trending_counts_distinct_posts_not_hashtag_occurrences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Post pertama #alpha #alpha',
        ]);
        $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Post kedua #alpha',
        ]);

        $response = $this->actingAs($user)->get(route('feed'));
        $trending = $response->viewData('trendingHashtags');

        $this->assertSame(2, $trending['alpha']);
        $this->assertSame(2, DB::table('hashtag_post')->count());
    }

    public function test_trending_returns_top_five_with_deterministic_ties(): void
    {
        $user = User::factory()->create();
        $names = ['alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta'];

        foreach ($names as $name) {
            $hashtag = Hashtag::create(['name' => $name]);
            $post = Post::create([
                'user_id' => $user->id,
                'content' => 'Post tanpa hashtag inline',
            ]);
            $post->hashtags()->attach($hashtag->id);
        }

        $alpha = Hashtag::query()->where('name', 'alpha')->firstOrFail();
        $secondAlphaPost = Post::create([
            'user_id' => $user->id,
            'content' => 'Post kedua tanpa hashtag inline',
        ]);
        $secondAlphaPost->hashtags()->attach($alpha->id);

        $response = $this->actingAs($user)->get(route('feed'));
        $trending = $response->viewData('trendingHashtags');

        $this->assertSame(
            ['alpha', 'beta', 'delta', 'epsilon', 'gamma'],
            array_keys($trending),
        );
        $this->assertCount(5, $trending);
        $this->assertSame(2, $trending['alpha']);
    }

    public function test_empty_trending_is_valid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee('Belum ada topik hangat hari ini.');
        $this->assertSame([], $response->viewData('trendingHashtags'));
    }

    public function test_deleting_a_post_removes_its_hashtag_pivots(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'hapus']);
        $post = Post::create([
            'user_id' => $user->id,
            'content' => 'Post untuk dihapus',
        ]);
        $post->hashtags()->attach($hashtag->id);

        $post->delete();

        $this->assertDatabaseMissing('hashtag_post', [
            'post_id' => $post->id,
            'hashtag_id' => $hashtag->id,
        ]);
    }

    public function test_hashtag_post_pair_is_unique_in_the_database(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'unik']);
        $post = Post::create([
            'user_id' => $user->id,
            'content' => 'Post unik',
        ]);

        DB::table('hashtag_post')->insert([
            'post_id' => $post->id,
            'hashtag_id' => $hashtag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('hashtag_post')->insert([
            'post_id' => $post->id,
            'hashtag_id' => $hashtag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
