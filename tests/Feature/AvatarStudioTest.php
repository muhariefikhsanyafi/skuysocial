<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use App\Services\GravatarAvatar;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AvatarStudioTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'studio-'.uniqid().'@example.test']);
    }

    private function png(): string
    {
        return UploadedFile::fake()->image('avatar.png', 256, 256)->getContent();
    }

    private function preview(User $user, string $style): string
    {
        return app(GravatarAvatar::class)->previewUrl($user, $style);
    }

    private function fakeGravatar(): void
    {
        $png = $this->png();
        Http::fake(['https://www.gravatar.com/avatar/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    }

    public function test_preview_supports_all_styles_and_caches_without_leaking_email(): void
    {
        $user = $this->owner();
        $this->fakeGravatar();

        foreach (GravatarAvatar::STYLES as $style) {
            $this->actingAs($user)->get($this->preview($user, $style))
                ->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get($this->preview($user, $style))->assertOk();
        }
        Http::assertSentCount(5);
        Http::assertSent(function ($request) use ($user): bool {
            return str_starts_with($request->url(), 'https://www.gravatar.com/avatar/'.app(GravatarAvatar::class)->hash($user))
                && $request['f'] === 'y' && $request['r'] === 'g' && $request['s'] === 256
                && ! str_contains($request->url(), $user->email)
                && ! str_contains($request->url(), hash('sha256', $user->email));
        });
        $this->assertNull($user->refresh()->avatar);
    }

    public function test_pseudonym_is_stable_across_email_and_name_changes_and_distinct_for_accounts(): void
    {
        $first = $this->owner();
        $second = $this->owner();
        $service = app(GravatarAvatar::class);
        $hash = $service->hash($first);
        $first->forceFill(['email' => 'changed@example.test', 'name' => 'Synthetic renamed'])->save();
        $this->assertSame($hash, $service->hash($first->fresh()));
        $this->assertNotSame($hash, $service->hash($second));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_generated_avatar_is_persisted_and_old_photo_is_preserved_across_refresh_and_login(): void
    {
        Storage::fake('public');
        $user = $this->owner();
        $user->update(['avatar' => 'avatars/old-photo.jpg']);
        Storage::disk('public')->put($user->avatar, 'old photo');
        $this->fakeGravatar();
        $this->actingAs($user)->get($this->preview($user, 'retro'))->assertOk();
        $response = $this->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'retro']);
        $response->assertOk()->assertJsonPath('style', 'retro');
        $path = $user->refresh()->avatar;
        $this->assertStringStartsWith('avatars/gravatar/retro-', $path);
        Storage::disk('public')->assertExists([$path, 'avatars/old-photo.jpg']);
        Http::assertSentCount(1);
        $url = $response->json('avatar_url');
        $this->get(route('user.profile', $user->username))->assertOk()->assertSee($url, false);
        $this->get(route('feed'))->assertOk()->assertSee($url, false);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('user.edit', $user->username))->assertOk()->assertSee($url, false)->assertSee('retro');
        $this->assertSame($path, $user->refresh()->avatar);
        Http::assertSentCount(1);
    }

    public function test_generated_avatar_can_be_replaced_with_an_uploaded_photo_without_deleting_either(): void
    {
        Storage::fake('public');
        $this->fakeGravatar();
        $user = $this->owner();
        $this->actingAs($user)->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'wavatar'])->assertOk();
        $generated = $user->refresh()->avatar;
        $this->postJson(route('avatar.store'), ['source' => 'upload', 'avatar' => UploadedFile::fake()->image('my-photo.jpg')])
            ->assertOk()->assertJsonPath('style', null);
        $uploaded = $user->refresh()->avatar;
        $this->assertNotSame($generated, $uploaded);
        Storage::disk('public')->assertExists([$generated, $uploaded]);
        Http::assertSentCount(1);
    }

    public function test_failure_keeps_current_photo_and_negative_cache_avoids_repeated_calls(): void
    {
        $user = $this->owner();
        $user->update(['avatar' => 'avatars/previous.png']);
        Http::fake(['*' => Http::response('unavailable', 503)]);
        $preview = $this->actingAs($user)->get($this->preview($user, 'wavatar'))->assertStatus(503);
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->get($this->preview($user, 'wavatar'))->assertStatus(503);
        $this->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'wavatar'])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertSame('avatars/previous.png', $user->refresh()->avatar);
        Http::assertSentCount(1);
    }

    public function test_bad_images_redirects_and_oversized_responses_are_rejected(): void
    {
        $user = $this->owner();
        $responses = [
            ['<svg/>', 200, ['Content-Type' => 'image/svg+xml']],
            ['not an image', 200, ['Content-Type' => 'image/png']],
            [$this->png(), 302, ['Content-Type' => 'image/png', 'Location' => 'http://localhost/private']],
            [str_repeat('x', GravatarAvatar::MAX_BYTES + 1), 200, ['Content-Type' => 'image/png']],
            [UploadedFile::fake()->image('small.png', 64, 64)->getContent(), 200, ['Content-Type' => 'image/png']],
        ];
        foreach ($responses as $index => [$body, $status, $headers]) {
            Http::fake(['*' => Http::response($body, $status, $headers)]);
            $this->actingAs($user)->get($this->preview($user, GravatarAvatar::STYLES[$index]))->assertStatus(503);
        }
        $this->assertNull($user->refresh()->avatar);
    }

    public function test_client_cannot_supply_a_url_or_target_another_account(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        Http::fake();
        $this->actingAs($owner)->postJson(route('avatar.store'), [
            'source' => 'gravatar', 'style' => 'wavatar', 'url' => 'http://localhost/private', 'user_id' => $other->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['url', 'user_id']);
        $this->get(route('user.edit', $other->username))->assertNotFound();
        $this->get(route('avatar.preview', 'invalid-style'))->assertNotFound();
        $this->assertNull($other->refresh()->avatar);
        Http::assertNothingSent();
    }

    public function test_guests_and_unverified_accounts_cannot_save(): void
    {
        $this->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'retro'])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create(['email' => 'unverified@example.test']))
            ->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'retro'])->assertForbidden();
    }

    public function test_storage_or_database_failure_does_not_change_old_avatar(): void
    {
        Storage::fake('public');
        $user = $this->owner();
        $user->update(['avatar' => 'avatars/old.png']);
        Storage::disk('public')->put($user->avatar, 'old');
        $this->fakeGravatar();
        User::saving(static function (User $saved) use ($user): void {
            if ($saved->id === $user->id && $saved->isDirty('avatar')) {
                throw new RuntimeException('Synthetic failure');
            }
        });
        $this->actingAs($user)->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'retro'])->assertUnprocessable();
        $this->assertSame('avatars/old.png', $user->refresh()->avatar);
        $this->assertSame(['avatars/old.png'], Storage::disk('public')->allFiles());
    }

    public function test_notifications_use_current_avatar_and_payload_is_not_rewritten(): void
    {
        $viewer = $this->owner();
        $actor = $this->owner();
        $actor->update(['avatar' => 'avatars/old.png']);
        $post = Post::create(['user_id' => $viewer->id, 'content' => '[Synthetic] notification']);
        $viewer->notify(new PostLiked($actor, $post));
        $actor->update(['avatar' => 'avatars/gravatar/retro-synthetic.png']);
        $this->actingAs($viewer)->get(route('activity.index'))->assertOk()
            ->assertSee(asset('storage/'.$actor->avatar), false)->assertDontSee('src="'.asset('storage/avatars/old.png').'"', false);
        $this->assertSame('avatars/old.png', $viewer->notifications()->first()->data['avatar']);
    }

    public function test_feed_and_editor_do_not_fetch_gravatar_during_render(): void
    {
        Http::fake();
        $user = $this->owner();
        $this->actingAs($user)->get(route('feed'))->assertOk();
        $this->get(route('user.edit', $user->username))->assertOk()->assertSee('Avatar Studio')->assertSee('Robohash');
        Http::assertNothingSent();
    }

    public function test_busy_cache_lock_falls_back_without_remote_fetch(): void
    {
        $user = $this->owner();
        Http::fake();
        $key = 'avatar-studio:v1:'.app(GravatarAvatar::class)->hash($user).':wavatar:lock';
        $lock = Cache::lock($key, 15);
        $lock->get();
        $this->actingAs($user)->get($this->preview($user, 'wavatar'))->assertStatus(503);
        Http::assertNothingSent();
        $lock->release();
    }

    public function test_failure_cached_before_lock_acquisition_does_not_fetch_again(): void
    {
        $user = $this->owner();
        $this->fakeGravatar();
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once()->andReturn(true);
        Cache::shouldReceive('get')->twice()->andReturn(null, false);
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('put')->andReturn(true);

        $failed = false;
        try {
            app(GravatarAvatar::class)->image($user, 'wavatar');
        } catch (RuntimeException) {
            $failed = true;
        }
        $this->assertTrue($failed, 'A failure cached between the first read and acquiring the lock must be respected.');
        Http::assertNothingSent();
    }

    public function test_preview_urls_are_account_scoped_and_foreign_hashes_are_rejected(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        Http::fake();
        $this->assertNotSame($this->preview($owner, 'retro'), $this->preview($other, 'retro'));
        $this->actingAs($owner)->get($this->preview($other, 'retro'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_persisted_avatar_is_consistent_in_blade_inertia_comments_search_and_messages(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $viewer = $this->owner();
        $this->fakeGravatar();
        $this->actingAs($owner)->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'identicon'])->assertOk();
        $owner->refresh();
        $url = asset('storage/'.$owner->avatar);
        $post = Post::create(['user_id' => $owner->id, 'content' => '[Synthetic] consistent avatar']);
        $this->get(route('feed'))->assertOk()->assertSee($url, false);
        $this->get(route('user.profile', $owner->username))->assertOk()->assertSee($url, false);
        $this->get(route('search', ['q' => $owner->username]))->assertOk()->assertSee($url, false);
        $this->postJson(route('comments.store', $post), ['content' => '[Synthetic] comment'])
            ->assertCreated()->assertJsonPath('comment.author.avatar_url', $url);
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('posts.show', $post)));
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get(route('posts.show', $post))
            ->assertOk()->assertJsonPath('props.display_post.author.avatar_url', $url)
            ->assertJsonPath('props.comments.data.0.author.avatar_url', $url);
        $this->flushHeaders();
        $this->actingAs($viewer)->post(route('messages.start', $owner->username))->assertRedirect();
        $this->get(route('messages.index'))->assertOk()->assertSee($url, false);
        $conversation = Conversation::query()->firstOrFail();
        $this->get(route('messages.show', $conversation))->assertOk()->assertSee($url, false);
        Http::assertSentCount(1);
    }

    public function test_invalid_upload_and_invalid_style_leave_existing_avatar_unchanged(): void
    {
        $user = $this->owner();
        $user->update(['avatar' => 'avatars/original.png']);
        Http::fake();
        $this->actingAs($user)->postJson(route('avatar.store'), ['source' => 'upload', 'avatar' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->postJson(route('avatar.store'), ['source' => 'gravatar', 'style' => 'https://example.test/avatar'])
            ->assertUnprocessable()->assertJsonValidationErrors('style');
        $this->assertSame('avatars/original.png', $user->refresh()->avatar);
        Http::assertNothingSent();
    }
}
