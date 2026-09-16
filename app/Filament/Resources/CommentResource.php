<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\CommentResource\Pages\ViewComment;
use App\Models\Comment;
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

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'comments';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('admin.labels.comment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.comments');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.comments');
    }

    public static function getBreadcrumb(): string
    {
        return __('admin.navigation.comments');
    }

    public static function getEloquentQuery(): Builder
    {
        return Comment::query()
            ->select([
                'id',
                'post_id',
                'user_id',
                'parent_id',
                'content',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name,username',
                'post:id,uid',
                'parent' => static function ($query): void {
                    $query
                        ->select([
                            'id',
                            'post_id',
                            'user_id',
                            'parent_id',
                            'content',
                            'created_at',
                            'updated_at',
                        ])
                        ->whereNull('parent_id');
                },
            ])
            ->withCount([
                'replies',
                'likes',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.columns.identifier'))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return ctype_digit($search)
                            ? $query->whereKey((int) $search)
                            : $query->whereKey(-1);
                    })
                    ->sortable(),
                TextColumn::make('author')
                    ->label(__('admin.columns.author'))
                    ->state(static fn (Comment $record): string => self::authorState($record->user))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'user',
                            static fn (Builder $userQuery): Builder => $userQuery->where('username', $search),
                        );
                    })
                    ->wrap(),
                TextColumn::make('post_uid')
                    ->label(__('admin.columns.post_uid'))
                    ->state(static fn (Comment $record): string => self::postUidState($record))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'post',
                            static fn (Builder $postQuery): Builder => $postQuery->where('uid', $search),
                        );
                    }),
                TextColumn::make('relationship')
                    ->label(__('admin.columns.relationship'))
                    ->state(static fn (Comment $record): string => self::relationshipState($record)),
                TextColumn::make('parent_comment')
                    ->label(__('admin.columns.parent_comment'))
                    ->state(static fn (Comment $record): string => self::parentIdState($record))
                    ->placeholder(__('admin.values.not_set')),
                TextColumn::make('parent_content')
                    ->label(__('admin.columns.parent_content'))
                    ->state(static fn (Comment $record): string => self::parentContentState($record))
                    ->limit(100)
                    ->wrap()
                    ->placeholder(__('admin.values.not_set')),
                TextColumn::make('content')
                    ->label(__('admin.columns.content'))
                    ->limit(120)
                    ->wrap(),
                TextColumn::make('replies_count')
                    ->label(__('admin.columns.replies_count')),
                TextColumn::make('likes_count')
                    ->label(__('admin.columns.likes_count')),
                TextColumn::make('created_at')
                    ->label(__('admin.columns.created'))
                    ->dateTime('Y-m-d H:i'),
                TextColumn::make('updated_at')
                    ->label(__('admin.columns.updated_at'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('comment_type')
                    ->label(__('admin.filters.comment_type'))
                    ->options([
                        'root' => __('admin.values.root_comment'),
                        'reply' => __('admin.values.reply'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'root' => $query->whereNull('parent_id'),
                            'reply' => $query->whereNotNull('parent_id'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('admin.actions.view')),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->stackedOnMobile()
            ->emptyStateHeading(__('admin.table.comments_empty_heading'))
            ->emptyStateDescription(__('admin.table.comments_empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('id')
                                    ->label(__('admin.columns.identifier')),
                                TextEntry::make('author')
                                    ->label(__('admin.columns.author'))
                                    ->state(static fn (Comment $record): string => self::authorState($record->user)),
                                TextEntry::make('post_uid')
                                    ->label(__('admin.columns.post_uid'))
                                    ->state(static fn (Comment $record): string => self::postUidState($record)),
                                TextEntry::make('relationship')
                                    ->label(__('admin.columns.relationship'))
                                    ->state(static fn (Comment $record): string => self::relationshipState($record)),
                            ]),
                    ]),
                Section::make(__('admin.sections.content'))
                    ->schema([
                        TextEntry::make('content')
                            ->label(__('admin.columns.content'))
                            ->columnSpanFull()
                            ->wrap(),
                    ]),
                Section::make(__('admin.sections.relationships'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('parent_comment')
                                    ->label(__('admin.columns.parent_comment'))
                                    ->state(static fn (Comment $record): string => self::parentIdState($record))
                                    ->placeholder(__('admin.values.not_set')),
                                TextEntry::make('parent_content')
                                    ->label(__('admin.columns.parent_content'))
                                    ->state(static fn (Comment $record): string => self::parentContentState($record))
                                    ->columnSpanFull()
                                    ->wrap()
                                    ->placeholder(__('admin.values.not_set')),
                            ]),
                    ]),
                Section::make(__('admin.sections.interactions'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('replies_count')
                                    ->label(__('admin.columns.replies_count')),
                                TextEntry::make('likes_count')
                                    ->label(__('admin.columns.likes_count')),
                            ]),
                    ]),
                Section::make(__('admin.sections.record'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('admin.columns.created'))
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
            'index' => ListComments::route('/'),
            'view' => ViewComment::route('/{record}'),
        ];
    }

    private static function authorState(?User $user): string
    {
        return $user instanceof User
            ? $user->name.' (@'.$user->username.')'
            : __('admin.values.unknown_author');
    }

    private static function postUidState(Comment $comment): string
    {
        return $comment->post?->uid ?? __('admin.values.not_set');
    }

    private static function relationshipState(Comment $comment): string
    {
        if ($comment->parent_id === null) {
            return __('admin.values.root_comment');
        }

        return self::validRootParent($comment) instanceof Comment
            ? __('admin.values.reply')
            : __('admin.values.invalid_parent');
    }

    private static function parentIdState(Comment $comment): string
    {
        if ($comment->parent_id === null) {
            return __('admin.values.not_set');
        }

        if (! self::validRootParent($comment) instanceof Comment) {
            return '#'.$comment->parent_id.' ('.__('admin.values.invalid_parent').')';
        }

        return '#'.$comment->parent_id;
    }

    private static function parentContentState(Comment $comment): string
    {
        return self::validRootParent($comment)?->content ?? __('admin.values.not_set');
    }

    private static function validRootParent(Comment $comment): ?Comment
    {
        $parent = $comment->relationLoaded('parent')
            ? $comment->getRelation('parent')
            : null;

        if (! $parent instanceof Comment) {
            return null;
        }

        return $parent->post_id === $comment->post_id && $parent->parent_id === null
            ? $parent
            : null;
    }
}
