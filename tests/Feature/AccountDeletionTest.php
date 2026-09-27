<?php

namespace Tests\Feature;

use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_danger_zone_is_present_only_in_account_settings(): void
    {
        $user = User::factory()->create();

        $accountSettings = $this->actingAs($user)
            ->get(route('profile.edit', $user->username))
            ->assertOk();

        $accountSettings->assertSee(__('settings.delete.danger_heading'))
            ->assertSee('name="username_confirmation"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="delete"', false);

        $this->get(route('user.edit', $user->username))
            ->assertOk()
            ->assertDontSee('Danger Zone')
            ->assertDontSee(__('settings.delete.heading'));
    }

    public function test_guest_cannot_delete_an_account(): void
    {
        $user = User::factory()->create();

        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'username_confirmation' => $user->username,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_wrong_password_does_not_delete_the_authenticated_account(): void
    {
        $user = User::factory()->create(['username' => 'password.owner']);

        $this->actingAs($user)
            ->from(route('profile.edit', $user->username))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
                'username_confirmation' => $user->username,
            ])
            ->assertSessionHasErrorsIn('userDeletion', ['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_deletion_ignores_an_untrusted_target_and_invalidates_the_current_session(): void
    {
        $user = User::factory()->create(['username' => 'self.owner']);
        $otherUser = User::factory()->create(['username' => 'other.owner']);

        $this->actingAs($user);
        $sessionIdBeforeDeletion = session()->getId();

        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'username_confirmation' => $user->username,
            'user_id' => $otherUser->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($sessionIdBeforeDeletion, session()->getId());
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    public function test_get_profile_legacy_route_does_not_delete_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertRedirect(route('profile.edit', $user->username));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_private_account_settings_still_return_404_for_a_username_mismatch(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit', $otherUser->username))
            ->assertNotFound();
    }

    public function test_username_confirmation_must_match_the_authenticated_user(): void
    {
        $user = User::factory()->create(['username' => 'delete.owner']);
        $otherUser = User::factory()->create(['username' => 'different.owner']);

        $this->actingAs($user)
            ->from(route('profile.edit', $user->username))
            ->delete(route('profile.destroy'), [
                'password' => 'password',
                'username_confirmation' => $otherUser->username,
            ])
            ->assertSessionHasErrorsIn('userDeletion', ['username_confirmation']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    public function test_administrator_may_delete_their_account_when_another_usable_administrator_remains(): void
    {
        $administrator = User::factory()->administrator()->create(['username' => 'admin.owner']);
        $remainingAdministrator = User::factory()->administrator()->create(['username' => 'admin.remaining']);

        $this->actingAs($administrator)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
                'username_confirmation' => $administrator->username,
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $administrator->id]);
        $this->assertDatabaseHas('users', ['id' => $remainingAdministrator->id]);
        $this->assertGuest();
    }

    public function test_account_deletion_preserves_immutable_moderation_audit_events(): void
    {
        $user = User::factory()->create(['username' => 'audit.target']);
        $actor = User::factory()->create(['username' => 'audit.actor']);
        $event = ModerationEvent::query()->create([
            'target_user_id' => $user->id,
            'actor_user_id' => $actor->id,
            'previous_state' => 'restricted',
            'new_state' => 'active',
            'reason_code' => 'other',
            'reason_text' => 'Disposable test audit event.',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
                'username_confirmation' => $user->username,
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('moderation_events', [
            'id' => $event->id,
            'target_user_id' => $user->id,
        ]);
    }

    public function test_the_only_usable_administrator_cannot_delete_their_account(): void
    {
        $administrator = User::factory()->administrator()->create(['username' => 'sole.admin']);

        $this->actingAs($administrator)
            ->from(route('profile.edit', $administrator->username))
            ->delete(route('profile.destroy'), [
                'password' => 'password',
                'username_confirmation' => $administrator->username,
            ])
            ->assertSessionHasErrorsIn('userDeletion', ['username_confirmation']);

        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }
}
