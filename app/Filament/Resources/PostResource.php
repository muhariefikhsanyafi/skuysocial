<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Resources\PostResource\Pages\ViewPost;
use App\Models\Post;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'posts';

    protected static ?string $recordTitleAttribute = 'uid';

    public static function getModelLabel(): string
    {
        return __('admin.labels.post');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.posts');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.posts');
    }

    public static function getBreadcrumb(): string
    {
        return __('admin.navigation.posts');
    }

    public static function getEloquentQuery(): Builder
    {
        return Post::query()
            ->select([
                'id',
                'uid',
                'user_id',
                'content',
                'topic',
                'repost_id',
                'image',
                'images',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name,username',
                'originalPost' => static function ($query): void {
                    $query
                        ->select([
                            'id',
                            'uid',
                            'user_id',
                            'content',
                            'topic',
                            'repost_id',
                            'image',
                            'images',
                            'created_at',
                            'updated_at',
                        ])
                        ->with('user:id,name,username');
                },
            ])
            ->withCount([
                'comments',
                'likes',
                'bookmarks',
                'reposts',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uid')
                    ->label(__('admin.columns.uid'))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return $query->where('uid', $search);
                    })
                    ->sortable(),
                TextColumn::make('author')
                    ->label(__('admin.columns.author'))
                    ->state(static fn (Post $record): string => self::authorState($record->user))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'user',
                            static fn (Builder $userQuery): Builder => $userQuery->where('username', $search),
                        );
                    })
                    ->wrap(),
                TextColumn::make('content')
                    ->label(__('admin.columns.content'))
                    ->limit(120)
                    ->wrap(),
                TextColumn::make('topic')
                    ->label(__('admin.columns.topic'))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return $query->where('topic', $search);
                    })
                    ->sortable()
                    ->placeholder(__('admin.values.not_set')),
                TextColumn::make('original_post')
                    ->label(__('admin.columns.original_post'))
                    ->state(static fn (Post $record): string => self::originalPostState($record))
                    ->placeholder(__('admin.values.not_set')),
                ImageColumn::make('media_paths')
                    ->label(__('admin.columns.media'))
                    ->state(static fn (Post $record): array => self::mediaPaths($record))
                    ->disk('public')
                    ->visibility('public')
                    ->limit(4)
                    ->stacked()
                    ->imageSize(40)
                    ->alt(static fn (Post $record): string => __('admin.values.media_alt', ['uid' => $record->uid]))
                    ->placeholder(__('admin.values.no_media')),
                TextColumn::make('comments_count')
                    ->label(__('admin.columns.comments_count')),
                TextColumn::make('likes_count')
                    ->label(__('admin.columns.likes_count')),
                TextColumn::make('bookmarks_count')
                    ->label(__('admin.columns.bookmarks_count')),
                TextColumn::make('reposts_count')
                    ->label(__('admin.columns.reposts_count')),
                TextColumn::make('created_at')
                    ->label(__('admin.columns.created'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(query: static function (Builder $query, string $direction): void {
                        $query
                            ->orderBy('created_at', $direction)
                            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
                    }),
                TextColumn::make('updated_at')
                    ->label(__('admin.columns.updated_at'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('post_type')
                    ->label(__('admin.filters.post_type'))
                    ->options([
                        'original' => __('admin.values.original'),
                        'repost' => __('admin.values.repost'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'original' => $query->whereNull('repost_id'),
                            'repost' => $query->whereNotNull('repost_id'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('admin.actions.view')),
            ])
            ->splitSearchTerms(false)
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->stackedOnMobile()
            ->emptyStateHeading(__('admin.table.posts_empty_heading'))
            ->emptyStateDescription(__('admin.table.posts_empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('uid')
                                    ->label(__('admin.columns.uid')),
                                TextEntry::make('id')
                                    ->label(__('admin.columns.identifier')),
                                TextEntry::make('author')
                                    ->label(__('admin.columns.author'))
                                    ->state(static fn (Post $record): string => self::authorState($record->user)),
                                TextEntry::make('topic')
                                    ->label(__('admin.columns.topic'))
                                    ->placeholder(__('admin.values.not_set')),
                            ]),
                    ]),
                Section::make(__('admin.sections.content'))
                    ->schema([
                        TextEntry::make('content')
                            ->label(__('admin.columns.content'))
                            ->columnSpanFull()
                            ->wrap()
                            ->placeholder(__('admin.values.not_set')),
                        ImageEntry::make('media_paths')
                            ->label(__('admin.columns.media'))
                            ->state(static fn (Post $record): array => self::mediaPaths($record))
                            ->disk('public')
                            ->visibility('public')
                            ->limit(4)
                            ->stacked()
                            ->imageSize(96)
                            ->alt(static fn (Post $record): string => __('admin.values.media_alt', ['uid' => $record->uid]))
                            ->placeholder(__('admin.values.no_media')),
                    ]),
                Section::make(__('admin.sections.relationships'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('post_type')
                                    ->label(__('admin.columns.relationship'))
                                    ->state(static fn (Post $record): string => self::postTypeState($record)),
                                TextEntry::make('original_post')
                                    ->label(__('admin.columns.original_post'))
                                    ->state(static fn (Post $record): string => self::originalPostState($record))
                                    ->placeholder(__('admin.values.not_set')),
                            ]),
                    ]),
                Section::make(__('admin.sections.interactions'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('comments_count')
                                    ->label(__('admin.columns.comments_count')),
                                TextEntry::make('likes_count')
                                    ->label(__('admin.columns.likes_count')),
                                TextEntry::make('bookmarks_count')
                                    ->label(__('admin.columns.bookmarks_count')),
                                TextEntry::make('reposts_count')
                                    ->label(__('admin.columns.reposts_count')),
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
            'index' => ListPosts::route('/'),
            'view' => ViewPost::route('/{record}'),
        ];
    }

    private static function authorState(?User $user): string
    {
        return $user instanceof User
            ? $user->name.' (@'.$user->username.')'
            : __('admin.values.unknown_author');
    }

    private static function originalPostState(Post $post): string
    {
        return $post->originalPost?->uid ?? __('admin.values.not_set');
    }

    private static function postTypeState(Post $post): string
    {
        return $post->repost_id === null
            ? __('admin.values.original')
            : __('admin.values.repost');
    }

    /**
     * Return only relative paths from the existing public posts directory.
     *
     * @return array<int, string>
     */
    private static function mediaPaths(Post $post): array
    {
        $storedPaths = array_merge(
            is_array($post->images) ? $post->images : [],
            [$post->image],
        );
        $paths = [];

        foreach ($storedPaths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = ltrim(trim($path), '/');

            if (
                $path === ''
                || ! str_starts_with($path, 'posts/')
                || str_contains($path, '..')
                || str_contains($path, '\\')
                || in_array($path, $paths, true)
            ) {
                continue;
            }

            $paths[] = $path;
        }

        return array_slice($paths, 0, 4);
    }
}
