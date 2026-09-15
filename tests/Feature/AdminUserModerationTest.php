<?php

namespace Tests\Feature;

use App\Actions\TransitionUserModerationState;
use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\ModerationEventsRelationManager;
use App\Models\ModerationEvent;
use App\Models\Post;
use App\Models\User;
use App\Policies\ModerationEventPolicy;
use App\Policies\UserPolicy;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Js;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUserModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function beforeRefreshingDatabase(): void
    {
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
    }

    #[DataProvider('moderationActionMatrix')]
    public function test_view_user_exposes_exactly_three_cross_state_actions(
        string $state,
        array $expectedActions,
    ): void {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create(['moderation_state' => $state]);

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $this->assertSame($expectedActions, $this->visibleHeaderActionNames($component));

        foreach ($component->instance()->getCachedHeaderActions() as $action) {
            $this->assertInstanceOf(Action::class, $action);
            $this->assertTrue($action->isAuthorized());
        }
    }

    #[DataProvider('normalBrowserModerationMounts')]
    public function test_normal_browser_header_action_payload_mounts_the_expected_modal(
        string $state,
        string $actionName,
    ): void {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create([
            'moderation_state' => $state,
            'moderation_state_version' => 7,
        ]);

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);
        $action = collect($component->instance()->getCachedHeaderActions())
            ->first(fn (Action $action): bool => $action->getName() === $actionName);
        $context = $this->browserModerationActionContext($target);

        $this->assertInstanceOf(Action::class, $action);
        $this->assertTrue($action->isVisible());
        $this->assertSame([], $action->getInvokedArguments() ?? []);
        $this->assertSame($context, $action->getContext());
        $this->assertSame(
            "mountAction('{$actionName}', {}, ".Js::from($context)->toHtml().')',
            $action->getLivewireClickHandler(),
        );

        $component
            ->call('mountAction', $actionName, [], $context)
            ->assertActionMounted($this->browserMountedModerationAction($target, $actionName))
            ->assertSet('trustedMountedModerationActionName', $actionName)
            ->assertSet('trustedMountedModerationStateVersion', 7);

        $mountedAction = $component->instance()->getMountedAction();

        $this->assertInstanceOf(Action::class, $mountedAction);
        $this->assertSame($actionName, $mountedAction->getName());
        $this->assertTrue($component->instance()->mountedActionShouldOpenModal($mountedAction));
    }

    public function test_invalid_target_state_fails_closed_without_moderation_actions(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = $this->corruptModerationState(User::factory()->create());

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $this->assertSame([], $this->visibleHeaderActionNames($component));
    }

    public function test_view_user_hides_all_moderation_actions_for_self(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $administrator->username]);
        $actions = collect($component->instance()->getCachedHeaderActions());

        $this->assertCount(3, $actions);
        $this->assertSame([], $this->visibleHeaderActionNames($component));
        $actions->each(function (Action $action): void {
            $this->assertFalse($action->isVisible());
        });
    }

    #[DataProvider('filamentTransitions')]
    public function test_all_twelve_transitions_complete_through_filament(
        string $source,
        string $destination,
        string $actionName,
    ): void {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create(['moderation_state' => $source]);
        $initialRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $component = $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            $actionName,
            $this->actionData($target, $source, $destination),
        );

        $component->assertHasNoActionErrors();

        $target->refresh();
        $event = ModerationEvent::query()->latest('id')->firstOrFail();

        $this->assertSame($destination, $target->moderation_state);
        $this->assertSame(1, $target->moderationStateVersion());
        $this->assertSame($target->id, $event->target_user_id);
        $this->assertSame($administrator->id, $event->actor_user_id);
        $this->assertSame($source, $event->previous_state);
        $this->assertSame($destination, $event->new_state);
        $this->assertSame(ModerationReason::PolicyViolation->value, $event->reason_code);
        $this->assertSame('Filament moderation reason.', $event->reason_text);
        $this->assertDatabaseCount('moderation_events', 1);

        if (in_array($destination, [ModerationState::Suspended->value, ModerationState::Banned->value], true)) {
            $this->assertSame(1, $target->authSessionVersion());
            $this->assertNotSame($initialRememberToken, $target->remember_token);
        } else {
            $this->assertSame(0, $target->authSessionVersion());
            $this->assertSame($initialRememberToken, $target->remember_token);
        }
    }

    public function test_successful_normal_transition_refreshes_the_infolist_in_the_same_request(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create(['moderation_state' => ModerationState::Active->value]);

        $this->actingAs($administrator);

        $component = $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            $this->reasonData(),
        );

        $component->assertHasNoActionErrors();

        $renderedInfolistText = strip_tags(
            $component->instance()->getSchema('infolist')->toEmbeddedHtml(),
        );

        $this->assertSame(
            ModerationState::Restricted,
            $component->instance()->getRecord()->moderationState(),
        );
        $this->assertStringContainsString(
            __('admin.moderation.states.restricted'),
            $renderedInfolistText,
        );
        $this->assertStringNotContainsString(
            __('admin.moderation.states.active'),
            $renderedInfolistText,
        );
        $this->assertSame(
            [
                'restricted_reactivate_active',
                'restricted_suspend',
                'restricted_ban',
            ],
            $this->visibleHeaderActionNames($component),
        );
        $component->assertActionNotMounted();

        $this->assertSame(ModerationState::Restricted->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 1);

        Notification::assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.moderation.notifications.success_title'))
                ->body(__('admin.moderation.notifications.success_body', [
                    'destination' => __('admin.moderation.states.restricted'),
                ])),
        );
    }

    public function test_moderation_modal_shows_target_context_consequences_and_required_reason_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create([
            'name' => 'Modal Target',
            'username' => 'modal_target',
            'email' => 'modal-target@example.com',
        ]);

        $this->actingAs($administrator);

        Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_suspend'))
            ->assertMountedActionModalSee([
                $target->name,
                '@'.$target->username,
                $target->email,
                (string) $target->id,
                __('admin.moderation.states.active'),
                __('admin.moderation.states.suspended'),
                __('admin.moderation.consequences.suspend'),
                __('admin.moderation.fields.reason_code'),
                __('admin.moderation.fields.reason_text'),
                __('admin.moderation.fields.suspend_acknowledgement'),
            ]);
    }

    public function test_suspend_requires_the_session_revocation_acknowledgement(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_suspend',
            $this->reasonData(),
        )
            ->assertHasActionErrors(['suspend_acknowledgement']);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_missing_reason_is_rejected_by_filament(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'))
            ->callMountedAction()
            ->assertHasActionErrors(['reason_code', 'reason_text']);

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_invalid_reason_code_is_rejected_by_filament(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            [
                'reason_code' => 'not-approved',
                'reason_text' => 'Invalid reason code test.',
            ],
        )
            ->assertHasActionErrors(['reason_code']);

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_blank_reason_text_is_rejected_by_filament(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            [
                'reason_code' => ModerationReason::Other->value,
                'reason_text' => '   ',
            ],
        )
            ->assertHasActionErrors(['reason_text']);

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_reason_text_over_two_thousand_characters_is_rejected_by_filament(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            [
                'reason_code' => ModerationReason::Other->value,
                'reason_text' => str_repeat('x', 2001),
            ],
        )
            ->assertHasActionErrors(['reason_text']);

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_ban_requires_the_current_password(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'username_confirmation' => $target->username,
            ],
        )
            ->assertHasActionErrors(['current_password']);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_ban_rejects_a_wrong_current_password(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'wrong-password',
                'username_confirmation' => $target->username,
            ],
        )
            ->assertHasActionErrors(['current_password']);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_ban_rejects_a_wrong_typed_username(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'password',
                'username_confirmation' => 'different_username',
            ],
        )
            ->assertHasActionErrors(['username_confirmation']);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_ban_with_the_current_password_and_exact_username_succeeds_without_persisting_password_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'password',
                'username_confirmation' => $target->username,
            ],
        )
            ->assertHasNoActionErrors();

        $event = ModerationEvent::query()->firstOrFail();

        $this->assertSame(ModerationState::Banned->value, $target->refresh()->moderation_state);
        $this->assertSame(ModerationReason::PolicyViolation->value, $event->reason_code);
        $this->assertSame('Filament moderation reason.', $event->reason_text);
        $this->assertArrayNotHasKey('current_password', $event->getAttributes());
        $this->assertArrayNotHasKey('username_confirmation', $event->getAttributes());
        $this->assertStringNotContainsString('password', json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_ban_password_attempts_are_limited_to_five_per_minute_by_actor(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $expectedVersion = $target->moderationStateVersion();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $rateLimitKey = $this->banPasswordRateLimitKey($administrator);
        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);
        $banAction = collect($component->instance()->getCachedHeaderActions())
            ->first(fn (Action $action): bool => $action->getName() === 'active_ban');

        $this->assertInstanceOf(Action::class, $banAction);
        $this->assertNull($banAction->getRateLimit());

        $component = $this->mountModerationAction(
            $component,
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'wrong-password',
                'username_confirmation' => $target->username,
            ],
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $component
                ->callMountedAction()
                ->assertHasActionErrors(['current_password']);

            $this->assertSame($attempt, RateLimiter::attempts($rateLimitKey));
        }

        Hash::shouldReceive('check')->never();

        $component
            ->callMountedAction()
            ->assertHasActionErrors(['current_password']);

        $availableIn = RateLimiter::availableIn($rateLimitKey);

        $this->assertSame(6, RateLimiter::attempts($rateLimitKey));
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedVersion, $target->moderationStateVersion());
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.rate_limited_title'))
                ->body(__('admin.moderation.notifications.rate_limited_body')),
        );
    }

    public function test_client_mounted_action_metadata_cannot_reset_the_ban_password_limiter(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $expectedVersion = $target->moderationStateVersion();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $rateLimitKey = $this->banPasswordRateLimitKey($administrator);
        $component = Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_ban'));

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->submitRawMountedActionRequest($component, [
                'mountedActions.0.nonce' => (string) $attempt,
                'mountedActions.0.data.reason_code' => ModerationReason::PolicyViolation->value,
                'mountedActions.0.data.reason_text' => 'Metadata bypass regression.',
                'mountedActions.0.data.current_password' => 'wrong-password',
                'mountedActions.0.data.username_confirmation' => $target->username,
            ])->assertHasActionErrors(['current_password']);

            $this->assertSame($attempt, RateLimiter::attempts($rateLimitKey));
        }

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedVersion, $target->moderationStateVersion());
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.rate_limited_title'))
                ->body(__('admin.moderation.notifications.rate_limited_body')),
        );
    }

    #[DataProvider('crossTargetBanActions')]
    public function test_ban_password_limit_is_shared_across_targets_and_ban_variants(
        string $targetState,
        string $targetAction,
    ): void {
        $administrator = User::factory()->administrator()->create();
        $targetA = User::factory()->create();
        $targetB = User::factory()->create(['moderation_state' => $targetState]);
        $targetAExpectedVersion = $targetA->moderationStateVersion();
        $targetBExpectedVersion = $targetB->moderationStateVersion();
        $targetAExpectedAuthSessionVersion = $targetA->authSessionVersion();
        $targetBExpectedAuthSessionVersion = $targetB->authSessionVersion();
        $targetAExpectedRememberToken = $targetA->remember_token;
        $targetBExpectedRememberToken = $targetB->remember_token;

        $this->actingAs($administrator);

        $rateLimitKey = $this->banPasswordRateLimitKey($administrator);
        $targetAComponent = $this->mountModerationAction(
            Livewire::test(ViewUser::class, ['record' => $targetA->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'wrong-password',
                'username_confirmation' => $targetA->username,
            ],
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $targetAComponent
                ->callMountedAction()
                ->assertHasActionErrors(['current_password']);
        }

        $this->assertSame(5, RateLimiter::attempts($rateLimitKey));

        $targetBComponent = $this->mountModerationAction(
            Livewire::test(ViewUser::class, ['record' => $targetB->username]),
            $targetAction,
            $this->actionData($targetB, $targetState, ModerationState::Banned->value),
        );

        Hash::shouldReceive('check')->never();

        $targetBComponent
            ->callMountedAction()
            ->assertHasActionErrors(['current_password']);

        $this->assertSame(6, RateLimiter::attempts($rateLimitKey));

        $targetA->refresh();
        $targetB->refresh();

        $this->assertSame(ModerationState::Active->value, $targetA->moderation_state);
        $this->assertSame($targetState, $targetB->moderation_state);
        $this->assertSame($targetAExpectedVersion, $targetA->moderationStateVersion());
        $this->assertSame($targetBExpectedVersion, $targetB->moderationStateVersion());
        $this->assertSame($targetAExpectedAuthSessionVersion, $targetA->authSessionVersion());
        $this->assertSame($targetBExpectedAuthSessionVersion, $targetB->authSessionVersion());
        $this->assertSame($targetAExpectedRememberToken, $targetA->remember_token);
        $this->assertSame($targetBExpectedRememberToken, $targetB->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.rate_limited_title'))
                ->body(__('admin.moderation.notifications.rate_limited_body')),
        );
    }

    public function test_correct_ban_password_remains_blocked_until_the_actor_limiter_is_cleared(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $expectedVersion = $target->moderationStateVersion();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $rateLimitKey = $this->banPasswordRateLimitKey($administrator);
        $component = $this->mountModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_ban',
            [
                ...$this->reasonData(),
                'current_password' => 'wrong-password',
                'username_confirmation' => $target->username,
            ],
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $component
                ->callMountedAction()
                ->assertHasActionErrors(['current_password']);
        }

        $component
            ->set('mountedActions.0.data.current_password', 'password')
            ->callMountedAction()
            ->assertHasActionErrors(['current_password']);

        $target->refresh();

        $this->assertSame(6, RateLimiter::attempts($rateLimitKey));
        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedVersion, $target->moderationStateVersion());
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.rate_limited_title'))
                ->body(__('admin.moderation.notifications.rate_limited_body')),
        );

        RateLimiter::clear($rateLimitKey);

        $component
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $target->refresh();

        $this->assertSame(1, RateLimiter::attempts($rateLimitKey));
        $this->assertSame(ModerationState::Banned->value, $target->moderation_state);
        $this->assertSame($expectedVersion + 1, $target->moderationStateVersion());
        $this->assertSame($expectedAuthSessionVersion + 1, $target->authSessionVersion());
        $this->assertNotSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 1);
        $this->assertDatabaseHas('moderation_events', [
            'target_user_id' => $target->id,
            'actor_user_id' => $administrator->id,
            'new_state' => ModerationState::Banned->value,
        ]);
    }

    public function test_target_source_destination_and_kind_cannot_be_controlled_by_action_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            [
                ...$this->reasonData(),
                'target_user_id' => $otherTarget->id,
                'source_state' => ModerationState::Suspended->value,
                'destination_state' => ModerationState::Banned->value,
                'action_kind' => 'ban',
            ],
        )
            ->assertHasNoActionErrors();

        $this->assertSame(ModerationState::Restricted->value, $target->refresh()->moderation_state);
        $this->assertSame(ModerationState::Active->value, $otherTarget->refresh()->moderation_state);
        $this->assertDatabaseHas('moderation_events', [
            'target_user_id' => $target->id,
            'new_state' => ModerationState::Restricted->value,
        ]);
    }

    public function test_self_moderation_is_hidden_and_denied_by_explicit_moderate_authorization(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $administrator->username]);
        $action = collect($component->instance()->getCachedHeaderActions())
            ->first(fn (Action $action): bool => $action->getName() === 'active_restrict');

        $this->assertInstanceOf(Action::class, $action);
        $this->assertFalse($action->isVisible());
        $this->assertFalse($action->isAuthorized());

        $component
            ->call('mountAction', 'active_restrict');

        $this->assertNull($component->instance()->getMountedAction());

        $this->assertSame(ModerationState::Active->value, $administrator->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_non_usable_administrators_see_no_moderation_actions(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $actors = [
            'ordinary' => User::factory()->create(),
            'unverified administrator' => User::factory()->administrator()->unverified()->create(),
            'restricted administrator' => User::factory()->administrator()->create([
                'moderation_state' => ModerationState::Restricted->value,
            ]),
            'suspended administrator' => User::factory()->administrator()->create([
                'moderation_state' => ModerationState::Suspended->value,
            ]),
            'banned administrator' => User::factory()->administrator()->create([
                'moderation_state' => ModerationState::Banned->value,
            ]),
        ];

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        foreach ($actors as $label => $actor) {
            $this->actingAs($actor);

            collect($component->instance()->getCachedHeaderActions())
                ->each(function (Action $action): void {
                    $action->clearVisibilityCache();
                });

            $this->assertSame([], $this->visibleHeaderActionNames($component), $label);
        }
    }

    public function test_crafted_livewire_action_invocation_is_denied_when_the_actor_is_no_longer_usable(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $ordinaryUser = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'));

        $this->actingAs($ordinaryUser);

        $action = $component->instance()->getMountedAction();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertFalse($action->isAuthorized());

        $component->instance()->callMountedAction();

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_moderate_policy_requires_a_usable_administrator_and_recognized_target(): void
    {
        $target = User::factory()->create();
        $active = User::factory()->administrator()->create();
        $ordinary = User::factory()->create();
        $unverified = User::factory()->administrator()->unverified()->create();
        $restricted = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $suspended = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);
        $banned = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Banned->value,
        ]);
        $invalid = $this->corruptModerationState(
            User::factory()->administrator()->create(),
        );
        $invalidTarget = $this->corruptModerationState(User::factory()->create());
        $policy = app(UserPolicy::class);

        $this->assertTrue($policy->moderate($active, $target));
        $this->assertFalse($policy->moderate($active, $active));
        $this->assertFalse($policy->moderate($active, $invalidTarget));

        foreach ([$ordinary, $unverified, $restricted, $suspended, $banned, $invalid] as $actor) {
            $this->assertFalse($policy->moderate($actor, $target));
        }
    }

    public function test_arbitrary_forged_action_name_cannot_execute(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $this->submitRawMountedActionRequest($component, [
            'mountedActions' => [[
                'name' => 'forged_delete_user',
                'arguments' => [],
                'context' => $this->browserModerationActionContext($target),
                'data' => $this->reasonData(),
            ]],
        ]);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_valid_moderation_action_that_was_never_mounted_cannot_execute(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $this->submitRawMountedActionRequest($component, [
            'mountedActions' => [[
                'name' => 'active_suspend',
                'arguments' => [],
                'context' => $this->browserModerationActionContext($target),
                'data' => [
                    ...$this->reasonData(),
                    'suspend_acknowledgement' => true,
                ],
            ]],
        ]);

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null);
    }

    public function test_forged_action_shell_cannot_gain_trust_from_a_same_request_mount_call(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $component->update(
            calls: [
                [
                    'method' => 'mountAction',
                    'params' => [
                        'active_suspend',
                        [],
                        $this->browserModerationActionContext($target),
                    ],
                    'path' => '',
                ],
                [
                    'method' => 'callMountedAction',
                    'params' => [],
                    'path' => '',
                ],
            ],
            updates: [
                'mountedActions' => [[
                    'name' => 'active_suspend',
                    'arguments' => [],
                    'context' => $this->browserModerationActionContext($target),
                    'data' => [
                        ...$this->reasonData(),
                        'suspend_acknowledgement' => true,
                    ],
                ]],
            ],
        );

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertDatabaseCount('moderation_events', 0);
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null);
    }

    public function test_another_source_state_action_cannot_be_mounted_or_injected(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $component
            ->call(
                'mountAction',
                'suspended_ban',
                [],
                $this->browserModerationActionContext($target),
            )
            ->assertActionNotMounted();

        $this->submitRawMountedActionRequest($component, [
            'mountedActions' => [[
                'name' => 'suspended_ban',
                'arguments' => [],
                'context' => $this->browserModerationActionContext($target),
                'data' => $this->reasonData(),
            ]],
        ]);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null);
    }

    public function test_current_request_cannot_change_the_trusted_mounted_action_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'))
            ->assertSet('trustedMountedModerationActionName', 'active_restrict');

        $this->submitRawMountedActionRequest($component, [
            'mountedActions.0.name' => 'active_suspend',
            'mountedActions.0.data.reason_code' => ModerationReason::PolicyViolation->value,
            'mountedActions.0.data.reason_text' => 'Forged action name.',
            'mountedActions.0.data.suspend_acknowledgement' => true,
        ]);

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null);
    }

    public function test_current_request_cannot_create_the_locked_trusted_mount_marker(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        try {
            $component->update(
                calls: [[
                    'method' => 'callMountedAction',
                    'params' => [],
                    'path' => '',
                ]],
                updates: [
                    'trustedMountedModerationActionName' => 'active_restrict',
                    'mountedActions' => [[
                        'name' => 'active_restrict',
                        'arguments' => [],
                        'context' => $this->browserModerationActionContext($target),
                        'data' => $this->reasonData(),
                    ]],
                ],
            );

            $this->fail('The trusted mounted moderation action marker was not locked.');
        } catch (CannotUpdateLockedPropertyException $exception) {
            $this->assertSame('trustedMountedModerationActionName', $exception->property);
        }

        $this->assertSame(ModerationState::Active->value, $target->refresh()->moderation_state);
        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_client_cannot_overwrite_the_locked_trusted_moderation_state_version(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create(['moderation_state_version' => 4]);

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username])
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'))
            ->assertSet('trustedMountedModerationActionName', 'active_restrict')
            ->assertSet('trustedMountedModerationStateVersion', 4);

        try {
            $component->update(
                calls: [[
                    'method' => 'callMountedAction',
                    'params' => [],
                    'path' => '',
                ]],
                updates: [
                    'trustedMountedModerationStateVersion' => 3,
                    'mountedActions.0.data.reason_code' => ModerationReason::PolicyViolation->value,
                    'mountedActions.0.data.reason_text' => 'Forged moderation version.',
                ],
            );

            $this->fail('The trusted moderation state version was not locked.');
        } catch (CannotUpdateLockedPropertyException $exception) {
            $this->assertSame('trustedMountedModerationStateVersion', $exception->property);
        }

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame(4, $target->moderationStateVersion());
        $this->assertDatabaseCount('moderation_events', 0);
    }

    #[DataProvider('staleModerationLocales')]
    public function test_stale_filament_submission_is_rejected_with_safe_localized_feedback_and_refreshes_state(
        string $locale,
        string $expectedTitle,
        string $expectedBody,
    ): void {
        app()->setLocale($locale);

        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $component
            ->assertActionVisible('active_restrict')
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'))
            ->assertSet('trustedMountedModerationActionName', 'active_restrict')
            ->assertSet('trustedMountedModerationStateVersion', 0);

        app(TransitionUserModerationState::class)->handle(
            $administrator,
            $target,
            ModerationState::Suspended,
            ModerationReason::PolicyViolation,
            'HASIL TAB B',
            expectedPreviousState: ModerationState::Active,
            expectedPreviousVersion: 0,
        );

        $target->refresh();
        $expectedAuthSessionVersion = $target->authSessionVersion();
        $expectedRememberToken = $target->remember_token;

        $this->submitRawMountedActionRequest($component, [
            'mountedActions.0.data.reason_code' => ModerationReason::PolicyViolation->value,
            'mountedActions.0.data.reason_text' => 'HASIL TAB A',
        ]);

        $target->refresh();

        $this->assertSame(ModerationState::Suspended->value, $target->moderation_state);
        $this->assertSame(1, $target->moderationStateVersion());
        $this->assertSame($expectedAuthSessionVersion, $target->authSessionVersion());
        $this->assertSame($expectedRememberToken, $target->remember_token);
        $this->assertAuthenticatedAs($administrator, 'web');
        $this->assertSame(ModerationState::Suspended, $component->instance()->getRecord()->moderationState());
        $this->assertSame(
            [
                'suspended_reactivate_active',
                'suspended_restrict',
                'suspended_ban',
            ],
            $this->visibleHeaderActionNames($component),
        );
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null)
            ->assertSet('trustedMountedModerationStateVersion', null)
            ->assertDispatched('sync-action-modals');
        $this->assertNull($component->instance()->getMountedAction());
        $this->assertSame([], $component->instance()->getMountedActions());
        $this->assertDatabaseCount('moderation_events', 1);
        $this->assertDatabaseHas('moderation_events', [
            'target_user_id' => $target->id,
            'new_state' => ModerationState::Suspended->value,
            'reason_text' => 'HASIL TAB B',
        ]);
        $this->assertDatabaseMissing('moderation_events', [
            'target_user_id' => $target->id,
            'reason_text' => 'HASIL TAB A',
        ]);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title($expectedTitle)
                ->body($expectedBody),
        );
        $this->assertFalse(session()->has('filament.notifications'));
        $this->assertFalse(session()->has('filament.claimed_notifications'));
    }

    public function test_stale_filament_submission_rebinds_cached_infolist_in_the_same_response(): void
    {
        app()->setLocale('en');

        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create([
            'name' => 'Cached Infolist Target',
            'moderation_state' => ModerationState::Active->value,
        ]);
        $transition = app(TransitionUserModerationState::class);

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $component
            ->assertActionVisible('active_restrict')
            ->mountAction($this->browserMountedModerationAction($target, 'active_restrict'))
            ->assertSet('trustedMountedModerationActionName', 'active_restrict')
            ->assertSet('trustedMountedModerationStateVersion', 0);

        $racingTransition = new class($transition) extends TransitionUserModerationState
        {
            public ?ModerationState $cachedInfolistRecordState = null;

            public string $cachedInfolistText = '';

            public function __construct(
                private readonly TransitionUserModerationState $transition,
            ) {}

            public function handle(
                User $actor,
                User $target,
                ModerationState $newState,
                ModerationReason $reasonCode,
                string $reasonText,
                ?ModerationState $expectedPreviousState = null,
                ?int $expectedPreviousVersion = null,
            ): ModerationEvent {
                $component = app('livewire')->current();

                if ($component instanceof ViewUser) {
                    $infolist = $component->getSchema('infolist');
                    $infolistRecord = $infolist?->getRecord();

                    if ($infolistRecord instanceof User) {
                        $this->cachedInfolistRecordState = $infolistRecord->moderationState();
                    }

                    $this->cachedInfolistText = strip_tags($infolist?->toEmbeddedHtml() ?? '');
                }

                $this->transition->handle(
                    $actor,
                    $target,
                    ModerationState::Suspended,
                    ModerationReason::PolicyViolation,
                    'INTERVENING INFOLIST TRANSITION',
                    expectedPreviousState: ModerationState::Active,
                    expectedPreviousVersion: 0,
                );

                return $this->transition->handle(
                    $actor,
                    $target,
                    $newState,
                    $reasonCode,
                    $reasonText,
                    expectedPreviousState: $expectedPreviousState,
                    expectedPreviousVersion: $expectedPreviousVersion,
                );
            }
        };

        app()->instance(TransitionUserModerationState::class, $racingTransition);

        $this->submitRawMountedActionRequest($component, [
            'mountedActions.0.data.reason_code' => ModerationReason::PolicyViolation->value,
            'mountedActions.0.data.reason_text' => 'STALE INFOLIST SUBMISSION',
        ]);

        $target->refresh();
        $renderedInfolistText = strip_tags(
            $component->instance()->getSchema('infolist')->toEmbeddedHtml(),
        );

        $this->assertSame(ModerationState::Active, $racingTransition->cachedInfolistRecordState);
        $this->assertStringContainsString(
            __('admin.moderation.states.active'),
            $racingTransition->cachedInfolistText,
        );
        $this->assertStringNotContainsString(
            __('admin.moderation.states.suspended'),
            $racingTransition->cachedInfolistText,
        );
        $this->assertSame(ModerationState::Suspended->value, $target->moderation_state);
        $this->assertSame(1, $target->moderationStateVersion());
        $this->assertSame(
            ModerationState::Suspended,
            $component->instance()->getRecord()->moderationState(),
        );
        $this->assertStringContainsString(
            __('admin.moderation.states.suspended'),
            $renderedInfolistText,
        );
        $this->assertStringNotContainsString(
            __('admin.moderation.states.active'),
            $renderedInfolistText,
        );
        $this->assertSame(
            [
                'suspended_reactivate_active',
                'suspended_restrict',
                'suspended_ban',
            ],
            $this->visibleHeaderActionNames($component),
        );
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null)
            ->assertSet('trustedMountedModerationStateVersion', null)
            ->assertDispatched('sync-action-modals');
        $this->assertNull($component->instance()->getMountedAction());
        $this->assertSame([], $component->instance()->getMountedActions());
        $this->assertDatabaseCount('moderation_events', 1);
        $this->assertDatabaseHas('moderation_events', [
            'target_user_id' => $target->id,
            'new_state' => ModerationState::Suspended->value,
            'reason_text' => 'INTERVENING INFOLIST TRANSITION',
        ]);
        $this->assertDatabaseMissing('moderation_events', [
            'target_user_id' => $target->id,
            'reason_text' => 'STALE INFOLIST SUBMISSION',
        ]);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.stale_title'))
                ->body(__('admin.moderation.notifications.stale_body')),
        );
    }

    public function test_aba_stale_filament_submission_is_rejected_through_livewire_rehydration(): void
    {
        app()->setLocale('en');

        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $transition = app(TransitionUserModerationState::class);

        $this->actingAs($administrator);

        $component = Livewire::test(ViewUser::class, ['record' => $target->username]);

        $component
            ->assertActionVisible('active_ban')
            ->mountAction($this->browserMountedModerationAction($target, 'active_ban'))
            ->assertSet('trustedMountedModerationActionName', 'active_ban')
            ->assertSet('trustedMountedModerationStateVersion', 0);

        $transition->handle(
            $administrator,
            $target,
            ModerationState::Restricted,
            ModerationReason::PolicyViolation,
            'ABA transition one.',
            expectedPreviousState: ModerationState::Active,
            expectedPreviousVersion: 0,
        );

        $this->assertSame(1, $target->refresh()->moderationStateVersion());

        $transition->handle(
            $administrator,
            $target,
            ModerationState::Active,
            ModerationReason::Other,
            'ABA transition two.',
            expectedPreviousState: ModerationState::Restricted,
            expectedPreviousVersion: 1,
        );

        $target->refresh();
        $expectedVersion = $target->moderationStateVersion();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame(2, $expectedVersion);

        $this->submitRawMountedActionRequest($component, [
            'mountedActions.0.data.reason_code' => ModerationReason::SecurityAbuse->value,
            'mountedActions.0.data.reason_text' => 'STALE ABA TAB A',
            'mountedActions.0.data.current_password' => 'password',
            'mountedActions.0.data.username_confirmation' => $target->username,
        ]);

        $target->refresh();

        $this->assertSame(ModerationState::Active->value, $target->moderation_state);
        $this->assertSame($expectedVersion, $target->moderationStateVersion());
        $this->assertSame(ModerationState::Active, $component->instance()->getRecord()->moderationState());
        $this->assertSame(
            [
                'active_restrict',
                'active_suspend',
                'active_ban',
            ],
            $this->visibleHeaderActionNames($component),
        );
        $component
            ->assertActionNotMounted()
            ->assertSet('trustedMountedModerationActionName', null)
            ->assertSet('trustedMountedModerationStateVersion', null)
            ->assertDispatched('sync-action-modals');
        $this->assertNull($component->instance()->getMountedAction());
        $this->assertSame([], $component->instance()->getMountedActions());
        $this->assertDatabaseCount('moderation_events', 2);
        $this->assertDatabaseMissing('moderation_events', [
            'target_user_id' => $target->id,
            'reason_text' => 'STALE ABA TAB A',
        ]);

        Notification::assertNotified(
            Notification::make()
                ->danger()
                ->title(__('admin.moderation.notifications.stale_title'))
                ->body(__('admin.moderation.notifications.stale_body')),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function staleModerationLocales(): array
    {
        return [
            'english' => [
                'en',
                'Account state changed',
                'The account changed before this request was applied. The page has been refreshed; review the available actions and try again.',
            ],
            'indonesian' => [
                'id',
                'Status akun berubah',
                'Akun berubah sebelum permintaan ini diterapkan. Halaman telah diperbarui; tinjau tindakan yang tersedia dan coba lagi.',
            ],
        ];
    }

    public function test_successful_moderation_preserves_role_account_and_content(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create([
            'name' => 'Preserved Target',
            'email' => 'preserved-target@example.com',
            'bio' => 'Content remains intact.',
            'is_verified' => true,
        ]);
        $post = Post::query()->create([
            'user_id' => $target->id,
            'content' => 'Existing content remains visible.',
        ]);

        $this->actingAs($administrator);

        $this->callModerationAction(
            Livewire::test(ViewUser::class, ['record' => $target->username]),
            'active_restrict',
            $this->reasonData(),
        )
            ->assertHasNoActionErrors();

        $target->refresh();

        $this->assertSame(UserRole::User->value, $target->role);
        $this->assertSame('Preserved Target', $target->name);
        $this->assertSame('preserved-target@example.com', $target->email);
        $this->assertSame('Content remains intact.', $target->bio);
        $this->assertTrue((bool) $target->is_verified);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $target->id]);
    }

    public function test_history_is_target_scoped_newest_first_read_only_and_eager_loads_safe_actor_columns(): void
    {
        Carbon::setTestNow('2026-09-13 10:00:00');

        try {
            $administrator = User::factory()->administrator()->create();
            $target = User::factory()->create();
            $otherTarget = User::factory()->create();
            $transition = app(TransitionUserModerationState::class);

            $oldest = $transition->handle(
                $administrator,
                $target,
                ModerationState::Restricted,
                ModerationReason::Spam,
                'Oldest history event.',
            );

            Carbon::setTestNow('2026-09-13 10:01:00');

            $newest = $transition->handle(
                $administrator,
                $target,
                ModerationState::Active,
                ModerationReason::PolicyViolation,
                'Newest <script>alert(1)</script>.',
            );

            Carbon::setTestNow('2026-09-13 10:02:00');

            $otherEvent = $transition->handle(
                $administrator,
                $otherTarget,
                ModerationState::Restricted,
                ModerationReason::Other,
                'Other target event.',
            );
        } finally {
            Carbon::setTestNow();
        }

        $this->actingAs($administrator);

        $component = Livewire::test(ModerationEventsRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => ViewUser::class,
        ]);

        $component
            ->assertCanSeeTableRecords([$newest, $oldest])
            ->assertCanNotSeeTableRecords([$otherEvent])
            ->assertTableActionsExistInOrder([])
            ->assertTableHeaderActionsExistInOrder([])
            ->assertTableBulkActionsExistInOrder([])
            ->assertSee(__('admin.moderation.reasons.policy_violation'))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $records = $component->instance()->getTableRecords();

        $this->assertSame([$newest->id, $oldest->id], $records->pluck('id')->all());
        $this->assertSame(10, $component->instance()->getTableRecordsPerPage());
        $this->assertSame([10, 25, 50], $component->instance()->getTable()->getPaginationPageOptions());
        $this->assertTrue($records->first()->relationLoaded('actorUser'));
        $this->assertSame(
            ['id', 'name', 'username'],
            array_keys($records->first()->actorUser->getAttributes()),
        );
    }

    public function test_moderation_event_policy_allows_only_usable_administrators_to_view_and_denies_mutations(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $event = app(TransitionUserModerationState::class)->handle(
            $administrator,
            $target,
            ModerationState::Restricted,
            ModerationReason::Other,
            'Policy test event.',
        );
        $policy = app(ModerationEventPolicy::class);

        $this->assertTrue($policy->viewAny($administrator));
        $this->assertTrue($policy->view($administrator, $event));

        foreach ([
            User::factory()->create(),
            User::factory()->administrator()->unverified()->create(),
            User::factory()->administrator()->create([
                'moderation_state' => ModerationState::Restricted->value,
            ]),
        ] as $actor) {
            $this->assertFalse($policy->viewAny($actor));
            $this->assertFalse($policy->view($actor, $event));
        }

        $this->assertFalse($policy->create($administrator));
        $this->assertFalse($policy->update($administrator, $event));
        $this->assertFalse($policy->delete($administrator, $event));
        $this->assertFalse($policy->deleteAny($administrator));
        $this->assertFalse($policy->restore($administrator, $event));
        $this->assertFalse($policy->restoreAny($administrator));
        $this->assertFalse($policy->forceDelete($administrator, $event));
        $this->assertFalse($policy->forceDeleteAny($administrator));
        $this->assertFalse($policy->replicate($administrator, $event));
        $this->assertFalse($policy->reorder($administrator));
        $this->assertFalse($policy->associate($administrator, $event));
        $this->assertFalse($policy->dissociate($administrator, $event));
        $this->assertFalse($policy->attach($administrator, $event));
        $this->assertFalse($policy->detach($administrator, $event));
    }

    public function test_admin_moderation_copy_is_available_in_english_and_indonesian(): void
    {
        app()->setLocale('en');
        $this->assertSame('Moderation history', __('admin.moderation.history.title'));
        $this->assertSame('Policy violation', __('admin.moderation.reasons.policy_violation'));

        app()->setLocale('id');
        $this->assertSame('Riwayat moderasi', __('admin.moderation.history.title'));
        $this->assertSame('Pelanggaran kebijakan', __('admin.moderation.reasons.policy_violation'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function crossTargetBanActions(): array
    {
        return [
            'restricted target' => [ModerationState::Restricted->value, 'restricted_ban'],
            'suspended target' => [ModerationState::Suspended->value, 'suspended_ban'],
        ];
    }

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function moderationActionMatrix(): array
    {
        return [
            'active' => [
                ModerationState::Active->value,
                ['active_restrict', 'active_suspend', 'active_ban'],
            ],
            'restricted' => [
                ModerationState::Restricted->value,
                ['restricted_reactivate_active', 'restricted_suspend', 'restricted_ban'],
            ],
            'suspended' => [
                ModerationState::Suspended->value,
                ['suspended_reactivate_active', 'suspended_restrict', 'suspended_ban'],
            ],
            'banned' => [
                ModerationState::Banned->value,
                ['banned_unban_active', 'banned_unban_restricted', 'banned_suspend'],
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalBrowserModerationMounts(): array
    {
        return [
            'active action' => [ModerationState::Active->value, 'active_restrict'],
            'restricted action' => [ModerationState::Restricted->value, 'restricted_suspend'],
            'banned action' => [ModerationState::Banned->value, 'banned_unban_active'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function filamentTransitions(): array
    {
        return [
            'active to restricted' => [ModerationState::Active->value, ModerationState::Restricted->value, 'active_restrict'],
            'active to suspended' => [ModerationState::Active->value, ModerationState::Suspended->value, 'active_suspend'],
            'active to banned' => [ModerationState::Active->value, ModerationState::Banned->value, 'active_ban'],
            'restricted to active' => [ModerationState::Restricted->value, ModerationState::Active->value, 'restricted_reactivate_active'],
            'restricted to suspended' => [ModerationState::Restricted->value, ModerationState::Suspended->value, 'restricted_suspend'],
            'restricted to banned' => [ModerationState::Restricted->value, ModerationState::Banned->value, 'restricted_ban'],
            'suspended to active' => [ModerationState::Suspended->value, ModerationState::Active->value, 'suspended_reactivate_active'],
            'suspended to restricted' => [ModerationState::Suspended->value, ModerationState::Restricted->value, 'suspended_restrict'],
            'suspended to banned' => [ModerationState::Suspended->value, ModerationState::Banned->value, 'suspended_ban'],
            'banned to active' => [ModerationState::Banned->value, ModerationState::Active->value, 'banned_unban_active'],
            'banned to restricted' => [ModerationState::Banned->value, ModerationState::Restricted->value, 'banned_unban_restricted'],
            'banned to suspended' => [ModerationState::Banned->value, ModerationState::Suspended->value, 'banned_suspend'],
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    private function actionData(User $target, string $source, string $destination): array
    {
        $data = [
            ...$this->reasonData(),
        ];

        if ($destination === ModerationState::Suspended->value && $source !== ModerationState::Banned->value) {
            $data['suspend_acknowledgement'] = true;
        }

        if ($destination === ModerationState::Banned->value) {
            $data['current_password'] = 'password';
            $data['username_confirmation'] = $target->username;
        }

        return $data;
    }

    /**
     * @return array{reason_code: string, reason_text: string}
     */
    private function reasonData(): array
    {
        return [
            'reason_code' => ModerationReason::PolicyViolation->value,
            'reason_text' => 'Filament moderation reason.',
        ];
    }

    /**
     * @return array{recordKey: string}
     */
    private function browserModerationActionContext(User $target): array
    {
        return ['recordKey' => (string) $target->getKey()];
    }

    /**
     * @return array{name: string, arguments: array<never, never>, context: array{recordKey: string}}
     */
    private function browserMountedModerationAction(User $target, string $actionName): array
    {
        return [
            'name' => $actionName,
            'arguments' => [],
            'context' => $this->browserModerationActionContext($target),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function callModerationAction(Testable $component, string $actionName, array $data): Testable
    {
        return $this->mountModerationAction($component, $actionName, $data)
            ->callMountedAction();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mountModerationAction(Testable $component, string $actionName, array $data): Testable
    {
        /** @var User $target */
        $target = $component->instance()->getRecord();

        $component
            ->assertActionVisible($actionName)
            ->mountAction($this->browserMountedModerationAction($target, $actionName));

        foreach ($data as $key => $value) {
            $component->set("mountedActions.0.data.{$key}", $value);
        }

        return $component;
    }

    private function banPasswordRateLimitKey(User $actor): string
    {
        return 'skuysocial:admin-moderation:ban:actor:'.$actor->getKey();
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function submitRawMountedActionRequest(Testable $component, array $updates): Testable
    {
        return $component->update(
            calls: [[
                'method' => 'callMountedAction',
                'params' => [],
                'path' => '',
            ]],
            updates: $updates,
        );
    }

    /**
     * @param  Testable  $component
     * @return array<int, string>
     */
    private function visibleHeaderActionNames(mixed $component): array
    {
        return collect($component->instance()->getCachedHeaderActions())
            ->filter(fn (Action $action): bool => $action->isVisible())
            ->map(fn (Action $action): string => $action->getName())
            ->values()
            ->all();
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
}
