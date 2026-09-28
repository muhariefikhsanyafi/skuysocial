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

        $ownedConversation = $findOrCreate->handle($viewer, $counterpart);
        $findOrCreate->handle($unrelatedOwner, $unrelatedCounterpart);

        $response = $this->actingAs($viewer)->get(route('messages.index'));
        $response->assertOk()
            ->assertSeeText($counterpart->name)
            ->assertDontSeeText($unrelatedCounterpart->name)
            ->assertSee('href="'.route('messages.show', $ownedConversation).'"', false);

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
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);

        $response = $this->actingAs($viewer)->get(route('messages.index'));

        $response->assertOk()
            ->assertSeeText('Verified Inbox Friend')
            ->assertSeeText('@verified_friend')
            ->assertSee('src="'.asset('storage/avatars/verified-friend.webp').'"', false)
            ->assertSee(__('threads.verified'))
            ->assertSee(route('messages.show', $conversation), false);
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

    public function test_messaging_routes_are_authenticated_web_routes_with_scoped_write_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static function ($route): bool {
                $uri = strtolower($route->uri());
                $name = strtolower($route->getName() ?? '');

                return str_contains($uri, 'pesan') || str_contains($name, 'messages');
            });

        $this->assertEqualsCanonicalizing(
            ['messages.index', 'messages.start', 'messages.store', 'messages.show'],
            $routes->pluck('action.as')->all(),
        );
        $this->assertCount(0, $routes->filter(
            static fn ($route): bool => str_starts_with($route->uri(), 'api/'),
        ));

        foreach (['messages.index', 'messages.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('verified', $route->gatherMiddleware());
            $this->assertNotContains('moderation.active', $route->gatherMiddleware());
        }

        foreach (['messages.start', 'messages.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('verified', $route->gatherMiddleware());
            $this->assertContains('moderation.active', $route->gatherMiddleware());
        }

        $routeNamesInOrder = collect(Route::getRoutes()->getRoutes())
            ->pluck('action.as')
            ->filter(static fn (?string $name): bool => in_array($name, ['messages.start', 'messages.show'], true))
            ->values()
            ->all();
        $this->assertSame(['messages.start', 'messages.show'], $routeNamesInOrder);
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
