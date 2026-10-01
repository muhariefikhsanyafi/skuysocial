<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Resources\AnnouncementResource\Pages\ViewAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use App\Support\AnnouncementAudience;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $slug = 'announcements';

    protected static ?string $recordTitleAttribute = 'title_en';

    public static function getModelLabel(): string
    {
        return __('admin_announcements.labels.announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_announcements.labels.announcements');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_announcements.navigation.announcements');
    }

    public static function getEloquentQuery(): Builder
    {
        return Announcement::query()->with(['creator:id,name,username', 'sender:id,name,username'])
            ->withCount([
                'targets', 'reads', 'pushBatches',
                'pushBatches as delivery_queued_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('status', 'queued'),
                'pushBatches as delivery_processing_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('status', 'processing'),
                'pushBatches as delivery_sent_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'sent'),
                'pushBatches as delivery_no_subscribers_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'no_subscribers'),
                'pushBatches as delivery_failed_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'failed'),
                'pushBatches as delivery_unknown_count' => static fn (Builder $query) => $query->where('batch_type', 'delivery')->where('provider_outcome', 'unknown'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin_announcements.labels.announcement'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('title_id')->required()->maxLength(160)->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    TextInput::make('title_en')->required()->maxLength(160)->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    Textarea::make('body_id')->required()->maxLength(5000)->rows(8)->columnSpanFull()->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    Textarea::make('body_en')->required()->maxLength(5000)->rows(8)->columnSpanFull()->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    Select::make('audience_type')->label(__('admin_announcements.fields.audience'))->options([
                        'all' => __('admin_announcements.audience.all'),
                        'targeted' => __('admin_announcements.audience.targeted'),
                    ])->required()->live()->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    Select::make('target_user_ids')->label(__('admin_announcements.fields.targets'))->multiple()->searchable()->preload(false)
                        ->getSearchResultsUsing(static function (string $search, AnnouncementAudience $audience): array {
                            if (mb_strlen(trim($search)) < 2) {
                                return [];
                            }

                            return $audience->eligibleUserQuery()
                                ->where(static fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                                ->orderBy('name')->limit(50)->get(['id', 'name', 'username'])
                                ->mapWithKeys(static fn (User $user): array => [$user->id => $user->name.' (@'.$user->username.')'])->all();
                        })
                        ->getOptionLabelsUsing(static function (array $values, AnnouncementAudience $audience): array {
                            $ids = array_values(array_unique(array_map('intval', $values)));
                            $valid = $audience->eligibleUserQuery()->whereIn('users.id', $ids)->pluck('users.id')->all();
                            $invalidIds = array_diff($ids, $valid);
                            if ($invalidIds !== []) {
                                $existingIds = DB::table('users')->whereIn('id', $invalidIds)->pluck('id')->all();
                                $missingIds = array_diff($invalidIds, $existingIds);
                                if ($missingIds !== []) {
                                    throw ValidationException::withMessages([
                                        'data.target_user_ids' => __('admin_announcements.validation.eligible_target_required'),
                                    ]);
                                }
                            }

                            return DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'username'])
                                ->mapWithKeys(static fn ($user): array => [$user->id => $user->name.' (@'.$user->username.')'.(in_array($user->id, $invalidIds, true) ? ' — '.__('admin_announcements.validation.eligible_target_required') : '')])->all();
                        })
                        ->visible(fn (callable $get): bool => $get('audience_type') === 'targeted')
                        ->disabled(fn (?Announcement $record): bool => $record?->status === 'sent'),
                    Placeholder::make('recipient_preview')->label(__('admin_announcements.fields.recipient_count'))
                        ->content(static function (callable $get, ?Announcement $record, AnnouncementAudience $audience): string {
                            if (! $record) {
                                if ($get('audience_type') === 'targeted') {
                                    return (string) count($get('target_user_ids') ?? []);
                                }

                                return (string) $audience->eligibleUserQuery()->count();
                            }

                            return (string) ($get('audience_type') === 'targeted'
                                ? $record->targets()->whereIn('user_id', $audience->eligibleUserQuery()->select('users.id'))->count()
                                : $audience->eligibleUserQuery()->count());
                        }),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('title_en')->label(__('admin_announcements.fields.title_en'))->searchable()->wrap(),
            TextColumn::make('audience_type')->label(__('admin_announcements.fields.audience'))->formatStateUsing(fn (string $state): string => __('admin_announcements.audience.'.$state))->badge(),
            TextColumn::make('status')->label(__('admin_announcements.fields.status'))->formatStateUsing(fn (string $state): string => __('admin_announcements.status.'.$state))->badge(),
            TextColumn::make('push_state')->label(__('admin_announcements.fields.push_status'))->state(static fn (Announcement $record): string => $record->delivery_queued_count + $record->delivery_processing_count > 0
                ? 'queued'
                : ($record->delivery_unknown_count > 0 ? 'unknown' : ($record->delivery_failed_count > 0 ? 'failed' : ($record->delivery_sent_count > 0 ? 'sent' : 'no_subscribers'))))
                ->formatStateUsing(fn (string $state): string => __('admin_announcements.push.'.$state))->badge(),
            TextColumn::make('sent_at')->label(__('admin_announcements.status.sent'))->dateTime('Y-m-d H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => __('admin_announcements.status.draft'), 'sent' => __('admin_announcements.status.sent')]),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make()->visible(fn (Announcement $record): bool => $record->status === 'draft'),
            DeleteAction::make()->visible(fn (Announcement $record): bool => $record->status === 'draft')->requiresConfirmation(),
        ])->defaultSort('id', 'desc')->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin_announcements.labels.announcement'))->schema([
                TextEntry::make('title_id'), TextEntry::make('title_en'),
                TextEntry::make('body_id')->prose(), TextEntry::make('body_en')->prose(),
                TextEntry::make('audience_type')->formatStateUsing(fn (string $state): string => __('admin_announcements.audience.'.$state)),
                TextEntry::make('status')->formatStateUsing(fn (string $state): string => __('admin_announcements.status.'.$state)),
                TextEntry::make('sent_at')->dateTime('Y-m-d H:i:s'),
            ]),
            Section::make(__('admin_announcements.fields.push_status'))->schema([
                TextEntry::make('push_batches')->state(static function (Announcement $record): string {
                    $parts = [];
                    foreach (['queued', 'processing', 'sent', 'no_subscribers', 'failed', 'unknown'] as $state) {
                        $count = (int) $record->getAttribute('delivery_'.$state.'_count');
                        if ($count > 0) {
                            $parts[] = __('admin_announcements.push.'.$state).': '.$count;
                        }
                    }

                    return $parts === [] ? __('admin_announcements.push.queued') : implode(', ', $parts);
                }),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'view' => ViewAnnouncement::route('/{record}'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isUsableAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isUsableAdministrator() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isUsableAdministrator() && $record->status === 'draft';
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isUsableAdministrator() && $record->status === 'draft';
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
