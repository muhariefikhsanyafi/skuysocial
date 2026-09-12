<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_verified_administrator_can_access_the_user_resource_index(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create(['name' => 'Directory User']);

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee(__('admin.navigation.users'))
            ->assertSee($user->name);
    }

    public function test_verified_administrator_can_view_a_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create([
            'name' => 'Inspectable User',
            'bio' => 'A profile description for inspection.',
            'locale' => 'en',
        ]);
        $user->forceFill(['is_verified' => true])->save();

        $this->actingAs($administrator)
            ->get(UserResource::getUrl('view', ['record' => $user], false, 'admin'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->username)
            ->assertSee($user->email)
            ->assertSee($user->bio)
            ->assertSee(__('admin.values.user'))
            ->assertSee(__('admin.values.verified'))
            ->assertDontSee($user->getRawOriginal('password'))
            ->assertDontSee($user->getRawOriginal('remember_token'));
    }

    public function test_ordinary_user_cannot_access_the_user_resource(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.title'))
            ->assertSeeText(trans('moderation.forbidden.generic_body'))
            ->assertDontSeeText(trans('moderation.restricted.denial_body'));
    }

    public function test_unverified_administrator_cannot_access_the_user_resource(): void
    {
        $administrator = User::factory()->administrator()->unverified()->create();

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_user_resource_registers_only_index_and_view_routes(): void
    {
        $this->assertSame(['index', 'view'], array_keys(UserResource::getPages()));
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.resources.users.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.resources.users.view'));
        $this->assertNull(Route::getRoutes()->getByName('filament.admin.resources.users.create'));
        $this->assertNull(Route::getRoutes()->getByName('filament.admin.resources.users.edit'));
    }

    public function test_user_resource_has_only_a_view_table_action_and_no_mutation_actions(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
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
            ->assertTableColumnExists('role', fn (TextColumn $column): bool => ! $column->isInline(), $user);

        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($user));
        $this->assertFalse(UserResource::canDelete($user));
        $this->assertFalse(UserResource::canDeleteAny());
        $this->assertFalse(UserResource::canRestore($user));
        $this->assertFalse(UserResource::canRestoreAny());
        $this->assertFalse(UserResource::canForceDelete($user));
        $this->assertFalse(UserResource::canForceDeleteAny());
        $this->assertFalse(UserResource::canReplicate($user));
        $this->assertFalse(UserResource::canReorder());
    }

    public function test_role_is_displayed_as_a_read_only_text_entry(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(ViewUser::class, ['record' => $user->username])
            ->assertSchemaComponentExists('role', 'infolist', fn (TextEntry $entry): bool => true);
    }

    public function test_name_search_is_server_side_and_bounded_to_matching_records(): void
    {
        $administrator = User::factory()->administrator()->create();
        $matching = User::factory()->create(['name' => 'Name Search Match']);
        $other = User::factory()->create(['name' => 'Different Name']);

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->searchTable('Name Search Match')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_username_search_is_server_side_and_bounded_to_matching_records(): void
    {
        $administrator = User::factory()->administrator()->create();
        $matching = User::factory()->create(['username' => 'username_match']);
        $other = User::factory()->create(['username' => 'username_other']);

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->searchTable('username_match')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_email_search_is_server_side_and_bounded_to_matching_records(): void
    {
        $administrator = User::factory()->administrator()->create();
        $matching = User::factory()->create(['email' => 'email-match@example.com']);
        $other = User::factory()->create(['email' => 'email-other@example.com']);

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->searchTable('email-match@example.com')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_role_filter_limits_results(): void
    {
        $administrator = User::factory()->administrator()->create();
        $otherAdministrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->filterTable('role', UserRole::Administrator->value)
            ->assertCanSeeTableRecords([$administrator, $otherAdministrator])
            ->assertCanNotSeeTableRecords([$user]);
    }

    public function test_email_verification_filter_distinguishes_verified_and_unverified_accounts(): void
    {
        $administrator = User::factory()->administrator()->create();
        $verified = User::factory()->create();
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->filterTable('email_verification', 'unverified')
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified]);
    }

    public function test_public_verification_filter_distinguishes_public_states(): void
    {
        $administrator = User::factory()->administrator()->create();
        $verified = User::factory()->create();
        $unverified = User::factory()->create();
        $verified->forceFill(['is_verified' => true])->save();

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->filterTable('public_verification', 'verified')
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$unverified]);
    }

    public function test_locale_filter_limits_results(): void
    {
        $administrator = User::factory()->administrator()->create();
        $english = User::factory()->create(['locale' => 'en']);
        $indonesian = User::factory()->create(['locale' => 'id']);

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->filterTable('locale', 'en')
            ->assertCanSeeTableRecords([$english])
            ->assertCanNotSeeTableRecords([$indonesian]);
    }

    public function test_sensitive_attributes_are_not_selected_by_the_resource_query(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator);

        $attributes = UserResource::getEloquentQuery()
            ->whereKey($user)
            ->firstOrFail()
            ->getAttributes();

        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
        $this->assertArrayNotHasKey('auth_session_version', $attributes);
        $this->assertArrayNotHasKey('avatar', $attributes);
    }

    public function test_unknown_role_values_render_as_a_safe_label(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unknownRole = User::factory()->create();
        $unknownRole->forceFill(['role' => 'unexpected'])->save();

        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$unknownRole])
            ->assertSee(__('admin.values.unknown_role'));
    }

    public function test_user_table_uses_bounded_pagination_and_newest_first_default_sort(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->count(30)->create();

        $this->actingAs($administrator);

        $component = Livewire::test(ListUsers::class);

        $this->assertSame(25, $component->instance()->getTableRecordsPerPage());
        $this->assertSame([25, 50, 100], $component->instance()->getTable()->getPaginationPageOptions());
        $this->assertCount(25, $component->instance()->getTableRecords());
        $this->assertSame('created_at', $component->instance()->getTable()->getDefaultSortColumn());
        $this->assertSame('desc', $component->instance()->getTable()->getDefaultSortDirection());
    }

    public function test_user_table_uses_descending_id_as_the_created_at_tie_breaker(): void
    {
        $administrator = User::factory()->administrator()->create();
        $createdAt = now()->subDay();
        $first = User::factory()->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $second = User::factory()->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->actingAs($administrator);

        $records = Livewire::test(ListUsers::class)
            ->instance()
            ->getTableRecords()
            ->whereIn('id', [$first->id, $second->id])
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame([$second->id, $first->id], $records);
    }

    public function test_admin_labels_follow_the_authenticated_users_locale(): void
    {
        $administrator = User::factory()->administrator()->create(['locale' => 'en']);

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Name')
            ->assertDontSee('Pengguna');
    }
}
