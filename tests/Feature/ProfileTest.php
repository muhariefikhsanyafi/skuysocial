<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_page_is_displayed_on_the_canonical_route(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit', $user->username));

        $response->assertOk();
    }

    public function test_custom_profile_settings_have_associated_fields_and_keyboard_accessible_upload_controls(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('user.edit', $user->username));

        $html = $response->getContent();
        $customFormStart = strpos($html, '<form action="'.route('user.update').'"');
        $customFormEnd = strpos($html, '</form>', $customFormStart);

        $response
            ->assertOk()
            ->assertSee('<form action="'.route('user.update').'" method="POST" enctype="multipart/form-data"', false)
            ->assertSee('<label for="name"', false)
            ->assertSee('id="name"', false)
            ->assertSee('<label for="username"', false)
            ->assertSee('id="username"', false)
            ->assertSee('<label for="bio"', false)
            ->assertSee('id="bio"', false)
            ->assertSee('id="avatar"', false)
            ->assertSee('id="cover"', false)
            ->assertSee('aria-label="Ubah avatar"', false)
            ->assertSee('aria-label="Ubah sampul"', false)
            ->assertSee('@click="$refs.avatarInput.click()"', false)
            ->assertSee('@click="$refs.coverInput.click()"', false)
            ->assertSee('type="button"', false);

        $this->assertNotFalse($customFormStart);
        $this->assertNotFalse($customFormEnd);
        $customForm = substr($html, $customFormStart, $customFormEnd - $customFormStart);

        $this->assertSame(1, substr_count($customForm, '<form '));
        $this->assertDoesNotMatchRegularExpression('/<\/form(?!>)/', $html);
        $this->assertStringNotContainsString('</div>>', $html);
    }

    public function test_user_factory_generates_valid_unique_usernames(): void
    {
        $users = User::factory()->count(2)->create();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_.]+$/', $users[0]->username);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_.]+$/', $users[1]->username);
        $this->assertNotSame($users[0]->username, $users[1]->username);
    }

    public function test_public_profile_presentation_can_be_updated_from_profile_settings(): void
    {
        $user = User::factory()->create();
        $username = $user->username;

        $response = $this
            ->actingAs($user)
            ->post(route('user.update'), [
                'name' => 'Test User',
                'bio' => 'Public profile bio',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($username, $user->username);
        $this->assertSame('Public profile bio', $user->bio);
    }

    public function test_settings_updates_ignore_username_changes(): void
    {
        $user = User::factory()->create(['username' => 'read.only']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email,
                'username' => 'ChangedAccountName',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit', $user->username));

        $this->actingAs($user)
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'username' => 'ChangedProfileName',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $this->assertSame('read.only', $user->refresh()->username);
    }

    public function test_current_username_route_binding_continues_to_resolve_profile(): void
    {
        $user = User::factory()->create(['username' => 'routeprofileuser']);

        $this
            ->actingAs($user)
            ->get(route('user.profile', ['username' => $user->username]))
            ->assertOk()
            ->assertSee('@routeprofileuser');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit', $user->username));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_valid_cover_and_avatar_images_are_accepted_and_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'cover' => UploadedFile::fake()->image('cover.jpg'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $user->refresh();

        $this->assertNotNull($user->avatar);
        $this->assertNotNull($user->cover);
        Storage::disk('public')->assertExists($user->avatar);
        Storage::disk('public')->assertExists($user->cover);
    }

    public function test_non_image_cover_is_rejected_and_existing_cover_is_preserved(): void
    {
        Storage::fake('public');
        $existingCover = 'covers/existing.jpg';
        Storage::disk('public')->put($existingCover, 'existing cover');

        $user = User::factory()->create(['cover' => $existingCover]);

        $response = $this
            ->actingAs($user)
            ->from(route('user.edit', $user->username))
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'cover' => UploadedFile::fake()->create('cover.txt', 10, 'text/plain'),
            ]);

        $response
            ->assertSessionHasErrors('cover')
            ->assertRedirect(route('user.edit', $user->username));

        $this->assertSame($existingCover, $user->refresh()->cover);
        Storage::disk('public')->assertExists($existingCover);
    }

    public function test_oversized_cover_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user.edit', $user->username))
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'cover' => UploadedFile::fake()->image('cover.jpg')->size(2049),
            ]);

        $response
            ->assertSessionHasErrors('cover')
            ->assertRedirect(route('user.edit', $user->username));

        $this->assertNull($user->refresh()->cover);
    }

    public function test_custom_profile_update_accepts_bio_up_to_255_characters(): void
    {
        $user = User::factory()->create();
        $bio = str_repeat('b', 255);

        $response = $this
            ->actingAs($user)
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $bio,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $this->assertSame($bio, $user->refresh()->bio);
    }

    public function test_custom_profile_update_rejects_bio_longer_than_255_characters(): void
    {
        $user = User::factory()->create(['bio' => 'Existing bio']);

        $response = $this
            ->actingAs($user)
            ->from(route('user.edit', $user->username))
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => str_repeat('b', 256),
            ]);

        $response
            ->assertSessionHasErrors('bio')
            ->assertRedirect(route('user.edit', $user->username));

        $this->assertSame('Existing bio', $user->refresh()->bio);
    }

    public function test_account_email_update_does_not_change_public_profile_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
            'name' => 'Public Name',
            'username' => 'public.name',
            'bio' => 'Public bio',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), ['email' => 'new@example.com']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit', $user->username));

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Public Name', $user->name);
        $this->assertSame('public.name', $user->username);
        $this->assertSame('Public bio', $user->bio);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
