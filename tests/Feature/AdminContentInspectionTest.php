<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Filament\Resources\CommentResource;
use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\CommentResource\Pages\ViewComment;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Resources\PostResource\Pages\ViewPost;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Policies\PostPolicy;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ImageEntry;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminContentInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_usable_administrator_can_access_post_and_comment_index_and_view_pages(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create([
            'name' => 'Inspectable Author',
            'username' => 'inspectable_author',
        ]);
        $post = $this->createPost($author, [
            'content' => 'Inspectable post content',
            'topic' => 'Inspection topic',
        ]);
        $comment = $this->createComment($author, $post, [
            'content' => 'Inspectable comment content',
        ]);

        $this->actingAs($administrator)
            ->get('/admin/posts')
            ->assertOk()
            ->assertSee(__('admin.navigation.posts'))
            ->assertSee($post->uid)
            ->assertSee($post->content)
            ->assertSee($author->username);

        $this->actingAs($administrator)
            ->get(PostResource::getUrl('view', ['record' => $post], false, 'admin'))
            ->assertOk()
            ->assertSee($post->uid)
            ->assertSee($post->content)
            ->assertSee(__('admin.values.original'));

        $this->actingAs($administrator)
            ->get('/admin/comments')
            ->assertOk()
            ->assertSee(__('admin.navigation.comments'))
            ->assertSee((string) $comment->id)
            ->assertSee($comment->content)
            ->assertSee($post->uid);

        $this->actingAs($administrator)
            ->get(CommentResource::getUrl('view', ['record' => $comment], false, 'admin'))
            ->assertOk()
            ->assertSee((string) $comment->id)
            ->assertSee($comment->content)
            ->assertSee($post->uid);
    }

    public function test_ordinary_user_cannot_access_post_or_comment_resources(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/posts')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/comments')
            ->assertForbidden();
    }

    #[DataProvider('nonActiveAdministratorStates')]
    public function test_non_active_administrator_cannot_access_content_resources(string $state): void
    {
        $administrator = User::factory()->administrator()->create([
            'moderation_state' => $state,
        ]);

        foreach (['/admin/posts', '/admin/comments'] as $path) {
            $response = $this->actingAs($administrator)->get($path);

            if (in_array($state, [ModerationState::Suspended->value, ModerationState::Banned->value], true)) {
                $response->assertRedirect('/admin/login');
            } else {
                $response->assertForbidden();
            }
        }
    }

    public static function nonActiveAdministratorStates(): array
    {
        return [
            'restricted' => [ModerationState::Restricted->value],
            'suspended' => [ModerationState::Suspended->value],
            'banned' => [ModerationState::Banned->value],
        ];
    }

    public function test_content_resources_register_only_index_and_view_routes(): void
    {
        $this->assertSame(['index', 'view'], array_keys(PostResource::getPages()));
        $this->assertSame(['index', 'view'], array_keys(CommentResource::getPages()));

        foreach (['posts', 'comments'] as $resource) {
            $this->assertNotNull(Route::getRoutes()->getByName("filament.admin.resources.{$resource}.index"));
            $this->assertNotNull(Route::getRoutes()->getByName("filament.admin.resources.{$resource}.view"));
            $this->assertNull(Route::getRoutes()->getByName("filament.admin.resources.{$resource}.create"));
            $this->assertNull(Route::getRoutes()->getByName("filament.admin.resources.{$resource}.edit"));
        }
    }

    public function test_content_resources_expose_only_view_actions_and_fail_closed_mutations(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author);
        $comment = $this->createComment($author, $post);

        $this->actingAs($administrator);

        Livewire::test(ListPosts::class)
            ->assertTableActionsExistInOrder(['view'])
            ->assertTableHeaderActionsExistInOrder([])
            ->assertTableBulkActionsExistInOrder([])
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('forceDelete')
            ->assertTableActionDoesNotExist('restore')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('restore')
            ->assertTableColumnExists('media_paths', fn (ImageColumn $column): bool => true, $post);

        Livewire::test(ListComments::class)
            ->assertTableActionsExistInOrder(['view'])
            ->assertTableHeaderActionsExistInOrder([])
            ->assertTableBulkActionsExistInOrder([])
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('forceDelete')
            ->assertTableActionDoesNotExist('restore')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('restore')
            ->assertTableColumnExists('id', fn (TextColumn $column): bool => ! $column->isInline(), $comment);

        Livewire::test(ViewPost::class, ['record' => $post->uid])
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');

        Livewire::test(ViewComment::class, ['record' => $comment->id])
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');

        foreach ([PostResource::class, CommentResource::class] as $resource) {
            $record = $resource === PostResource::class ? $post : $comment;

            $this->assertFalse($resource::canCreate());
            $this->assertFalse($resource::canEdit($record));
            $this->assertFalse($resource::canDelete($record));
            $this->assertFalse($resource::canDeleteAny());
            $this->assertFalse($resource::canRestore($record));
            $this->assertFalse($resource::canRestoreAny());
            $this->assertFalse($resource::canForceDelete($record));
            $this->assertFalse($resource::canForceDeleteAny());
            $this->assertFalse($resource::canReplicate($record));
            $this->assertFalse($resource::canReorder());
        }
    }

    public function test_user_generated_content_is_escaped_in_post_and_comment_views(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author, [
            'content' => '<script>alert("post")</script><b>Post text</b>',
        ]);
        $comment = $this->createComment($author, $post, [
            'content' => '<script>alert("comment")</script><b>Comment text</b>',
        ]);

        $this->actingAs($administrator)
            ->get(PostResource::getUrl('view', ['record' => $post], false, 'admin'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("post")</script>', false)
            ->assertDontSee('<b>Post text</b>', false);

        $this->actingAs($administrator)
            ->get(CommentResource::getUrl('view', ['record' => $comment], false, 'admin'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("comment")</script>', false)
            ->assertDontSee('<b>Comment text</b>', false);
    }

    public function test_post_repost_and_comment_parent_relationships_are_scoped_for_inspection(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $otherPost = $this->createPost($author, ['content' => 'Other post parent context']);
        $originalPost = $this->createPost($author, ['content' => 'Original post context']);
        $repost = $this->createPost($author, [
            'content' => null,
            'repost_id' => $originalPost->id,
        ]);
        $parent = $this->createComment($author, $originalPost, ['content' => 'Same post parent context']);
        $reply = $this->createComment($author, $originalPost, [
            'content' => 'Same post reply context',
            'parent_id' => $parent->id,
        ]);
        $foreignParent = $this->createComment($author, $otherPost, ['content' => 'Foreign parent context']);
        $invalidReply = $this->createComment($author, $originalPost, [
            'content' => 'Invalid cross-post reply context',
            'parent_id' => $foreignParent->id,
        ]);

        $this->actingAs($administrator)
            ->get(PostResource::getUrl('view', ['record' => $repost], false, 'admin'))
            ->assertOk()
            ->assertSee(__('admin.values.repost'))
            ->assertSee($originalPost->uid);

        $this->actingAs($administrator)
            ->get(CommentResource::getUrl('view', ['record' => $reply], false, 'admin'))
            ->assertOk()
            ->assertSee($originalPost->uid)
            ->assertSee(__('admin.values.reply'))
            ->assertSee($parent->content);

        $this->actingAs($administrator)
            ->get(CommentResource::getUrl('view', ['record' => $invalidReply], false, 'admin'))
            ->assertOk()
            ->assertSee($originalPost->uid)
            ->assertSee(__('admin.values.invalid_parent'))
            ->assertDontSee($foreignParent->content);
    }

    public function test_post_and_comment_tables_use_bounded_deterministic_pagination(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author);

        foreach (range(1, 30) as $number) {
            $this->createPost($author, [
                'content' => "Bounded post {$number}",
            ]);
        }
        Comment::query()->insert(array_map(
            static fn (int $number): array => [
                'user_id' => $author->id,
                'post_id' => $post->id,
                'content' => "Bounded comment {$number}",
                'parent_id' => null,
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ],
            range(1, 30),
        ));

        $this->actingAs($administrator);

        $posts = Livewire::test(ListPosts::class);
        $this->assertSame(25, $posts->instance()->getTableRecordsPerPage());
        $this->assertSame([25, 50, 100], $posts->instance()->getTable()->getPaginationPageOptions());
        $this->assertCount(25, $posts->instance()->getTableRecords());
        $this->assertSame('created_at', $posts->instance()->getTable()->getDefaultSortColumn());
        $this->assertSame('desc', $posts->instance()->getTable()->getDefaultSortDirection());

        $comments = Livewire::test(ListComments::class);
        $this->assertSame(25, $comments->instance()->getTableRecordsPerPage());
        $this->assertSame([25, 50, 100], $comments->instance()->getTable()->getPaginationPageOptions());
        $this->assertCount(25, $comments->instance()->getTableRecords());
        $this->assertSame('id', $comments->instance()->getTable()->getDefaultSortColumn());
        $this->assertSame('desc', $comments->instance()->getTable()->getDefaultSortDirection());
    }

    #[DataProvider('invalidTablePaginationValues')]
    public function test_content_tables_coerce_tampered_pagination_to_a_bounded_page_size(int|string $tamperedValue): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author);

        foreach (range(1, 25) as $number) {
            $this->createPost($author, [
                'content' => "Tampered pagination post {$number}",
            ]);
        }

        Comment::query()->insert(array_map(
            static fn (int $number): array => [
                'user_id' => $author->id,
                'post_id' => $post->id,
                'content' => "Tampered pagination comment {$number}",
                'parent_id' => null,
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ],
            range(1, 26),
        ));

        $this->actingAs($administrator);

        foreach ([ListPosts::class, ListComments::class] as $pageClass) {
            $component = Livewire::test($pageClass)
                ->set('tableRecordsPerPage', $tamperedValue);

            $this->assertSame(25, $component->instance()->getTableRecordsPerPage());
            $this->assertCount(25, $component->instance()->getTableRecords());
        }
    }

    public static function invalidTablePaginationValues(): array
    {
        return [
            'all' => ['all'],
            'unsupported numeric value' => [101],
        ];
    }

    public function test_post_topic_search_discovers_multi_word_topics(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create(['username' => 'topic_search_author']);
        $matching = $this->createPost($author, [
            'topic' => 'Inspection topic',
        ]);
        $other = $this->createPost($author, [
            'topic' => 'Different subject',
        ]);

        $this->actingAs($administrator);

        Livewire::test(ListPosts::class)
            ->searchTable('Inspection topic')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_nested_same_post_reply_is_not_treated_as_a_valid_root_parent(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($author, $post, ['content' => 'Root comment context']);
        $reply = $this->createComment($author, $post, [
            'content' => 'Nested reply parent context',
            'parent_id' => $root->id,
        ]);
        $nestedReply = $this->createComment($author, $post, [
            'content' => 'Nested reply content',
            'parent_id' => $reply->id,
        ]);

        $this->actingAs($administrator)
            ->get(CommentResource::getUrl('view', ['record' => $nestedReply], false, 'admin'))
            ->assertOk()
            ->assertSee($post->uid)
            ->assertSee(__('admin.values.invalid_parent'))
            ->assertDontSee($reply->content);
    }

    public function test_post_sorting_uses_descending_id_as_the_created_at_tie_breaker(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $createdAt = now()->subDay();
        $first = $this->createPost($author, [
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $second = $this->createPost($author, [
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->actingAs($administrator);

        $records = Livewire::test(ListPosts::class)
            ->instance()
            ->getTableRecords()
            ->whereIn('id', [$first->id, $second->id])
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame([$second->id, $first->id], $records);
    }

    public function test_content_queries_use_explicit_projections_eager_loading_and_bounded_media(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posts/inspection.jpg', 'image');

        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $original = $this->createPost($author, ['content' => 'Original for query inspection']);
        $post = $this->createPost($author, [
            'images' => [
                'posts/inspection.jpg',
                'posts/second.jpg',
                '../private.txt',
                'https://example.test/external.jpg',
                'posts/fifth.jpg',
            ],
            'repost_id' => $original->id,
        ]);

        DB::enableQueryLog();
        $records = PostResource::getEloquentQuery()
            ->whereKey($post)
            ->limit(10)
            ->get();
        $records->each(static function (Post $record): void {
            $record->user?->username;
            $record->originalPost?->user?->username;
        });
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $userQueries = collect($queries)
            ->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' from "users"'))
            ->count();
        $postQueries = collect($queries)
            ->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' from "posts"'))
            ->count();

        $this->assertLessThanOrEqual(2, $userQueries);
        $this->assertLessThanOrEqual(2, $postQueries);
        $this->assertTrue($records->firstOrFail()->relationLoaded('user'));
        $this->assertTrue($records->firstOrFail()->relationLoaded('originalPost'));
        $this->assertArrayNotHasKey('password', $records->firstOrFail()->user->getAttributes());

        $this->actingAs($administrator)
            ->get(PostResource::getUrl('view', ['record' => $post], false, 'admin'))
            ->assertOk()
            ->assertSee('/storage/posts/inspection.jpg', false)
            ->assertDontSee('external.jpg', false)
            ->assertDontSee('../private.txt', false);

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$post])
            ->assertTableColumnExists('media_paths', fn (ImageColumn $column): bool => true, $post);

        Livewire::test(ViewPost::class, ['record' => $post->uid])
            ->assertSchemaComponentExists('media_paths', 'infolist', fn (ImageEntry $entry): bool => true);
    }

    public function test_post_and_comment_policies_allow_only_read_inspection_for_usable_administrators(): void
    {
        $administrator = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $post = $this->createPost($author);
        $comment = $this->createComment($author, $post);
        $postPolicy = app(PostPolicy::class);
        $commentPolicy = app(CommentPolicy::class);

        $this->assertTrue($postPolicy->viewAny($administrator));
        $this->assertTrue($postPolicy->view($administrator, $post));
        $this->assertTrue($commentPolicy->viewAny($administrator));
        $this->assertTrue($commentPolicy->view($administrator, $comment));

        foreach (['create', 'update', 'delete', 'restore', 'forceDelete'] as $method) {
            $arguments = match ($method) {
                'create' => [$administrator],
                default => [$administrator, $post],
            };
            $commentArguments = match ($method) {
                'create' => [$administrator],
                default => [$administrator, $comment],
            };

            $this->assertFalse($postPolicy->{$method}(...$arguments));
            $this->assertFalse($commentPolicy->{$method}(...$commentArguments));
        }

        $this->assertFalse($postPolicy->deleteAny($administrator));
        $this->assertFalse($postPolicy->restoreAny($administrator));
        $this->assertFalse($postPolicy->forceDeleteAny($administrator));
        $this->assertFalse($commentPolicy->deleteAny($administrator));
        $this->assertFalse($commentPolicy->restoreAny($administrator));
        $this->assertFalse($commentPolicy->forceDeleteAny($administrator));
    }

    public function test_admin_content_translation_keys_exist_in_both_catalogs(): void
    {
        $english = Arr::dot(require lang_path('en/admin.php'));
        $indonesian = Arr::dot(require lang_path('id/admin.php'));
        $requiredKeys = [
            'navigation.posts',
            'navigation.comments',
            'labels.post',
            'labels.posts',
            'labels.comment',
            'labels.comments',
            'columns.uid',
            'columns.identifier',
            'columns.author',
            'columns.content',
            'columns.post_uid',
            'columns.parent_comment',
            'columns.parent_content',
            'columns.relationship',
            'columns.media',
            'columns.comments_count',
            'columns.likes_count',
            'columns.bookmarks_count',
            'columns.reposts_count',
            'columns.replies_count',
            'columns.created',
            'filters.post_type',
            'filters.comment_type',
            'values.original',
            'values.repost',
            'values.root_comment',
            'values.reply',
            'values.invalid_parent',
            'values.no_media',
            'values.media_alt',
            'sections.content',
            'sections.relationships',
            'sections.interactions',
            'sections.record',
            'table.posts_empty_heading',
            'table.posts_empty_description',
            'table.comments_empty_heading',
            'table.comments_empty_description',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $english);
            $this->assertArrayHasKey($key, $indonesian);
            $this->assertNotSame('', $english[$key]);
            $this->assertNotSame('', $indonesian[$key]);
        }

        $this->assertSame(array_keys($english), array_keys($indonesian));
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'user_id' => $user->id,
            'content' => 'Inspection post content',
        ], $attributes));
    }

    private function createComment(User $user, Post $post, array $attributes = []): Comment
    {
        return Comment::query()->create(array_merge([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'Inspection comment content',
        ], $attributes));
    }
}
