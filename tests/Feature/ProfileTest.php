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

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_custom_profile_settings_have_associated_fields_and_keyboard_accessible_upload_controls(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('user.edit'));

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

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'updated_user',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('updated_user', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_standard_profile_update_accepts_bio_up_to_255_characters(): void
    {
        $user = User::factory()->create();
        $bio = str_repeat('s', 255);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'bio' => $bio,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame($bio, $user->refresh()->bio);
    }

    public function test_mixed_case_unused_username_is_stored_in_lowercase(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => 'NewProfileUser',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('newprofileuser', $user->refresh()->username);
    }

    public function test_changing_only_username_capitalization_keeps_own_canonical_username(): void
    {
        $user = User::factory()->create(['username' => 'arief']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => 'ArIeF',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('arief', $user->refresh()->username);
    }

    public function test_case_variant_of_another_users_username_is_rejected_by_validation(): void
    {
        $user = User::factory()->create(['username' => 'profile-owner']);
        User::factory()->create(['username' => 'taken_user']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => 'Taken_User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasErrors('username')
            ->assertRedirect('/profile');

        $this->assertSame('profile-owner', $user->refresh()->username);
    }

    public function test_normalized_username_route_binding_continues_to_resolve_profile(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => 'RouteProfileUser',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($user)
            ->get('/user/routeprofileuser')
            ->assertOk()
            ->assertSee('routeprofileuser');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_valid_cover_and_avatar_images_are_accepted_and_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'cover' => UploadedFile::fake()->image('cover.jpg'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

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
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'cover' => UploadedFile::fake()->create('cover.txt', 10, 'text/plain'),
            ]);

        $response
            ->assertSessionHasErrors('cover')
            ->assertRedirect('/profile');

        $this->assertSame($existingCover, $user->refresh()->cover);
        Storage::disk('public')->assertExists($existingCover);
    }

    public function test_oversized_cover_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'cover' => UploadedFile::fake()->image('cover.jpg')->size(2049),
            ]);

        $response
            ->assertSessionHasErrors('cover')
            ->assertRedirect('/profile');

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
            ->from(route('user.edit'))
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => str_repeat('b', 256),
            ]);

        $response
            ->assertSessionHasErrors('bio')
            ->assertRedirect(route('user.edit'));

        $this->assertSame('Existing bio', $user->refresh()->bio);
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
