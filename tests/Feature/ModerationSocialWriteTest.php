<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModerationSocialWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_restricted_user_cannot_create_a_thread_or_upload_files(): void
    {
        Storage::fake('public');
        $user = $this->restrictedUser();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'content' => 'Blocked thread.',
                'images' => [UploadedFile::fake()->image('blocked.jpg')],
            ])
            ->assertForbidden()
            ->assertSeeText('SkuySocial')
            ->assertSeeText(trans('moderation.forbidden.title'))
            ->assertSeeText(trans('moderation.restricted.notice_title'))
            ->assertSeeText(trans('moderation.restricted.denial_body'))
            ->assertSeeText(trans('moderation.forbidden.back_to_home'))
            ->assertSee('href="'.route('feed').'"', false);

        $this->assertDatabaseCount('posts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Notification::assertNothingSent();
    }

    public function test_restricted_user_cannot_update_or_delete_an_owned_thread(): void
    {
        $user = $this->restrictedUser();
        $post = $this->createPost($user, 'Original content.');

        $this->actingAs($user)
            ->patch(route('posts.update', $post), ['content' => 'Changed content.'])
            ->assertForbidden();
        $this->assertSame('Original content.', $post->refresh()->content);

        $this->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        Notification::assertNothingSent();
    }

    public function test_restricted_user_cannot_create_comments_or_replies(): void
    {
        $user = $this->restrictedUser();
        $owner = User::factory()->create();
        $post = $this->createPost($owner, 'Comment target.');
        $root = Comment::query()->create([
            'user_id' => $owner->id,
            'post_id' => $post->id,
            'content' => 'Existing root.',
        ]);

        $this->actingAs($user)
            ->post(route('comments.store', $post), ['content' => 'Blocked root.'])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('comments.store', $post), [
                'content' => 'Blocked reply.',
                'parent_id' => $root->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 1);
        Notification::assertNothingSent();
    }

    public function test_restricted_user_cannot_like_or_unlike_a_post(): void
    {
        $user = $this->restrictedUser();
        $post = $this->createPost(User::factory()->create(), 'Like target.');

        $this->actingAs($user)
            ->post(route('posts.like', $post))
            ->assertForbidden();
        $this->assertDatabaseCount('likes', 0);

        Like::query()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->post(route('posts.like', $post))
            ->assertForbidden();
        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
        Notification::assertNothingSent();
    }

    public function test_restricted_json_write_has_a_stable_error_code(): void
    {
        $user = $this->restrictedUser();
        $post = $this->createPost(User::factory()->create(), 'JSON target.');

        $this->actingAs($user)
            ->postJson(route('posts.like', $post))
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson([
                'message' => trans('moderation.denials.social_write'),
                'code' => 'moderation_state_forbids_social_write',
            ]);
    }

    public function test_restricted_write_is_forbidden_before_missing_model_binding(): void
    {
        $user = $this->restrictedUser();

        $this->actingAs($user)
            ->post('/posts/missing-post-uid/like')
            ->assertForbidden();
    }

    public function test_restricted_user_cannot_like_or_unlike_a_comment(): void
    {
        $user = $this->restrictedUser();
        $owner = User::factory()->create();
        $post = $this->createPost($owner, 'Comment-like target.');
        $comment = Comment::query()->create([
            'user_id' => $owner->id,
            'post_id' => $post->id,
            'content' => 'Comment target.',
        ]);

        $this->actingAs($user)
            ->post(route('comments.like', $comment))
            ->assertForbidden();
        $this->assertDatabaseCount('comment_likes', 0);

        DB::table('comment_likes')->insert([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('comments.like', $comment))
            ->assertForbidden();
        $this->assertDatabaseHas('comment_likes', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_restricted_user_cannot_repost_or_unrepost(): void
    {
        $user = $this->restrictedUser();
        $original = $this->createPost(User::factory()->create(), 'Repost target.');

        $this->actingAs($user)
            ->post(route('posts.repost', $original))
            ->assertForbidden();
        $this->assertDatabaseMissing('posts', [
            'user_id' => $user->id,
            'repost_id' => $original->id,
        ]);

        $repost = Post::query()->create([
            'user_id' => $user->id,
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $this->actingAs($user)
            ->post(route('posts.repost', $original))
            ->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $repost->id]);
        Notification::assertNothingSent();
    }

    public function test_restricted_user_cannot_follow_or_unfollow(): void
    {
        $user = $this->restrictedUser();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('user.follow', $target))
            ->assertForbidden();
        $this->assertDatabaseCount('follows', 0);

        DB::table('follows')->insert([
            'follower_id' => $user->id,
            'following_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('user.follow', $target))
            ->assertForbidden();
        $this->assertDatabaseHas('follows', [
            'follower_id' => $user->id,
            'following_id' => $target->id,
        ]);
        Notification::assertNothingSent();
    }

    public function test_restricted_user_cannot_bookmark_or_unbookmark(): void
    {
        $user = $this->restrictedUser();
        $post = $this->createPost(User::factory()->create(), 'Bookmark target.');

        $this->actingAs($user)
            ->post(route('bookmarks.store', $post))
            ->assertForbidden();
        $this->assertDatabaseCount('bookmarks', 0);

        Bookmark::query()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->delete(route('bookmarks.destroy', $post))
            ->assertForbidden();
        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_restricted_user_cannot_update_public_profile_or_email(): void
    {
        Storage::fake('public');
        $user = $this->restrictedUser([
            'name' => 'Original Name',
            'username' => 'original_name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Changed Name',
                'username' => 'changed_name',
                'email' => 'changed@example.com',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertForbidden()
            ->assertSeeText(trans('moderation.restricted.denial_body'));

        $user->refresh();
        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original_name', $user->username);
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($user)
            ->post(route('user.update'), [
                'name' => 'Changed Again',
                'bio' => 'Changed bio.',
                'cover' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertForbidden();

        $user->refresh();
        $this->assertSame('Original Name', $user->name);
        $this->assertNull($user->bio);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_restricted_user_cannot_delete_their_account(): void
    {
        $user = $this->restrictedUser();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_restricted_user_can_change_password(): void
    {
        $user = $this->restrictedUser();

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_restricted_user_can_change_locale(): void
    {
        $user = $this->restrictedUser(['locale' => 'id']);

        $this->actingAs($user)
            ->post(route('locale.update'), [
                'locale' => 'en',
                'return_to' => '/profile',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_restricted_user_can_update_notification_read_state(): void
    {
        $user = $this->restrictedUser();
        $actor = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'moderation-safe-action-test',
            'data' => [
                'type' => 'follow',
                'name' => $actor->name,
                'username' => $actor->username,
                'avatar' => $actor->avatar,
                'message' => 'Unread notification.',
            ],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_restricted_user_can_read_authenticated_content_and_own_profile(): void
    {
        $user = $this->restrictedUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get(route('profile.edit', $user->username))->assertOk();
        $this->actingAs($user)->get(route('user.profile', $user))->assertOk();
    }

    private function restrictedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge($attributes, [
            'moderation_state' => ModerationState::Restricted->value,
        ]));
    }

    private function createPost(User $user, string $content): Post
    {
        return Post::query()->create([
            'user_id' => $user->id,
            'content' => $content,
        ]);
    }
}
