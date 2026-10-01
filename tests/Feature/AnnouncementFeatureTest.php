<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Filament\Resources\AnnouncementResource;
use App\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\ViewAnnouncement;
use App\Jobs\DeliverAnnouncementPushBatch;
use App\Jobs\DispatchAnnouncementPush;
use App\Models\Announcement;
use App\Models\AnnouncementPushBatch;
use App\Models\User;
use App\Services\OneSignalPushService;
use App\Support\AnnouncementAudience;
use App\Support\SendAnnouncement;
use Filament\Actions\Action;
use Filament\Facades\Filament as FilamentFacade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AnnouncementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FilamentFacade::setCurrentPanel('admin');
    }

    public function test_targeted_visibility_rechecks_recipient_eligibility(): void
    {
        $author = User::factory()->administrator()->create();
        $target = User::factory()->create(['locale' => 'id']);
        $other = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($author, 'targeted');
        $announcement->targets()->create(['user_id' => $target->id]);
        $this->actingAs($target)->get(route('announcements.index'))->assertOk()->assertSee('Judul ID');
        $this->actingAs($other)->get(route('announcements.index'))->assertOk()->assertDontSee('Judul ID');
        $this->actingAs($other)->get(route('announcements.show', $announcement))->assertNotFound();
        $target->forceFill(['moderation_state' => ModerationState::Suspended->value])->save();
        $this->actingAs($target)->get(route('announcements.show', $announcement))->assertRedirect();
    }

    public function test_all_user_cutoff_excludes_future_accounts(): void
    {
        $author = User::factory()->administrator()->create();
        $existing = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($author, 'all', $existing->id);
        $future = User::factory()->create(['locale' => 'en']);
        $this->actingAs($existing)->get(route('announcements.show', $announcement))->assertOk();
        $this->actingAs($future)->get(route('announcements.show', $announcement))->assertNotFound();
    }

    public function test_read_marker_is_created_on_detail_only_and_separate_from_activity_dm(): void
    {
        $author = User::factory()->administrator()->create();
        $recipient = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($author, 'all', $recipient->id);
        $this->actingAs($recipient)->get(route('announcements.index'))->assertOk();
        $this->assertSame(1, app(AnnouncementAudience::class)->unreadCount($recipient));
        $this->get(route('announcements.show', $announcement))->assertOk();
        $this->get(route('announcements.show', $announcement))->assertOk();
        $this->assertSame(0, app(AnnouncementAudience::class)->unreadCount($recipient));
        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->assertSame(0, $recipient->unreadMessageCount());
        $this->assertDatabaseCount('announcement_reads', 1);
    }

    public function test_in_app_copy_uses_account_locale_and_read_state(): void
    {
        $author = User::factory()->administrator()->create(['locale' => 'en']);
        $id = User::factory()->create(['locale' => 'id']);
        $en = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($author, 'all', max($id->id, $en->id));
        $this->actingAs($id)->get(route('announcements.index'))->assertOk()->assertSee('Judul ID')->assertSee('Isi Indonesia');
        $this->actingAs($en)->get(route('announcements.index'))->assertOk()->assertSee('Title EN')->assertSee('English body');
        $this->actingAs($en)->get(route('announcements.show', $announcement))->assertOk();
        $this->get(route('announcements.index'))->assertSee(__('announcements.read'));
    }

    public function test_eligibility_includes_active_restricted_and_null_locale_fallback(): void
    {
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $active = User::factory()->create(['locale' => 'id']);
        $restricted = User::factory()->create(['locale' => 'en', 'moderation_state' => 'restricted']);
        $nullLocale = User::factory()->create(['locale' => null]);
        User::factory()->unverified()->create(['locale' => 'en']);
        User::factory()->create(['locale' => 'fr']);
        User::factory()->create(['locale' => 'en', 'moderation_state' => 'suspended']);
        User::factory()->create(['locale' => 'en', 'moderation_state' => 'banned']);
        $announcement = $this->sentAnnouncement($admin, 'all', $nullLocale->id);
        $audience = app(AnnouncementAudience::class);
        $this->assertFalse($audience->isEligible(User::factory()->create(['locale' => 'fr'])));
        $this->assertTrue($audience->isEligible($nullLocale));
        $this->assertSame('id', $audience->resolveLocale($nullLocale));
        $this->assertEqualsCanonicalizing([$admin->id, $active->id, $restricted->id, $nullLocale->id], $audience->recipientQuery($announcement)->pluck('users.id')->all());
    }

    public function test_send_freezes_send_time_cutoff_and_is_safe_when_repeated(): void
    {
        Queue::fake();
        $admin = User::factory()->administrator()->create();
        User::factory()->create(['locale' => 'id']);
        $future = User::factory()->unverified()->create(['locale' => 'id']);
        $announcement = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $this->assertTrue(app(SendAnnouncement::class)->handle($announcement, $admin));
        $announcement->refresh();
        $this->assertSame('sent', $announcement->status);
        $this->assertSame($future->id, (int) $announcement->audience_cutoff_user_id);
        $this->assertFalse(app(SendAnnouncement::class)->handle($announcement, $admin));
        $this->assertDatabaseHas('announcement_push_batches', ['announcement_id' => $announcement->id, 'batch_key' => 'dispatch', 'batch_type' => 'dispatch']);
        $this->assertSame('queued', $this->batch($announcement, 'dispatch')->status);
        Queue::assertPushed(DispatchAnnouncementPush::class, 1);
    }

    public function test_targeted_send_rejects_ineligible_selected_users(): void
    {
        $admin = User::factory()->administrator()->create();
        $valid = User::factory()->create(['locale' => 'en']);
        $suspended = User::factory()->create(['locale' => 'en', 'moderation_state' => 'suspended']);
        $announcement = Announcement::query()->create($this->draftAttributes(['audience_type' => 'targeted', 'created_by' => $admin->id]));
        $announcement->targets()->createMany([['user_id' => $valid->id], ['user_id' => $suspended->id]]);
        try {
            app(SendAnnouncement::class)->handle($announcement, $admin);
            $this->fail('Invalid targeted recipients must prevent publication.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('audience', $exception->errors());
        }
        $this->assertSame('draft', $announcement->fresh()->status);
    }

    public function test_sent_records_are_immutable(): void
    {
        $admin = User::factory()->administrator()->create();
        $announcement = $this->sentAnnouncement($admin, 'all', $admin->id);
        $announcement->title_en = 'Changed';
        $this->expectException(LogicException::class);
        $announcement->save();
    }

    public function test_fake_onesignal_delivery_groups_account_locales_and_uses_canonical_route(): void
    {
        config(['app.url' => 'https://skuysocial.test', 'services.onesignal.enabled' => true, 'services.onesignal.app_id' => 'synthetic-app-id', 'services.onesignal.rest_api_key' => 'synthetic-test-key']);
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $id = User::factory()->create(['locale' => 'id']);
        $en = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($admin, 'all', max($id->id, $en->id));
        $idBatch = $announcement->pushBatches()->create(['batch_key' => 'delivery:id:'.$id->id, 'batch_type' => 'delivery', 'locale' => 'id', 'range_start_user_id' => $id->id, 'range_end_user_id' => $id->id, 'recipient_count' => 1, 'status' => 'queued']);
        $enBatch = $announcement->pushBatches()->create(['batch_key' => 'delivery:en:'.$en->id, 'batch_type' => 'delivery', 'locale' => 'en', 'range_start_user_id' => $en->id, 'range_end_user_id' => $en->id, 'recipient_count' => 1, 'status' => 'queued']);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-id'], 200)]);
        (new DeliverAnnouncementPushBatch((int) $idBatch->id))->handle(app(OneSignalPushService::class), app(AnnouncementAudience::class));
        (new DeliverAnnouncementPushBatch((int) $enBatch->id))->handle(app(OneSignalPushService::class), app(AnnouncementAudience::class));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => ($request->data()['headings']['en'] ?? null) === 'Judul ID' && ($request->data()['headings']['id'] ?? null) === 'Judul ID' && $request->data()['url'] === route('announcements.show', $announcement, true));
        Http::assertSent(fn ($request): bool => ($request->data()['headings']['en'] ?? null) === 'Title EN' && ! array_key_exists('id', $request->data()['headings']) && $request->data()['url'] === route('announcements.show', $announcement, true));
    }

    public function test_dispatch_creates_bounded_per_locale_batches_and_queues_default_connection(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        User::factory()->create(['locale' => 'id']);
        User::factory()->create(['locale' => 'en']);
        $announcement = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $this->assertTrue(app(SendAnnouncement::class)->handle($announcement, $admin));
        $announcement->refresh();
        $dispatch = $announcement->pushBatches()->where('batch_type', 'dispatch')->firstOrFail();
        $this->assertSame(3, (int) $dispatch->recipient_count);
        (new DispatchAnnouncementPush((int) $announcement->id, (int) $dispatch->id))->handle(app(AnnouncementAudience::class));
        $batches = $announcement->pushBatches()->where('batch_type', 'delivery')->get();
        $this->assertSame('completed', $dispatch->fresh()->status);
        $this->assertSame(2, $batches->count());
        $this->assertSame(['en', 'id'], $batches->pluck('locale')->sort()->values()->all());
        $this->assertSame(3, (int) $batches->sum('recipient_count'));
        $this->assertTrue($batches->every(static fn ($batch): bool => $batch->recipient_count <= AnnouncementAudience::PUSH_BATCH_SIZE));
        Queue::assertPushed(DispatchAnnouncementPush::class, 1);
        Queue::assertPushed(DeliverAnnouncementPushBatch::class, 2);
    }

    public function test_dispatch_reuses_persisted_batch_membership_after_eligibility_changes(): void
    {
        Queue::fake();
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $id = User::factory()->create(['locale' => 'id']);
        $english = User::factory()->create(['locale' => 'en']);
        $announcement = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $this->assertTrue(app(SendAnnouncement::class)->handle($announcement, $admin));
        $announcement->refresh();
        $dispatch = $this->batch($announcement, 'dispatch');
        app(AnnouncementAudience::class)->createDeliveryBatchRows($announcement);
        $before = $announcement->pushBatches()->where('batch_type', 'delivery')->orderBy('id')->get();
        $this->assertCount(2, $before);
        $ranges = $before->map(static fn (AnnouncementPushBatch $batch): array => [(int) $batch->range_start_user_id, (int) $batch->range_end_user_id])->all();
        $english->forceFill(['moderation_state' => ModerationState::Suspended->value])->save();
        $english->forceFill(['moderation_state' => ModerationState::Active->value])->save();
        (new DispatchAnnouncementPush((int) $announcement->id, (int) $dispatch->id))->handle(app(AnnouncementAudience::class));
        $after = $announcement->pushBatches()->where('batch_type', 'delivery')->orderBy('id')->get();
        $this->assertCount(2, $after);
        $id->forceFill(['moderation_state' => ModerationState::Suspended->value])->save();
        Queue::assertPushed(DeliverAnnouncementPushBatch::class, 2);
    }

    public function test_dispatch_keeps_original_batch_when_recipient_becomes_ineligible_before_delivery(): void
    {
        Queue::fake();
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $recipient = User::factory()->create(['locale' => 'en']);
        $announcement = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $this->assertTrue(app(SendAnnouncement::class)->handle($announcement, $admin));
        $announcement->refresh();
        $dispatch = $this->batch($announcement, 'dispatch');
        $delivery = $announcement->pushBatches()->create([
            'batch_key' => 'delivery:en:'.$recipient->id,
            'batch_type' => 'delivery',
            'locale' => 'en',
            'range_start_user_id' => $recipient->id,
            'range_end_user_id' => $recipient->id,
            'recipient_count' => 1,
            'status' => 'queued',
        ]);
        $this->assertSame(1, (int) $delivery->recipient_count);
        $recipient->forceFill(['moderation_state' => ModerationState::Suspended->value])->save();
        config(['services.onesignal.enabled' => true]);
        (new DeliverAnnouncementPushBatch((int) $delivery->id))->handle(app(OneSignalPushService::class), app(AnnouncementAudience::class));
        $this->assertSame('no_subscribers', $delivery->fresh()->provider_outcome);
        $this->assertSame('completed', $delivery->fresh()->status);
        Http::assertNothingSent();
        $dispatch->forceFill(['status' => 'processing'])->save();
        (new DispatchAnnouncementPush((int) $announcement->id, (int) $dispatch->id))->handle(app(AnnouncementAudience::class));
        $this->assertSame('processing', $dispatch->fresh()->status);
        $this->assertSame(1, $announcement->pushBatches()->where('batch_type', 'delivery')->count());
        $this->assertSame($recipient->id, (int) $delivery->fresh()->range_start_user_id);
        $this->assertSame($recipient->id, (int) $delivery->fresh()->range_end_user_id);
    }

    public function test_announcement_schema_enforces_target_and_read_uniqueness(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->create();
        $announcement = $this->sentAnnouncement($admin, 'targeted');
        $announcement->targets()->create(['user_id' => $user->id]);
        $announcement->reads()->create(['user_id' => $user->id, 'read_at' => now()]);
        try {
            $announcement->targets()->create(['user_id' => $user->id]);
            $this->fail('Duplicate target must be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseCount('announcement_targets', 1);
        }
        try {
            $announcement->reads()->create(['user_id' => $user->id, 'read_at' => now()]);
            $this->fail('Duplicate read marker must be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseCount('announcement_reads', 1);
        }
        $this->assertTrue(Schema::hasColumn('announcement_push_batches', 'range_start_user_id'));
        $this->assertTrue(Schema::hasColumn('announcement_push_batches', 'range_end_user_id'));
    }

    public function test_filament_admin_can_create_edit_and_delete_a_draft(): void
    {
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $target = User::factory()->create(['locale' => 'en']);
        $this->actingAs($admin);
        Livewire::test(CreateAnnouncement::class)
            ->fillForm([
                'title_id' => 'Draf ID', 'title_en' => 'Draft EN',
                'body_id' => 'Isi ID', 'body_en' => 'Body EN',
                'audience_type' => 'targeted', 'target_user_ids' => [$target->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $draft = Announcement::query()->where('title_en', 'Draft EN')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertDatabaseHas('announcement_targets', ['announcement_id' => $draft->id, 'user_id' => $target->id]);
        $this->get(AnnouncementResource::getUrl('edit', ['record' => $draft], false, 'admin'))->assertOk();
        Livewire::test(EditAnnouncement::class, ['record' => $draft->getRouteKey()])
            ->fillForm([
                'title_id' => 'Draf ID Diubah', 'title_en' => 'Edited EN',
                'body_id' => 'Isi diperbarui', 'body_en' => 'Updated body',
                'audience_type' => 'targeted', 'target_user_ids' => [$target->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Edited EN', $draft->fresh()->title_en);
        $this->assertSame([$target->id], $draft->targets()->pluck('user_id')->all());
        $this->get(AnnouncementResource::getUrl('view', ['record' => $draft], false, 'admin'))->assertOk();
        Livewire::test(EditAnnouncement::class, ['record' => $draft->getRouteKey()])
            ->mountAction('delete_draft')->assertActionMounted('delete_draft')->callMountedAction();
        $this->assertDatabaseMissing('announcements', ['id' => $draft->id]);
    }

    public function test_filament_admin_cannot_send_without_confirmation_and_send_revalidates_draft(): void
    {
        Queue::fake();
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        User::factory()->create(['locale' => 'en']);
        $announcement = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $this->actingAs($admin);
        $component = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getRouteKey()]);
        $component->assertActionExists('send_announcement', fn (Action $action): bool => $action->isConfirmationRequired())
            ->assertActionDoesNotHaveColor('send_announcement', 'danger')
            ->assertActionHasColor('delete', 'danger');
        $component->mountAction('send_announcement')->assertActionMounted('send_announcement');
        $this->assertSame('draft', $announcement->fresh()->status);
        $component->callMountedAction();
        $this->assertSame('sent', $announcement->fresh()->status);
        $this->assertSame(1, $announcement->pushBatches()->where('batch_type', 'dispatch')->count());
        Queue::assertPushed(DispatchAnnouncementPush::class, 1);
        $component->assertRedirect(AnnouncementResource::getUrl('view', ['record' => $announcement], false, 'admin'));

        $sentComponent = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getRouteKey()]);
        $sentComponent
            ->assertSeeText(__('admin_announcements.status.sent'))
            ->assertActionHidden('edit')
            ->assertActionHidden('delete')
            ->assertActionHidden('send_announcement');
        $draft = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $draft->forceFill(['title_en' => ''])->saveQuietly();
        Livewire::test(ViewAnnouncement::class, ['record' => $draft->getRouteKey()])
            ->mountAction('send_announcement')->assertActionMounted('send_announcement');
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(0, $draft->pushBatches()->count());
    }

    public function test_announcement_admin_policy_is_separate_from_user_views(): void
    {
        $member = User::factory()->create(['locale' => 'en']);
        $this->actingAs($member);
        $this->assertFalse(AnnouncementResource::canViewAny());
        $this->get(route('announcements.index'))->assertOk();
        $admin = User::factory()->administrator()->create(['locale' => 'en']);
        $draft = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $sent = $this->sentAnnouncement($admin, 'all', $admin->id);
        $this->actingAs($admin);
        $this->assertTrue(AnnouncementResource::canViewAny());
        $this->get('/admin/announcements')->assertOk()->assertSee(__('admin_announcements.actions.create'));
        $this->assertTrue(Gate::allows('update', $draft));
        $this->assertTrue(Gate::allows('delete', $draft));
        $this->assertFalse(Gate::allows('update', $sent));
        $this->assertFalse(Gate::allows('delete', $sent));
        $this->get('/admin/announcements')->assertOk();
        $this->get('/admin/announcements/create')->assertOk();
    }

    public function test_mobile_navigation_keeps_messages_and_adds_separate_announcement_badge(): void
    {
        $recipient = User::factory()->create(['locale' => 'en']);
        $admin = User::factory()->administrator()->create();
        $this->sentAnnouncement($admin, 'all', $recipient->id);
        $html = $this->actingAs($recipient)->get(route('feed'))->assertOk()->getContent();
        $this->assertStringContainsString('Announcements, Unread announcements: 1', $html);
        $this->assertStringContainsString('aria-label="Unread: 1"', $html);
        $this->assertStringNotContainsString('data-mobile-nav-key="announcements"', $html);
        $this->assertStringContainsString('data-mobile-nav-key="messages"', $html);
        $mobileProfileMenuStart = strpos($html, 'id="mobile-profile-menu"');
        $mobileNavigationStart = strpos($html, 'id="mobile-navigation"');
        $this->assertNotFalse($mobileProfileMenuStart);
        $this->assertNotFalse($mobileNavigationStart);
        $mobileProfileMenu = substr($html, $mobileProfileMenuStart, $mobileNavigationStart - $mobileProfileMenuStart);
        $this->assertStringContainsString('href="'.route('announcements.index').'"', $mobileProfileMenu);
        $bottomNavStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $bottomNavEnd = strpos($html, '</nav>', $bottomNavStart);
        $bottomNav = substr($html, $bottomNavStart, $bottomNavEnd - $bottomNavStart);
        preg_match_all('/data-mobile-nav-key="([^"]+)"/', $bottomNav, $mobileKeys);
        $this->assertSame(['home', 'search', 'create', 'activity', 'messages'], $mobileKeys[1]);
    }

    public function test_sent_copy_is_escaped_and_draft_cannot_be_opened(): void
    {
        $admin = User::factory()->administrator()->create();
        $recipient = User::factory()->create(['locale' => 'en']);
        $sent = $this->sentAnnouncement($admin, 'all', $recipient->id);
        $draft = Announcement::query()->create($this->draftAttributes(['created_by' => $admin->id]));
        $sent->forceFill(['body_en' => '<script>alert(1)</script>'])->saveQuietly();
        $this->actingAs($recipient)->get(route('announcements.show', $sent))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('announcements.show', $draft))->assertNotFound();
    }

    public function test_announcement_list_and_detail_use_full_width_announcement_layout(): void
    {
        $recipient = User::factory()->create(['locale' => 'en']);
        $admin = User::factory()->administrator()->create();
        $announcement = $this->sentAnnouncement($admin, 'all', $recipient->id);

        $this->actingAs($recipient)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('class="skuy-activity-item skuy-announcement-item"', false)
            ->assertSee('class="skuy-activity-item__body min-w-0 w-full"', false);

        $stylesheet = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($stylesheet);
        $this->assertMatchesRegularExpression('/\.skuy-announcement-item\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s', $stylesheet);
        $this->assertMatchesRegularExpression('/@media \(max-width:\s*767px\)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\.skuy-activity-item:not\(\.skuy-announcement-item\)\s*\{[^}]*grid-template-columns:\s*36px\s+minmax\(0,\s*1fr\)\s+30px;/s', $stylesheet);
        $this->assertMatchesRegularExpression('/\.skuy-announcement-detail\s*\{[^}]*min-width:\s*0;/s', $stylesheet);

        $this->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('class="skuy-announcement-detail__body"', false)
            ->assertDontSee('class="skuy-activity-item skuy-announcement-item"', false)
            ->assertSee('English body');
    }

    public function test_provider_failure_is_durable_and_does_not_unpublish_announcement(): void
    {
        config(['app.url' => 'https://skuysocial.test', 'services.onesignal.enabled' => true, 'services.onesignal.app_id' => 'synthetic-app-id', 'services.onesignal.rest_api_key' => 'synthetic-test-key']);
        $admin = User::factory()->administrator()->create();
        $recipient = User::factory()->create(['locale' => 'en']);
        $announcement = $this->sentAnnouncement($admin, 'all', $recipient->id);
        $batch = $announcement->pushBatches()->create(['batch_key' => 'delivery:en:'.$recipient->id, 'batch_type' => 'delivery', 'locale' => 'en', 'range_start_user_id' => $recipient->id, 'range_end_user_id' => $recipient->id, 'recipient_count' => 1, 'status' => 'queued']);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['errors' => ['synthetic']], 500)]);
        (new DeliverAnnouncementPushBatch((int) $batch->id))->handle(app(OneSignalPushService::class), app(AnnouncementAudience::class));
        $this->assertSame('failed', $batch->fresh()->provider_outcome);
        $this->assertSame('sent', $announcement->fresh()->status);
        $this->assertDatabaseMissing('announcement_push_batches', ['error_class' => 'synthetic']);
    }

    private function recipientQuery(Announcement $announcement): Builder
    {
        return app(AnnouncementAudience::class)->recipientQuery($announcement);
    }

    private function batch(Announcement $announcement, string $type): AnnouncementPushBatch
    {
        return $announcement->pushBatches()->where('batch_type', $type)->firstOrFail();
    }

    private function sentAnnouncement(User $author, string $audience, ?int $cutoff = null): Announcement
    {
        return Announcement::query()->create($this->draftAttributes([
            'audience_type' => $audience, 'status' => 'sent', 'created_by' => $author->id,
            'sent_by' => $author->id, 'sent_at' => now(), 'audience_cutoff_user_id' => $cutoff,
        ]));
    }

    private function draftAttributes(array $overrides = []): array
    {
        return array_merge(['title_id' => 'Judul ID', 'title_en' => 'Title EN', 'body_id' => 'Isi Indonesia', 'body_en' => 'English body', 'audience_type' => 'all', 'status' => 'draft'], $overrides);
    }
}
