<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\TransitionUserModerationState;
use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use LogicException;
use OverflowException;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    private const int BAN_PASSWORD_RATE_LIMIT_MAX_ATTEMPTS = 5;

    private const int BAN_PASSWORD_RATE_LIMIT_DECAY_SECONDS = 60;

    private const string BAN_PASSWORD_RATE_LIMIT_KEY_PREFIX = 'skuysocial:admin-moderation:ban:actor:';

    /**
     * @var array<string, array{source: ModerationState, destination: ModerationState, kind: string}>
     */
    private const array MODERATION_ACTION_DEFINITIONS = [
        'active_restrict' => [
            'source' => ModerationState::Active,
            'destination' => ModerationState::Restricted,
            'kind' => 'restrict',
        ],
        'active_suspend' => [
            'source' => ModerationState::Active,
            'destination' => ModerationState::Suspended,
            'kind' => 'suspend',
        ],
        'active_ban' => [
            'source' => ModerationState::Active,
            'destination' => ModerationState::Banned,
            'kind' => 'ban',
        ],
        'restricted_reactivate_active' => [
            'source' => ModerationState::Restricted,
            'destination' => ModerationState::Active,
            'kind' => 'reactivate',
        ],
        'restricted_suspend' => [
            'source' => ModerationState::Restricted,
            'destination' => ModerationState::Suspended,
            'kind' => 'suspend',
        ],
        'restricted_ban' => [
            'source' => ModerationState::Restricted,
            'destination' => ModerationState::Banned,
            'kind' => 'ban',
        ],
        'suspended_reactivate_active' => [
            'source' => ModerationState::Suspended,
            'destination' => ModerationState::Active,
            'kind' => 'reactivate',
        ],
        'suspended_restrict' => [
            'source' => ModerationState::Suspended,
            'destination' => ModerationState::Restricted,
            'kind' => 'downgrade',
        ],
        'suspended_ban' => [
            'source' => ModerationState::Suspended,
            'destination' => ModerationState::Banned,
            'kind' => 'ban',
        ],
        'banned_unban_active' => [
            'source' => ModerationState::Banned,
            'destination' => ModerationState::Active,
            'kind' => 'unban',
        ],
        'banned_unban_restricted' => [
            'source' => ModerationState::Banned,
            'destination' => ModerationState::Restricted,
            'kind' => 'unban',
        ],
        'banned_suspend' => [
            'source' => ModerationState::Banned,
            'destination' => ModerationState::Suspended,
            'kind' => 'replace_ban',
        ],
    ];

    #[Locked]
    public ?string $trustedMountedModerationActionName = null;

    #[Locked]
    public ?int $trustedMountedModerationStateVersion = null;

    protected ?string $snapshotMountedModerationActionName = null;

    protected bool $hasTrustedMountedModerationAction = false;

    protected bool $mountedModerationActionThisRequest = false;

    protected function getHeaderActions(): array
    {
        $currentState = $this->getRecord()->moderationState();

        if (! $currentState instanceof ModerationState) {
            return [];
        }

        $actions = [];

        foreach (self::MODERATION_ACTION_DEFINITIONS as $name => $definition) {
            if ($definition['source'] !== $currentState) {
                continue;
            }

            $actions[] = $this->makeModerationAction($name);
        }

        return $actions;
    }

    public function hydrateMountedActions(?array $mountedActions): void
    {
        $this->snapshotMountedModerationActionName = $this->mountedModerationActionName($mountedActions);
    }

    public function booted(): void
    {
        $trustedName = $this->trustedMountedModerationActionName;
        $trustedVersion = $this->trustedMountedModerationStateVersion;

        if (
            ! is_string($trustedName)
            || ! is_int($trustedVersion)
            || ($trustedVersion < 0)
            || ($this->snapshotMountedModerationActionName !== $trustedName)
        ) {
            if (
                is_string($trustedName)
                || is_int($trustedVersion)
                || $this->containsModerationAction($this->mountedActions)
            ) {
                $this->discardMountedModerationAction();
            }

            return;
        }

        $this->hasTrustedMountedModerationAction = true;
        $this->cacheAction($this->makeModerationAction($trustedName));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $definition = self::MODERATION_ACTION_DEFINITIONS[$name] ?? null;
        $initialMountedActionsCount = count($this->mountedActions ?? []);
        $expectedPreviousVersion = is_array($definition)
            ? $this->getRecord()->moderationStateVersion()
            : null;

        if (
            is_array($definition)
            && (
                ($initialMountedActionsCount !== 0)
                || ($arguments !== [])
                || ! $this->isExpectedModerationActionContext($context)
                || ($definition['source'] !== $this->getRecord()->moderationState())
                || ! is_int($expectedPreviousVersion)
            )
        ) {
            return null;
        }

        $result = parent::mountAction($name, $arguments, $context);

        if (
            is_array($definition)
            && (count($this->mountedActions ?? []) === 1)
            && ($this->mountedModerationActionName($this->mountedActions) === $name)
            && (($mountedAction = $this->getMountedAction()) instanceof Action)
            && ($mountedAction->getName() === $name)
            && $this->mountedActionShouldOpenModal($mountedAction)
        ) {
            $this->trustedMountedModerationActionName = $name;
            $this->trustedMountedModerationStateVersion = $expectedPreviousVersion;
            $this->mountedModerationActionThisRequest = true;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callMountedAction(array $arguments = []): mixed
    {
        if (
            is_string($this->trustedMountedModerationActionName)
            || $this->containsModerationAction($this->mountedActions)
        ) {
            $mountedName = $this->mountedModerationActionName($this->mountedActions);
            $canCall = is_string($mountedName)
                && ($mountedName === $this->trustedMountedModerationActionName)
                && is_int($this->trustedMountedModerationStateVersion)
                && ($this->trustedMountedModerationStateVersion >= 0)
                && ($this->hasTrustedMountedModerationAction || $this->mountedModerationActionThisRequest)
                && ($arguments === []);

            if (! $canCall) {
                $this->discardMountedModerationAction();

                return null;
            }
        }

        return parent::callMountedAction($arguments);
    }

    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        parent::unmountAction($cancelParentActions);

        if ($this->mountedModerationActionName($this->mountedActions) === $this->trustedMountedModerationActionName) {
            return;
        }

        $this->trustedMountedModerationActionName = null;
        $this->trustedMountedModerationStateVersion = null;
        $this->hasTrustedMountedModerationAction = false;
        $this->mountedModerationActionThisRequest = false;
    }

    private function makeModerationAction(string $name): Action
    {
        $definition = self::MODERATION_ACTION_DEFINITIONS[$name] ?? null;

        if (! is_array($definition)) {
            throw new LogicException("Unknown moderation action [{$name}].");
        }

        $source = $definition['source'];
        $destination = $definition['destination'];
        $kind = $definition['kind'];

        $action = Action::make($name)
            ->label(__('admin.moderation.actions.'.$name))
            ->color($this->moderationActionColor($kind))
            ->authorize('moderate')
            ->visible(fn (User $record): bool => Gate::allows('moderate', $record))
            ->authorizationNotification()
            ->authorizationMessage(__('admin.moderation.notifications.unauthorized'))
            ->requiresConfirmation()
            ->modalHeading(__('admin.moderation.modal.heading', [
                'action' => __('admin.moderation.actions.'.$name),
            ]))
            ->modalDescription(__('admin.moderation.modal.description', [
                'current' => $this->moderationStateLabel($source),
                'destination' => $this->moderationStateLabel($destination),
            ]))
            ->modalSubmitActionLabel(__('admin.moderation.actions.'.$name))
            ->modalWidth('4xl')
            ->schema(fn (User $record): array => $this->moderationSchema($record, $destination, $kind))
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title(__('admin.moderation.notifications.success_title'))
                    ->body(__('admin.moderation.notifications.success_body', [
                        'destination' => $this->moderationStateLabel($destination),
                    ])),
            )
            ->action(function (Action $action, array $data, User $record) use ($source, $destination, $kind): void {
                $expectedPreviousVersion = $this->trustedMountedModerationStateVersion;
                $rawReasonCode = $data['reason_code'] ?? null;
                $rawReasonText = $data['reason_text'] ?? null;

                if (
                    ! is_int($expectedPreviousVersion)
                    || ($expectedPreviousVersion < 0)
                    || ! is_string($rawReasonCode)
                    || ! is_string($rawReasonText)
                ) {
                    $this->failModerationAction($action);

                    return;
                }

                $reasonCode = ModerationReason::tryFrom($rawReasonCode);

                if (! $reasonCode instanceof ModerationReason) {
                    $this->failModerationAction($action);

                    return;
                }

                if (
                    ($kind === 'ban')
                    && (
                        ! is_string($data['username_confirmation'] ?? null)
                        || ! hash_equals($record->username, $data['username_confirmation'])
                    )
                ) {
                    $this->failModerationAction($action);

                    return;
                }

                $actor = auth('web')->user();

                if (! $actor instanceof User) {
                    $this->failModerationAction($action);

                    return;
                }

                try {
                    app(TransitionUserModerationState::class)->handle(
                        $actor,
                        $record,
                        $destination,
                        $reasonCode,
                        $rawReasonText,
                        expectedPreviousState: $source,
                        expectedPreviousVersion: $expectedPreviousVersion,
                    );
                } catch (DomainException $exception) {
                    $this->failModerationAction(
                        $action,
                        stale: $exception->getCode() === TransitionUserModerationState::STALE_STATE_ERROR_CODE,
                    );

                    return;
                } catch (AuthorizationException|InvalidArgumentException|OverflowException) {
                    $this->failModerationAction($action);

                    return;
                }

                $this->refreshRecord();
                $this->getSchema('infolist')?->record($this->getRecord());
                $this->forceRender();
                $action->success();
            });

        return $action;
    }

    /**
     * @return array<int, mixed>
     */
    private function moderationSchema(User $record, ModerationState $destination, string $kind): array
    {
        return [
            Section::make(__('admin.moderation.sections.target'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('target_name')
                                ->label(__('admin.moderation.modal.name'))
                                ->state(fn (User $record): string => $record->name),
                            TextEntry::make('target_username')
                                ->label(__('admin.moderation.modal.username'))
                                ->state(fn (User $record): string => '@'.$record->username),
                            TextEntry::make('target_email')
                                ->label(__('admin.moderation.modal.email'))
                                ->state(fn (User $record): string => $record->email),
                            TextEntry::make('target_id')
                                ->label(__('admin.moderation.modal.account_id'))
                                ->state(fn (User $record): string => (string) $record->getKey()),
                            TextEntry::make('current_state')
                                ->label(__('admin.moderation.modal.current_state'))
                                ->state(fn (User $record): string => $this->moderationStateLabel($record->moderationState())),
                            TextEntry::make('destination_state')
                                ->label(__('admin.moderation.modal.destination_state'))
                                ->state($this->moderationStateLabel($destination)),
                        ]),
                    TextEntry::make('consequences')
                        ->label(__('admin.moderation.modal.consequences'))
                        ->state(__('admin.moderation.consequences.'.$kind))
                        ->prose(),
                ]),
            Section::make(__('admin.moderation.sections.reason'))
                ->schema([
                    Select::make('reason_code')
                        ->label(__('admin.moderation.fields.reason_code'))
                        ->options($this->moderationReasonOptions())
                        ->in($this->moderationReasonValues())
                        ->required()
                        ->validationMessages([
                            'required' => __('admin.moderation.validation.required'),
                            'in' => __('admin.moderation.validation.reason_code'),
                        ])
                        ->helperText(__('admin.moderation.modal.reason_code_helper')),
                    Textarea::make('reason_text')
                        ->label(__('admin.moderation.fields.reason_text'))
                        ->required()
                        ->string()
                        ->trim()
                        ->minLength(1)
                        ->maxLength(2000)
                        ->rows(5)
                        ->validationMessages([
                            'required' => __('admin.moderation.validation.required'),
                            'string' => __('admin.moderation.validation.reason_text_string'),
                            'min' => __('admin.moderation.validation.reason_text_min'),
                            'max' => __('admin.moderation.validation.reason_text_max'),
                        ])
                        ->helperText(__('admin.moderation.modal.reason_text_helper')),
                    ...($kind === 'suspend' ? [
                        Checkbox::make('suspend_acknowledgement')
                            ->label(__('admin.moderation.fields.suspend_acknowledgement'))
                            ->required()
                            ->accepted()
                            ->validationMessages([
                                'required' => __('admin.moderation.validation.required'),
                                'accepted' => __('admin.moderation.validation.acknowledgement'),
                            ]),
                    ] : []),
                    ...($kind === 'ban' ? [
                        TextInput::make('current_password')
                            ->label(__('admin.moderation.fields.current_password'))
                            ->password()
                            ->required()
                            ->rule(fn (): Closure => $this->banPasswordRule())
                            ->validationMessages([
                                'required' => __('admin.moderation.validation.required'),
                            ])
                            ->helperText(__('admin.moderation.modal.password_helper')),
                        TextInput::make('username_confirmation')
                            ->label(fn (User $record): string => __('admin.moderation.fields.username_confirmation', [
                                'username' => $record->username,
                            ]))
                            ->required()
                            ->string()
                            ->rule(function (User $record): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (! is_string($value) || ! hash_equals($record->username, $value)) {
                                        $fail(__('admin.moderation.validation.username_confirmation'));
                                    }
                                };
                            })
                            ->validationMessages([
                                'required' => __('admin.moderation.validation.required'),
                                'string' => __('admin.moderation.validation.username_confirmation'),
                            ])
                            ->helperText(__('admin.moderation.modal.username_helper')),
                    ] : []),
                ]),
        ];
    }

    private function banPasswordRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $actor = auth('web')->user();

            if (! $actor instanceof User) {
                $fail(__('admin.moderation.validation.current_password'));

                return;
            }

            $attempts = RateLimiter::increment(
                self::BAN_PASSWORD_RATE_LIMIT_KEY_PREFIX.$actor->getKey(),
                self::BAN_PASSWORD_RATE_LIMIT_DECAY_SECONDS,
            );

            if ($attempts > self::BAN_PASSWORD_RATE_LIMIT_MAX_ATTEMPTS) {
                Notification::make()
                    ->danger()
                    ->title(__('admin.moderation.notifications.rate_limited_title'))
                    ->body(__('admin.moderation.notifications.rate_limited_body'))
                    ->send();

                $fail(__('admin.moderation.notifications.rate_limited_body'));

                return;
            }

            if (! is_string($value) || ! Hash::check($value, $actor->getAuthPassword())) {
                $fail(__('admin.moderation.validation.current_password'));
            }
        };
    }

    private function failModerationAction(Action $action, bool $stale = false): void
    {
        $action->failure();

        $notification = Notification::make()
            ->danger()
            ->title($stale
                ? __('admin.moderation.notifications.stale_title')
                : __('admin.moderation.notifications.failure_title'))
            ->body($stale
                ? __('admin.moderation.notifications.stale_body')
                : __('admin.moderation.notifications.failure_body'));

        if ($stale) {
            $notification->send();
            $this->refreshRecord();
            $this->getSchema('infolist')?->record($this->getRecord());
            $this->forceRender();
            $action->cancel();

            return;
        }

        $action->failureNotification($notification);
        $this->refreshRecord();
    }

    private function refreshRecord(): void
    {
        $record = UserResource::getEloquentQuery()
            ->whereKey($this->getRecord()->getKey())
            ->first();

        if ($record instanceof User) {
            $this->record = $record;
            $this->cachedHeaderActions = [];
            $this->cacheInteractsWithHeaderActions();
        }
    }

    /**
     * @param  array<array<string, mixed>>|null  $mountedActions
     */
    private function mountedModerationActionName(?array $mountedActions): ?string
    {
        if (
            ! is_array($mountedActions)
            || ! array_is_list($mountedActions)
            || (count($mountedActions) !== 1)
            || ! is_array($mountedActions[0])
            || (($mountedActions[0]['arguments'] ?? null) !== [])
            || ! $this->isExpectedModerationActionContext($mountedActions[0]['context'] ?? null)
        ) {
            return null;
        }

        $name = $mountedActions[0]['name'] ?? null;

        return is_string($name) && array_key_exists($name, self::MODERATION_ACTION_DEFINITIONS)
            ? $name
            : null;
    }

    private function isExpectedModerationActionContext(mixed $context): bool
    {
        if (
            ! is_array($context)
            || (array_keys($context) !== ['recordKey'])
            || ! is_string($context['recordKey'])
        ) {
            return false;
        }

        return hash_equals((string) $this->getRecord()->getKey(), $context['recordKey']);
    }

    /**
     * @param  array<array<string, mixed>>|null  $mountedActions
     */
    private function containsModerationAction(?array $mountedActions): bool
    {
        if (! is_array($mountedActions)) {
            return false;
        }

        foreach ($mountedActions as $mountedAction) {
            if (
                is_array($mountedAction)
                && is_string($mountedAction['name'] ?? null)
                && array_key_exists($mountedAction['name'], self::MODERATION_ACTION_DEFINITIONS)
            ) {
                return true;
            }
        }

        return false;
    }

    private function discardMountedModerationAction(): void
    {
        $this->mountedActions = [];
        $this->cachedMountedActions = null;
        $this->trustedMountedModerationActionName = null;
        $this->trustedMountedModerationStateVersion = null;
        $this->snapshotMountedModerationActionName = null;
        $this->hasTrustedMountedModerationAction = false;
        $this->mountedModerationActionThisRequest = false;
        $this->forgetCachedMountedActionSchemas();
        $this->forceRender();
    }

    /**
     * @return array<string, string>
     */
    private function moderationReasonOptions(): array
    {
        return collect(ModerationReason::cases())
            ->mapWithKeys(fn (ModerationReason $reason): array => [
                $reason->value => __('admin.moderation.reasons.'.$reason->value),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function moderationReasonValues(): array
    {
        return array_map(
            static fn (ModerationReason $reason): string => $reason->value,
            ModerationReason::cases(),
        );
    }

    private function moderationStateLabel(?ModerationState $state): string
    {
        return $state instanceof ModerationState
            ? __('admin.moderation.states.'.$state->value)
            : __('admin.values.unknown_state');
    }

    private function moderationActionColor(string $kind): string
    {
        return match ($kind) {
            'restrict' => 'warning',
            'reactivate', 'downgrade', 'unban' => 'success',
            default => 'danger',
        };
    }
}
