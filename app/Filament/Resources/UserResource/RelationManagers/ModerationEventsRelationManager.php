<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Models\ModerationEvent;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModerationEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'moderationEvents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.moderation.history.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.moderation.history.timestamp'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('actor')
                    ->label(__('admin.moderation.history.actor'))
                    ->state(function (ModerationEvent $record): string {
                        $actor = $record->relationLoaded('actorUser')
                            ? $record->getRelation('actorUser')
                            : null;

                        return $actor instanceof User
                            ? $actor->name.' (@'.$actor->username.')'
                            : __('admin.moderation.history.unknown_actor');
                    }),
                TextColumn::make('previous_state')
                    ->label(__('admin.moderation.history.previous_state'))
                    ->formatStateUsing(fn (string $state): string => $this->stateLabel($state)),
                TextColumn::make('new_state')
                    ->label(__('admin.moderation.history.new_state'))
                    ->formatStateUsing(fn (string $state): string => $this->stateLabel($state)),
                TextColumn::make('reason_code')
                    ->label(__('admin.moderation.history.reason_code'))
                    ->formatStateUsing(fn (string $state): string => $this->reasonLabel($state)),
                TextColumn::make('reason_text')
                    ->label(__('admin.moderation.history.reason_text'))
                    ->wrap(),
            ])
            ->modifyQueryUsing(function (Builder|Relation $query): Builder|Relation {
                return $query
                    ->with('actorUser:id,name,username')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            })
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading(__('admin.moderation.history.empty_heading'))
            ->emptyStateDescription(__('admin.moderation.history.empty_description'));
    }

    private function stateLabel(string $state): string
    {
        return ModerationState::tryFrom($state) instanceof ModerationState
            ? __('admin.moderation.states.'.$state)
            : __('admin.values.unknown_state');
    }

    private function reasonLabel(string $reason): string
    {
        return ModerationReason::tryFrom($reason) instanceof ModerationReason
            ? __('admin.moderation.reasons.'.$reason)
            : __('admin.values.unknown_reason');
    }
}
