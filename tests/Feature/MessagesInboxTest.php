<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Enums\ModerationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MessagesInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_messages_inbox(): void
    {
        $this->get('/pesan')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_a_localized_empty_state_and_active_inbox_navigation(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $response = $this->actingAs($user)->get(route('messages.index'));
        $response->assertOk()
            ->assertSeeText('Pesan')
            ->assertSeeText('Belum ada pesan');

        $html = $response->getContent();
        $inboxUrl = preg_quote(route('messages.index'), '/');
        $activeRailPattern = '/<a\s+href="'.$inboxUrl.'"[^>]*aria-current="page"[^>]*class="skuy-app-nav__link"/';
        $this->assertMatchesRegularExpression($activeRailPattern, $html);

        preg_match($activeRailPattern, $html, $activeLinkMatch, PREG_OFFSET_CAPTURE);
        $activeLinkStart = $activeLinkMatch[0][1];
        $activeLinkEnd = strpos($html, '</a>', $activeLinkStart);
        $activeLink = substr($html, $activeLinkStart, $activeLinkEnd - $activeLinkStart);
        $this->assertStringContainsString('fa-comment-lines', $activeLink);
        $this->assertStringContainsString('Pesan', strip_tags($activeLink));

        $mobileProfileMenuStart = strpos($html, 'id="mobile-profile-menu"');
        $mobileNavigationStart = strpos($html, 'id="mobile-navigation"');
        $this->assertNotFalse($mobileProfileMenuStart);
        $this->assertNotFalse($mobileNavigationStart);
        $mobileProfileMenu = substr($html, $mobileProfileMenuStart, $mobileNavigationStart - $mobileProfileMenuStart);
        $this->assertStringContainsString(
            'href="'.route('messages.index').'" class="skuy-menu-link" aria-current="page"',
            $mobileProfileMenu,
        );
    }

    public function test_inbox_shows_only_conversations_for_the_authenticated_participant(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create(['name' => 'Visible Inbox Counterpart']);
        $unrelatedOwner = User::factory()->create();
        $unrelatedCounterpart = User::factory()->create(['name' => 'Private Other Counterpart']);
        $findOrCreate = app(FindOrCreateDirectConversation::class);

        $findOrCreate->handle($viewer, $counterpart);
        $findOrCreate->handle($unrelatedOwner, $unrelatedCounterpart);

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk()
            ->assertSeeText($counterpart->name)
            ->assertDontSeeText($unrelatedCounterpart->name);

        $this->assertSame('No messages yet', $response->viewData('rows')->first()['preview']);

        $loadedConversation = $response->viewData('conversations')->first();
        $this->assertTrue($loadedConversation->relationLoaded('participants'));
        $this->assertTrue($loadedConversation->relationLoaded('latestMessage'));
        $this->assertTrue(
            $loadedConversation->participants
                ->firstWhere('user_id', $counterpart->getKey())
                ->relationLoaded('user'),
        );
    }

    public function test_latest_message_preview_is_squished_escaped_and_prefixed_for_the_viewer(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $conversation->messages()->create([
            'sender_id' => $viewer->getKey(),
            'body' => "<b>First\n\t & last</b>",
        ]);

        $response = $this->actingAs($viewer)->get(route('messages.index'));

        $response->assertOk()
            ->assertSeeText('You:')
            ->assertSee('&lt;b&gt;First &amp; last&lt;/b&gt;', false)
            ->assertDontSee('<b>First & last</b>', false);

        $this->assertSame('<b>First & last</b>', $response->viewData('rows')->first()['preview']);
    }

    public function test_counterpart_identity_uses_public_profile_data_and_verification(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create([
            'name' => 'Verified Inbox Friend',
            'username' => 'verified_friend',
            'avatar' => 'avatars/verified-friend.webp',
            'is_verified' => true,
        ]);
        app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);

        $response = $this->actingAs($viewer)->get(route('messages.index'));

        $response->assertOk()
            ->assertSeeText('Verified Inbox Friend')
            ->assertSeeText('@verified_friend')
            ->assertSee('src="'.asset('storage/avatars/verified-friend.webp').'"', false)
            ->assertSee(__('threads.verified'))
            ->assertSee(route('user.profile', 'verified_friend'), false);
    }

    public function test_conversations_are_ordered_by_activity_with_a_stable_creation_fallback(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $lastMessageAtCounterpart = User::factory()->create(['name' => 'Last Message At Counterpart']);
        $messageTimestampCounterpart = User::factory()->create(['name' => 'Message Timestamp Counterpart']);
        $quietCounterpart = User::factory()->create(['name' => 'Quiet Counterpart']);
        $findOrCreate = app(FindOrCreateDirectConversation::class);
        $now = now();

        $lastMessageAtConversation = $findOrCreate->handle($viewer, $lastMessageAtCounterpart);
        $messageTimestampConversation = $findOrCreate->handle($viewer, $messageTimestampCounterpart);
        $quietConversation = $findOrCreate->handle($viewer, $quietCounterpart);

        $lastMessageAtConversation->forceFill([
            'created_at' => $now->copy()->subDays(2),
            'last_message_at' => $now->copy()->subHour(),
        ])->save();
        $lastMessageAt = $lastMessageAtConversation->messages()->create([
            'sender_id' => $viewer->getKey(),
            'body' => 'last_message_at controls ordering',
        ]);
        $lastMessageAt->forceFill(['created_at' => $now->copy()->subHours(5)])->save();

        $messageTimestampConversation->forceFill([
            'created_at' => $now->copy()->subDays(3),
            'last_message_at' => null,
        ])->save();
        $latestMessage = $messageTimestampConversation->messages()->create([
            'sender_id' => $messageTimestampCounterpart->getKey(),
            'body' => 'latest message timestamp is the fallback',
        ]);
        $latestMessage->forceFill(['created_at' => $now->copy()->subMinutes(30)])->save();

        $quietConversation->forceFill([
            'created_at' => $now->copy()->subDays(4),
            'last_message_at' => null,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk();

        $this->assertSame(
            [
                $messageTimestampCounterpart->name,
                $lastMessageAtCounterpart->name,
                $quietCounterpart->name,
            ],
            $response->viewData('rows')->pluck('counterpart.name')->all(),
        );
    }

    public function test_deleted_counterpart_uses_generic_identity_without_a_profile_link_and_keeps_history(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $deletedCounterpart = User::factory()->create(['name' => 'Removed Person']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $deletedCounterpart);
        $message = $conversation->messages()->create([
            'sender_id' => $deletedCounterpart->getKey(),
            'body' => 'Retained message history',
        ]);
        $deletedProfileUrl = route('user.profile', $deletedCounterpart->username);
        $deletedCounterpart->delete();

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk()
            ->assertSeeText('Deleted account')
            ->assertSeeText('Retained message history')
            ->assertDontSee('You:', false)
            ->assertDontSee($deletedProfileUrl, false);

        $this->assertDatabaseHas('messages', [
            'id' => $message->getKey(),
            'sender_id' => null,
        ]);
        $this->assertNull($response->viewData('rows')->first()['counterpart']);
    }

    public function test_restricted_read_only_user_can_view_the_inbox(): void
    {
        $restricted = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $response = $this->actingAs($restricted)->get(route('messages.index'));

        $response->assertOk()
            ->assertSeeText(__('messages.title'))
            ->assertSee('skuy-shell-notice--content', false);
    }

    public function test_inbox_route_is_the_only_messaging_surface_and_is_read_only(): void
    {
        $route = Route::getRoutes()->getByName('messages.index');

        $this->assertNotNull($route);
        $this->assertSame('pesan', $route->uri());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());

        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);
        $this->assertNotContains('moderation.active', $middleware);

        $messagingRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static function ($registeredRoute): bool {
                $uri = strtolower($registeredRoute->uri());
                $name = strtolower($registeredRoute->getName() ?? '');

                return str_contains($uri, 'pesan')
                    || str_contains($uri, 'message')
                    || str_contains($uri, 'conversation')
                    || str_contains($name, 'messages')
                    || str_contains($name, 'conversation');
            })
            ->pluck('action.as')
            ->all();

        $this->assertSame(['messages.index'], $messagingRoutes);
    }

    public function test_inbox_uses_twenty_conversation_pages(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $findOrCreate = app(FindOrCreateDirectConversation::class);

        for ($index = 0; $index < 21; $index++) {
            $findOrCreate->handle(
                $viewer,
                User::factory()->create(['name' => 'Inbox Counterpart '.$index]),
            );
        }

        $firstPage = $this->actingAs($viewer)->get(route('messages.index'));
        $firstPage->assertOk();
        $this->assertCount(20, $firstPage->viewData('rows'));
        $this->assertTrue($firstPage->viewData('conversations')->hasMorePages());

        $secondPage = $this->get(route('messages.index').'?page=2');
        $secondPage->assertOk();
        $this->assertCount(1, $secondPage->viewData('rows'));
        $this->assertFalse($secondPage->viewData('conversations')->hasMorePages());
    }
}
