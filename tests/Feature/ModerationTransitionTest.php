<?php

namespace Tests\Feature;

use App\Actions\TransitionUserModerationState;
use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Models\ModerationEvent;
use App\Models\Post;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ModerationTransitionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('approvedTransitions')]
    public function test_all_approved_transitions_succeed(string $from, string $to): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create(['moderation_state' => $from]);

        $event = $this->transition()->handle(
            $actor,
            $target,
            ModerationState::from($to),
            ModerationReason::PolicyViolation,
            'Reviewed moderation transition.',
            expectedPreviousState: ModerationState::from($from),
            expectedPreviousVersion: 0,
        );

        $target->refresh();

        $this->assertSame($to, $target->moderation_state);
        $this->assertNotNull($target->moderation_state_changed_at);
        $this->assertSame(1, $target->moderationStateVersion());
        $this->assertSame(
            in_array($to, ['suspended', 'banned'], true) ? 1 : 0,
            $target->authSessionVersion(),
        );
        $this->assertSame($from, $event->previous_state);
        $this->assertSame($to, $event->new_state);
        $this->assertDatabaseCount('moderation_events', 1);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertSame(UserRole::User->value, $target->role);
    }

    public function test_same_state_transition_is_rejected_without_an_audit_event(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();

        try {
            $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Active,
                ModerationReason::Other,
                'No change.',
            );
            $this->fail('A same-state transition should be rejected.');
        } catch (DomainException) {
            $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
            $this->assertSame(0, $target->moderationStateVersion());
            $this->assertDatabaseCount('moderation_events', 0);
        }
    }

    public function test_reason_text_is_required(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::Other,
            '   ',
        );
    }

    public function test_reason_text_longer_than_two_thousand_characters_is_rejected(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::Other,
            str_repeat('x', 2001),
        );
    }

    public function test_expected_previous_state_rejects_stale_transition_atomically(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::PolicyViolation,
            'The first transition wins.',
        );

        try {
            $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Suspended,
                ModerationReason::SecurityAbuse,
                'This stale transition must not apply.',
                expectedPreviousState: ModerationState::Active,
            );

            $this->fail('A stale transition should be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(TransitionUserModerationState::STALE_STATE_ERROR_CODE, $exception->getCode());
            $this->assertSame(ModerationState::Restricted->value, $target->refresh()->moderation_state);
            $this->assertSame(1, $target->moderationStateVersion());
            $this->assertDatabaseCount('moderation_events', 1);
        }
    }

    public function test_expected_previous_version_rejects_an_aba_transition_atomically(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $transition = $this->transition();

        $transition->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::PolicyViolation,
            'First intervening transition.',
            expectedPreviousState: ModerationState::Active,
            expectedPreviousVersion: 0,
        );

        $this->assertSame(1, $target->refresh()->moderationStateVersion());

        $transition->handle(
            $actor,
            $target,
            ModerationState::Active,
            ModerationReason::Other,
            'Second intervening transition.',
            expectedPreviousState: ModerationState::Restricted,
            expectedPreviousVersion: 1,
        );

        $this->assertSame(2, $target->refresh()->moderationStateVersion());

        try {
            $transition->handle(
                $actor,
                $target,
                ModerationState::Banned,
                ModerationReason::SecurityAbuse,
                'The stale ABA transition must not apply.',
                expectedPreviousState: ModerationState::Active,
                expectedPreviousVersion: 0,
            );

            $this->fail('An ABA-stale transition should be rejected.');
        } catch (DomainException $exception) {
            $target->refresh();

            $this->assertSame(TransitionUserModerationState::STALE_STATE_ERROR_CODE, $exception->getCode());
            $this->assertSame(ModerationState::Active->value, $target->moderation_state);
            $this->assertSame(2, $target->moderationStateVersion());
            $this->assertDatabaseCount('moderation_events', 2);
            $this->assertDatabaseMissing('moderation_events', [
                'target_user_id' => $target->id,
                'reason_text' => 'The stale ABA transition must not apply.',
            ]);
        }
    }

    public function test_non_usable_actors_are_rejected(): void
    {
        $actors = [
            User::factory()->create(),
            User::factory()->administrator()->unverified()->create(),
            User::factory()->administrator()->create([
                'moderation_state' => ModerationState::Restricted->value,
            ]),
        ];

        foreach ($actors as $actor) {
            $target = User::factory()->create();

            $this->assertAuthorizationDenied(function () use ($actor, $target): void {
                $this->transition()->handle(
                    $actor,
                    $target,
                    ModerationState::Restricted,
                    ModerationReason::PolicyViolation,
                    'Unauthorized actor test.',
                );
            });
        }

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_invalid_state_actor_is_rejected(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $actor = $this->corruptModerationState($actor);

        $this->assertAuthorizationDenied(function () use ($actor, $target): void {
            $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Restricted,
                ModerationReason::SecurityAbuse,
                'Invalid actor state.',
            );
        });

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_invalid_target_state_is_rejected_without_silent_repair(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = $this->corruptModerationState(User::factory()->create());

        try {
            $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Active,
                ModerationReason::SecurityAbuse,
                'Invalid target state.',
            );
            $this->fail('An invalid stored state should be rejected.');
        } catch (DomainException) {
            $this->assertSame('invalid_state', $target->refresh()->moderation_state);
            $this->assertDatabaseCount('moderation_events', 0);
        }
    }

    public function test_last_usable_administrator_cannot_be_moved_from_active(): void
    {
        $administrator = User::factory()->administrator()->create();

        try {
            $this->transition()->handle(
                $administrator,
                $administrator,
                ModerationState::Suspended,
                ModerationReason::SecurityAbuse,
                'Lockout prevention test.',
            );
            $this->fail('The last usable administrator should be protected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('At least one usable administrator must remain.', $exception->getMessage());
            $this->assertTrue($administrator->refresh()->isUsableAdministrator());
            $this->assertDatabaseCount('moderation_events', 0);
        }
    }

    public function test_self_moderation_is_rejected_when_another_admin_exists(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();

        try {
            $this->transition()->handle(
                $administrator,
                $administrator,
                ModerationState::Restricted,
                ModerationReason::Other,
                'Self-moderation test.',
            );
            $this->fail('Self-moderation should be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(
                'Administrators may not moderate their own account.',
                $exception->getMessage(),
            );
            $this->assertTrue($administrator->refresh()->isUsableAdministrator());
            $this->assertDatabaseCount('moderation_events', 0);
        }
    }

    public function test_usable_administrator_can_be_moderated_when_another_remains(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->administrator()->create();

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::PolicyViolation,
            'Another administrator remains active.',
        );

        $this->assertTrue($actor->refresh()->isUsableAdministrator());
        $this->assertFalse($target->refresh()->isUsableAdministrator());
        $this->assertSame(UserRole::Administrator->value, $target->role);
    }

    public function test_audit_event_is_exact_and_transition_preserves_user_data(): void
    {
        Carbon::setTestNow('2026-09-12 12:30:00');

        try {
            $actor = User::factory()->administrator()->create();
            $target = User::factory()->create();
            $post = Post::query()->create([
                'user_id' => $target->id,
                'content' => 'Existing content remains visible.',
            ]);

            $event = $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Banned,
                ModerationReason::PlatformAbuse,
                '  Confirmed coordinated abuse.  ',
            );

            $this->assertSame($target->id, $event->target_user_id);
            $this->assertSame($actor->id, $event->actor_user_id);
            $this->assertSame(ModerationState::Active->value, $event->previous_state);
            $this->assertSame(ModerationState::Banned->value, $event->new_state);
            $this->assertSame(ModerationReason::PlatformAbuse->value, $event->reason_code);
            $this->assertSame('Confirmed coordinated abuse.', $event->reason_text);
            $this->assertSame('2026-09-12 12:30:00', $event->created_at->toDateTimeString());
            $this->assertSame(UserRole::User->value, $target->refresh()->role);
            $this->assertDatabaseHas('users', ['id' => $target->id]);
            $this->assertDatabaseHas('posts', ['id' => $post->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_entering_each_non_authenticating_state_increments_the_version_and_rotates_remember_token(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $initialToken = $target->remember_token;

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Suspended,
            ModerationReason::SecurityAbuse,
            'Suspend access.',
        );

        $target->refresh();
        $suspendedToken = $target->remember_token;
        $this->assertSame(1, $target->authSessionVersion());
        $this->assertNotSame($initialToken, $suspendedToken);

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Banned,
            ModerationReason::SecurityAbuse,
            'Escalate access revocation.',
        );

        $target->refresh();
        $this->assertSame(2, $target->authSessionVersion());
        $this->assertNotSame($suspendedToken, $target->remember_token);

        $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Active,
            ModerationReason::Other,
            'Manual reactivation.',
        );

        $this->assertSame(2, $target->refresh()->authSessionVersion());
    }

    public function test_state_and_audit_insert_roll_back_together(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $shouldFail = true;

        ModerationEvent::creating(static function () use (&$shouldFail): void {
            if ($shouldFail) {
                $shouldFail = false;

                throw new RuntimeException('Simulated audit insert failure.');
            }
        });

        try {
            $this->transition()->handle(
                $actor,
                $target,
                ModerationState::Suspended,
                ModerationReason::SecurityAbuse,
                'Atomic rollback test.',
            );
            $this->fail('The simulated audit failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit insert failure.', $exception->getMessage());
            $target->refresh();
            $this->assertSame(ModerationState::Active->value, $target->moderation_state);
            $this->assertSame(0, $target->moderationStateVersion());
            $this->assertSame(0, $target->authSessionVersion());
            $this->assertDatabaseCount('moderation_events', 0);
        }
    }

    public function test_moderation_events_reject_model_updates_and_deletes(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $event = $this->transition()->handle(
            $actor,
            $target,
            ModerationState::Restricted,
            ModerationReason::Spam,
            'Append-only test.',
        );

        try {
            $event->update(['reason_text' => 'Changed text.']);
            $this->fail('Moderation events must reject updates.');
        } catch (LogicException) {
            $this->assertDatabaseHas('moderation_events', [
                'id' => $event->id,
                'reason_text' => 'Append-only test.',
            ]);
        }

        try {
            $event->fresh()->delete();
            $this->fail('Moderation events must reject deletes.');
        } catch (LogicException) {
            $this->assertDatabaseHas('moderation_events', ['id' => $event->id]);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function approvedTransitions(): array
    {
        $transitions = [];

        foreach (ModerationState::cases() as $from) {
            foreach (ModerationState::cases() as $to) {
                if ($from === $to) {
                    continue;
                }

                $transitions[$from->value.' to '.$to->value] = [$from->value, $to->value];
            }
        }

        return $transitions;
    }

    private function transition(): TransitionUserModerationState
    {
        return app(TransitionUserModerationState::class);
    }

    private function corruptModerationState(User $user): User
    {
        DB::statement('PRAGMA ignore_check_constraints = ON');

        try {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['moderation_state' => 'invalid_state']);
        } finally {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }

        return $user->refresh();
    }

    private function assertAuthorizationDenied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('The transition should be denied.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
