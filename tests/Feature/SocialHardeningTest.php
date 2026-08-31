<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_parent_must_belong_to_the_same_post(): void
    {
        $user = User::factory()->create(['username' => 'author']);
        $post = Post::create(['user_id' => $user->id, 'content' => 'Post utama']);
        $otherPost = Post::create(['user_id' => $user->id, 'content' => 'Post lain']);
        $foreignParent = Comment::create([
            'post_id' => $otherPost->id,
            'user_id' => $user->id,
            'content' => 'Komentar dari post lain',
        ]);

        $response = $this->actingAs($user)->post(
            route('comments.store', $post->uid),
            ['content' => 'Balasan tidak valid', 'parent_id' => $foreignParent->id],
        );

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('comments', ['content' => 'Balasan tidak valid']);
    }

    public function test_notification_legacy_post_key_contains_the_post_uid(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $liker = User::factory()->create(['username' => 'liker']);
        $post = Post::create(['user_id' => $owner->id, 'content' => 'Post untuk notifikasi']);

        $payload = (new PostLiked($liker, $post))->toArray($owner);

        $this->assertSame($post->uid, $payload['post_slug']);
    }

    public function test_is_verified_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $user->fill(['is_verified' => true]);

        $this->assertFalse((bool) $user->is_verified);
    }

    public function test_comment_like_pair_is_unique(): void
    {
        $user = User::factory()->create();
        $comment = Comment::create([
            'post_id' => Post::create(['user_id' => $user->id, 'content' => 'Post'])->id,
            'user_id' => $user->id,
            'content' => 'Komentar',
        ]);

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
