<?php

namespace Tests\Feature;

use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModerationStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_default_to_active_with_version_zero(): void
    {
        $user = new User([
            'name' => 'Database Default',
            'username' => 'database_default',
            'email' => 'database-default@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->save();
        $user->refresh();

        $this->assertSame(ModerationState::Active->value, $user->moderation_state);
        $this->assertSame(ModerationState::Active, $user->moderationState());
        $this->assertNull($user->moderation_state_changed_at);
        $this->assertSame(0, $user->moderationStateVersion());
        $this->assertSame(0, $user->authSessionVersion());
    }

    public function test_moderation_state_has_the_expected_index(): void
    {
        $index = collect(Schema::getIndexes('users'))->first(
            fn (array $index): bool => $index['name'] === 'users_moderation_state_index',
        );

        $this->assertNotNull($index);
        $this->assertSame(['moderation_state'], $index['columns']);
    }

    public function test_database_rejects_an_unknown_user_moderation_state(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['moderation_state' => 'unknown']);
    }

    public function test_database_rejects_an_unknown_audit_new_state(): void
    {
        [$actor, $target] = $this->actorAndTarget();

        $this->expectException(QueryException::class);

        DB::table('moderation_events')->insert([
            'target_user_id' => $target->id,
            'actor_user_id' => $actor->id,
            'previous_state' => ModerationState::Active->value,
            'new_state' => 'unknown',
            'reason_code' => ModerationReason::Other->value,
            'reason_text' => 'Invalid state constraint test.',
            'created_at' => now(),
        ]);
    }

    public function test_database_rejects_an_unknown_audit_reason_code(): void
    {
        [$actor, $target] = $this->actorAndTarget();

        $this->expectException(QueryException::class);

        DB::table('moderation_events')->insert([
            'target_user_id' => $target->id,
            'actor_user_id' => $actor->id,
            'previous_state' => ModerationState::Active->value,
            'new_state' => ModerationState::Restricted->value,
            'reason_code' => 'unknown',
            'reason_text' => 'Invalid reason constraint test.',
            'created_at' => now(),
        ]);
    }

    public function test_moderation_security_fields_are_not_mass_assignable_or_serialized(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Protected Fields',
            'moderation_state' => ModerationState::Banned->value,
            'moderation_state_changed_at' => now(),
            'moderation_state_version' => 99,
            'auth_session_version' => 99,
        ]);

        $this->assertNull($user->moderation_state);
        $this->assertNull($user->moderation_state_changed_at);
        $this->assertNull($user->moderation_state_version);
        $this->assertNull($user->auth_session_version);

        $persisted = User::factory()->create();
        $attributes = $persisted->toArray();

        $this->assertArrayNotHasKey('moderation_state_version', $attributes);
        $this->assertArrayNotHasKey('auth_session_version', $attributes);
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }

    public function test_state_capabilities_match_the_product_contract(): void
    {
        $expectations = [
            ModerationState::Active->value => [true, true, true],
            ModerationState::Restricted->value => [true, true, false],
            ModerationState::Suspended->value => [false, false, false],
            ModerationState::Banned->value => [false, false, false],
        ];

        foreach ($expectations as $state => [$authenticates, $retainsSession, $writes]) {
            $user = (new User)->forceFill([
                'moderation_state' => $state,
                'auth_session_version' => 0,
            ]);

            $this->assertSame($authenticates, $user->canAuthenticate(), $state);
            $this->assertSame($retainsSession, $user->canMaintainAuthenticatedSession(), $state);
            $this->assertSame($writes, $user->canPerformSocialWrites(), $state);
        }
    }

    public function test_unknown_state_fails_closed_without_throwing(): void
    {
        $user = (new User)->forceFill([
            'role' => UserRole::Administrator->value,
            'email_verified_at' => now(),
            'moderation_state' => 'unknown',
            'auth_session_version' => 0,
        ]);

        $this->assertNull($user->moderationState());
        $this->assertFalse($user->canAuthenticate());
        $this->assertFalse($user->canMaintainAuthenticatedSession());
        $this->assertFalse($user->canPerformSocialWrites());
        $this->assertFalse($user->isUsableAdministrator());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_partial_user_selection_without_moderation_fields_fails_closed(): void
    {
        $administrator = User::factory()->administrator()->create();
        $partial = User::query()
            ->select(['id', 'role', 'email_verified_at'])
            ->findOrFail($administrator->id);

        $this->assertNull($partial->moderationState());
        $this->assertNull($partial->moderationStateVersion());
        $this->assertNull($partial->authSessionVersion());
        $this->assertFalse($partial->canAuthenticate());
        $this->assertFalse($partial->canPerformSocialWrites());
        $this->assertFalse($partial->isUsableAdministrator());
    }

    public function test_reason_taxonomy_is_stable_and_complete(): void
    {
        $this->assertSame([
            'spam',
            'harassment',
            'impersonation',
            'platform_abuse',
            'security_abuse',
            'policy_violation',
            'other',
        ], array_column(ModerationReason::cases(), 'value'));
    }

    public function test_audit_schema_has_no_updated_at_or_cascading_user_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumns('moderation_events', [
            'id',
            'target_user_id',
            'actor_user_id',
            'previous_state',
            'new_state',
            'reason_code',
            'reason_text',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('moderation_events', 'updated_at'));
        $this->assertSame([], Schema::getForeignKeys('moderation_events'));
    }

    /**
     * @return array{User, User}
     */
    private function actorAndTarget(): array
    {
        return [
            User::factory()->administrator()->create(),
            User::factory()->create(),
        ];
    }
}
