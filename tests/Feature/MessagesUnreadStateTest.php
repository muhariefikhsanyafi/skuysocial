<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\UserFollowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagesUnreadStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_message_count_counts_incoming_messages_but_not_the_users_own(): void
    {
        $viewer = User::factory()->create();
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);

        $conversation->messages()->create(['sender_id' => $counterpart->getKey(), 'body' => 'Incoming one']);
        $conversation->messages()->create(['sender_id' => $viewer->getKey(), 'body' => 'Own message']);
        $conversation->messages()->create(['sender_id' => $counterpart->getKey(), 'body' => 'Incoming two']);

        $this->assertSame(2, $viewer->unreadMessageCount());
        $this->assertSame(1, $counterpart->unreadMessageCount());
    }

    public function test_global_unread_message_count_sums_only_conversations_the_user_participates_in(): void
    {
        $viewer = User::factory()->create();
        $firstCounterpart = User::factory()->create();
        $secondCounterpart = User::factory()->create();
        $unrelatedFirst = User::factory()->create();
        $unrelatedSecond = User::factory()->create();
        $findOrCreate = app(FindOrCreateDirectConversation::class);
        $firstConversation = $findOrCreate->handle($viewer, $firstCounterpart);
        $secondConversation = $findOrCreate->handle($viewer, $secondCounterpart);
        $unrelatedConversation = $findOrCreate->handle($unrelatedFirst, $unrelatedSecond);

        foreach (['First incoming', 'Second incoming'] as $body) {
            $firstConversation->messages()->create([
                'sender_id' => $firstCounterpart->getKey(),
                'body' => $body,
            ]);
        }
        $secondConversation->messages()->create([
            'sender_id' => $secondCounterpart->getKey(),
            'body' => 'Another conversation',
        ]);
        $unrelatedConversation->messages()->create([
            'sender_id' => $unrelatedSecond->getKey(),
            'body' => 'Not a viewer conversation',
        ]);

        $this->assertSame(3, $viewer->unreadMessageCount());
    }

    public function test_unread_message_count_uses_the_read_boundary_and_counts_retained_null_sender_messages(): void
    {
        $viewer = User::factory()->create();
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $firstIncoming = $conversation->messages()->create([
            'sender_id' => $counterpart->getKey(),
            'body' => 'Already acknowledged',
        ]);
        $conversation->messages()->create([
            'sender_id' => $viewer->getKey(),
            'body' => 'Own message after the boundary',
        ]);
        $deletedSenderMessage = $conversation->messages()->create([
            'sender_id' => $counterpart->getKey(),
            'body' => 'Incoming from a deleted account',
        ]);
        $viewer->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->update(['last_read_message_id' => $firstIncoming->getKey()]);
        $counterpart->delete();

        $this->assertDatabaseHas('messages', [
            'id' => $deletedSenderMessage->getKey(),
            'sender_id' => null,
        ]);
        $this->assertSame(1, $viewer->fresh()->unreadMessageCount());
    }

    public function test_opening_conversation_advances_only_the_viewers_marker_and_never_regresses(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $firstMessage = null;
        $latestMessage = null;

        for ($index = 1; $index <= 55; $index++) {
            $message = $conversation->messages()->create([
                'sender_id' => $counterpart->getKey(),
                'body' => 'History '.$index,
            ]);
            $firstMessage ??= $message;
            $latestMessage = $message;
        }

        $viewerParticipant = $viewer->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail();
        $counterpartParticipant = $counterpart->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail();
        $viewerParticipant->forceFill(['last_read_message_id' => $firstMessage->getKey()])->save();

        $this->actingAs($viewer)
            ->get(route('messages.show', $conversation).'?page=2')
            ->assertOk()
            ->assertSeeText('Newer messages');

        $this->assertSame($latestMessage->getKey(), $viewerParticipant->fresh()->last_read_message_id);
        $this->assertNull($counterpartParticipant->fresh()->last_read_message_id);

        $this->get(route('messages.show', $conversation).'?page=2')->assertOk();
        $this->assertSame($latestMessage->getKey(), $viewerParticipant->fresh()->last_read_message_id);
    }

    public function test_restricted_participant_can_clear_their_unread_state_but_cannot_send(): void
    {
        $viewer = User::factory()->create(['moderation_state' => 'restricted']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $message = $conversation->messages()->create([
            'sender_id' => $counterpart->getKey(),
            'body' => 'Read-only account can read this',
        ]);
        $viewerParticipant = $viewer->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertDontSee('data-message-composer', false);

        $this->assertSame($message->getKey(), $viewerParticipant->fresh()->last_read_message_id);
        $this->assertSame(0, $viewer->fresh()->unreadMessageCount());
        $this->postJson(route('messages.store', $conversation), ['body' => 'No send'])
            ->assertForbidden();
    }

    public function test_opening_an_empty_conversation_leaves_the_read_marker_null(): void
    {
        $viewer = User::factory()->create();
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $participant = $viewer->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('messages.show', $conversation))
            ->assertOk();

        $this->assertNull($participant->fresh()->last_read_message_id);
    }

    public function test_shared_navigation_and_inbox_render_authoritative_message_and_activity_counts(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        // A name containing HTML-sensitive characters must be escaped in rendered attributes.
        $counterpart = User::factory()->create(['name' => "O'Neil & Sons"]);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $conversation->messages()->create(['sender_id' => $counterpart->getKey(), 'body' => 'Unread one']);
        $conversation->messages()->create(['sender_id' => $viewer->getKey(), 'body' => 'Own message']);
        $conversation->messages()->create(['sender_id' => $counterpart->getKey(), 'body' => 'Unread two']);
        $viewer->notify(new UserFollowed($counterpart));
        $viewer->notify(new UserFollowed($counterpart));
        $readNotification = $viewer->notifications()->oldest('id')->firstOrFail();
        $readNotification->markAsRead();
        $unreadNotification = $viewer->unreadNotifications()->firstOrFail();
        $announcementAuthor = User::factory()->administrator()->create(['locale' => 'en']);
        $announcement = Announcement::query()->create([
            'title_id' => 'Pengumuman', 'title_en' => 'Announcement', 'body_id' => 'Isi ID', 'body_en' => 'Body EN',
            'audience_type' => 'all', 'status' => 'sent', 'audience_cutoff_user_id' => $viewer->id,
            'created_by' => $announcementAuthor->id, 'sent_by' => $announcementAuthor->id, 'sent_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk();

        $this->assertSame(2, $viewer->fresh()->unreadMessageCount());
        $this->assertSame(2, $response->viewData('rows')->first()['unread_count']);
        $this->assertSame(1, $viewer->fresh()->unreadNotifications()->count());
        $this->assertNotNull($readNotification->fresh()->read_at);
        $this->assertNull($unreadNotification->fresh()->read_at);

        $html = $response->getContent();
        $this->assertStringContainsString('data-flow-page="messages"', $html);
        $this->assertStringContainsString('data-flow-messages="inbox"', $html);
        $this->assertSame(2, substr_count($html, 'data-unread-messages-badge'));
        $this->assertSame(2, substr_count($html, 'data-unread-notifications-badge'));
        $this->assertSame(3, substr_count($html, 'aria-label="Unread messages: 2"'));
        $this->assertSame(2, substr_count($html, 'aria-label="Unread notifications: 1"'));
        $this->assertStringContainsString('data-conversation-unread-badge', $html);
        $this->assertStringContainsString('skuy-message-row--unread', $html);
        $this->assertStringContainsString(
            'aria-label="'.e(__('messages.open_conversation_with', ['name' => $counterpart->name]).', Unread messages: 2').'"',
            $html,
        );

        $mobileNavStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $this->assertNotFalse($mobileNavStart);
        $mobileNavEnd = strpos($html, '</nav>', $mobileNavStart);
        $mobileNav = substr($html, $mobileNavStart, $mobileNavEnd - $mobileNavStart);
        preg_match_all('/data-mobile-nav-key="([^"]+)"/', $mobileNav, $mobileKeys);
        $this->assertStringContainsString('data-unread-messages-badge', $mobileNav);
        $this->assertStringContainsString('data-unread-notifications-badge', $mobileNav);
        $this->assertStringContainsString('aria-label="Activity, Unread notifications: 1"', $mobileNav);
        $this->assertStringContainsString('aria-label="Messages, Unread messages: 2"', $mobileNav);
        $this->assertStringNotContainsString('Announcements, Unread announcements: 1', $mobileNav);
        $this->assertStringContainsString('aria-current="page"', $mobileNav);
        $this->assertSame(['home', 'search', 'create', 'activity', 'messages'], $mobileKeys[1]);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="'.preg_quote(route('user.profile', $viewer->username), '/').'"/',
            $mobileNav,
        );
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('messages.index'), '/').'"[^>]*aria-current="page"[^>]*data-mobile-nav-key="messages"/',
            $mobileNav,
        );

        $mobileProfileMenuStart = strpos($html, 'id="mobile-profile-menu"');
        $mobileNavigationStart = strpos($html, 'id="mobile-navigation"');
        $this->assertNotFalse($mobileProfileMenuStart);
        $this->assertNotFalse($mobileNavigationStart);
        $mobileProfileMenu = substr($html, $mobileProfileMenuStart, $mobileNavigationStart - $mobileProfileMenuStart);
        $this->assertStringContainsString('href="'.route('user.profile', $viewer->username).'"', $mobileProfileMenu);
        $this->assertStringNotContainsString('href="'.route('messages.index').'"', $mobileProfileMenu);
        $this->assertStringContainsString('aria-controls="mobile-profile-menu"', $html);

        $desktopRailStart = strpos($html, 'id="mobile-navigation"');
        $mobileDockStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $this->assertNotFalse($desktopRailStart);
        $desktopRail = substr($html, $desktopRailStart, $mobileDockStart - $desktopRailStart);
        $this->assertStringContainsString('href="'.route('user.profile', $viewer->username).'"', $desktopRail);
        $this->assertStringContainsString('href="'.route('announcements.index').'"', $desktopRail);
        $this->assertStringContainsString('aria-label="Unread: 1"', $desktopRail);

        $this->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('data-flow-messages="conversation"', false);
        $this->assertSame(0, $viewer->fresh()->unreadMessageCount());

        $readInbox = $this->get(route('messages.index'));
        $this->assertSame(0, $readInbox->viewData('rows')->first()['unread_count']);
        $readInbox->assertDontSee('data-conversation-unread-badge', false)
            ->assertDontSee('data-unread-messages-badge', false);
        $this->assertSame(1, $viewer->fresh()->unreadNotifications()->count());
    }

    public function test_navigation_and_inbox_cap_unread_counts_at_ninety_nine_plus(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);

        for ($index = 1; $index <= 100; $index++) {
            $conversation->messages()->create([
                'sender_id' => $counterpart->getKey(),
                'body' => 'Unread message '.$index,
            ]);
            $viewer->notify(new UserFollowed($counterpart));
        }

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertSame(100, $viewer->fresh()->unreadMessageCount());
        $this->assertSame(100, $viewer->fresh()->unreadNotifications()->count());
        $this->assertSame(100, $response->viewData('rows')->first()['unread_count']);
        $this->assertSame(2, substr_count($html, 'data-unread-messages-badge'));
        $this->assertSame(2, substr_count($html, 'data-unread-notifications-badge'));
        $this->assertSame(5, substr_count($html, '>99+</span>'));
        $this->assertSame(3, substr_count($html, 'aria-label="Unread messages: 100"'));
        $this->assertSame(2, substr_count($html, 'aria-label="Unread notifications: 100"'));
    }
}
