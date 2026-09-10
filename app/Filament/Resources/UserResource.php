<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $slug = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.labels.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.users');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.users');
    }

    public static function getBreadcrumb(): string
    {
        return __('admin.navigation.users');
    }

    public static function getEloquentQuery(): Builder
    {
        return User::query()->select([
            'id',
            'name',
            'username',
            'email',
            'email_verified_at',
            'is_verified',
            'locale',
            'role',
            'bio',
            'created_at',
            'updated_at',
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label(__('admin.columns.username'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin.columns.email'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('role')
                    ->label(__('admin.columns.role'))
                    ->state(fn (User $record): string => self::roleState($record->role))
                    ->formatStateUsing(fn (string $state): string => self::roleLabel($state))
                    ->color(fn (string $state): string => self::roleColor($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label(__('admin.columns.email_verification'))
                    ->state(fn (User $record): string => filled($record->email_verified_at) ? 'verified' : 'unverified')
                    ->formatStateUsing(fn (string $state): string => self::verificationLabel($state))
                    ->color(fn (string $state): string => self::verificationColor($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('is_verified')
                    ->label(__('admin.columns.public_verification'))
                    ->state(fn (User $record): string => self::isTruthy($record->is_verified) ? 'verified' : 'unverified')
                    ->formatStateUsing(fn (string $state): string => self::verificationLabel($state))
                    ->color(fn (string $state): string => self::verificationColor($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('admin.columns.locale'))
                    ->state(fn (User $record): string => self::localeState($record->locale))
                    ->formatStateUsing(fn (string $state): string => self::localeLabel($state))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.columns.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(query: static function (Builder $query, string $direction): void {
                        $query
                            ->orderBy('created_at', $direction)
                            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
                    }),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('admin.filters.role'))
                    ->options([
                        UserRole::User->value => __('admin.values.user'),
                        UserRole::Administrator->value => __('admin.values.administrator'),
                    ]),
                SelectFilter::make('email_verification')
                    ->label(__('admin.filters.email_verification'))
                    ->options([
                        'verified' => __('admin.values.verified'),
                        'unverified' => __('admin.values.unverified'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'verified' => $query->whereNotNull('email_verified_at'),
                            'unverified' => $query->whereNull('email_verified_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('public_verification')
                    ->label(__('admin.filters.public_verification'))
                    ->options([
                        'verified' => __('admin.values.verified'),
                        'unverified' => __('admin.values.unverified'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'verified' => $query->where('is_verified', true),
                            'unverified' => $query->where('is_verified', false),
                            default => $query,
                        };
                    }),
                SelectFilter::make('locale')
                    ->label(__('admin.filters.locale'))
                    ->options([
                        'id' => __('admin.locales.id'),
                        'en' => __('admin.locales.en'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('admin.actions.view')),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->stackedOnMobile()
            ->emptyStateHeading(__('admin.table.empty_heading'))
            ->emptyStateDescription(__('admin.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('admin.columns.name')),
                                TextEntry::make('username')
                                    ->label(__('admin.columns.username')),
                                TextEntry::make('email')
                                    ->label(__('admin.columns.email')),
                            ]),
                    ]),
                Section::make(__('admin.sections.access'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('role')
                                    ->label(__('admin.columns.role'))
                                    ->state(fn (User $record): string => self::roleState($record->role))
                                    ->formatStateUsing(fn (string $state): string => self::roleLabel($state))
                                    ->color(fn (string $state): string => self::roleColor($state))
                                    ->badge(),
                                TextEntry::make('email_verified_at')
                                    ->label(__('admin.columns.email_verification'))
                                    ->state(fn (User $record): string => filled($record->email_verified_at) ? 'verified' : 'unverified')
                                    ->formatStateUsing(fn (string $state): string => self::verificationLabel($state))
                                    ->color(fn (string $state): string => self::verificationColor($state))
                                    ->badge(),
                                TextEntry::make('is_verified')
                                    ->label(__('admin.columns.public_verification'))
                                    ->state(fn (User $record): string => self::isTruthy($record->is_verified) ? 'verified' : 'unverified')
                                    ->formatStateUsing(fn (string $state): string => self::verificationLabel($state))
                                    ->color(fn (string $state): string => self::verificationColor($state))
                                    ->badge(),
                                TextEntry::make('locale')
                                    ->label(__('admin.columns.locale'))
                                    ->state(fn (User $record): string => self::localeState($record->locale))
                                    ->formatStateUsing(fn (string $state): string => self::localeLabel($state)),
                            ]),
                    ]),
                Section::make(__('admin.sections.profile'))
                    ->schema([
                        TextEntry::make('bio')
                            ->label(__('admin.columns.bio'))
                            ->placeholder(__('admin.values.not_set'))
                            ->prose(),
                    ]),
                Section::make(__('admin.sections.metadata'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('id')
                                    ->label(__('admin.columns.id')),
                                TextEntry::make('created_at')
                                    ->label(__('admin.columns.created_at'))
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('updated_at')
                                    ->label(__('admin.columns.updated_at'))
                                    ->dateTime('Y-m-d H:i:s'),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    private static function roleState(mixed $state): string
    {
        return match (is_string($state) ? UserRole::tryFrom($state) : null) {
            UserRole::User => UserRole::User->value,
            UserRole::Administrator => UserRole::Administrator->value,
            default => 'unknown',
        };
    }

    private static function roleLabel(string $state): string
    {
        return match ($state) {
            UserRole::User->value => __('admin.values.user'),
            UserRole::Administrator->value => __('admin.values.administrator'),
            default => __('admin.values.unknown_role'),
        };
    }

    private static function roleColor(string $state): string
    {
        return match ($state) {
            UserRole::Administrator->value => 'warning',
            UserRole::User->value => 'gray',
            default => 'danger',
        };
    }

    private static function verificationLabel(string $state): string
    {
        return $state === 'verified'
            ? __('admin.values.verified')
            : __('admin.values.unverified');
    }

    private static function verificationColor(string $state): string
    {
        return $state === 'verified' ? 'success' : 'gray';
    }

    private static function isTruthy(mixed $state): bool
    {
        return filter_var($state, FILTER_VALIDATE_BOOLEAN);
    }

    private static function localeState(mixed $state): string
    {
        return is_string($state) && $state !== '' ? $state : 'not_set';
    }

    private static function localeLabel(string $state): string
    {
        return match ($state) {
            'id' => __('admin.locales.id'),
            'en' => __('admin.locales.en'),
            'not_set' => __('admin.values.not_set'),
            default => $state,
        };
    }
}
