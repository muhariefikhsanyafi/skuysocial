<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Enums\ModerationState;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DirectMessagingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_key_is_canonical_and_reversed_lookup_returns_the_same_conversation(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $service = app(FindOrCreateDirectConversation::class);

        $conversation = $service->handle($first, $second);
        $reversed = $service->handle($second, $first);

        $this->assertSame($conversation->getKey(), $reversed->getKey());
        $this->assertSame(
            'direct:'.min($first->getKey(), $second->getKey()).':'.max($first->getKey(), $second->getKey()),
            $conversation->direct_key,
        );
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(2, $conversation->participants()->count());
        $this->assertEqualsCanonicalizing(
            [$first->getKey(), $second->getKey()],
            $conversation->participants()->pluck('user_id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_self_conversation_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(FindOrCreateDirectConversation::class)->handle($user, $user);
    }

    public function test_unique_direct_key_is_the_database_authority_against_duplicate_pairs(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);

        try {
            Conversation::query()->create([
                'type' => Conversation::TYPE_DIRECT,
                'direct_key' => $conversation->direct_key,
            ]);
            $this->fail('The unique direct key must reject a second conversation for the pair.');
        } catch (QueryException) {
            $this->assertSame(1, Conversation::query()->count());
        }
    }

    public function test_only_conversation_participants_can_access_the_conversation_and_its_messages(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);
        $message = $conversation->messages()->create([
            'sender_id' => $first->getKey(),
            'body' => 'A private message.',
        ]);

        $this->assertTrue(Gate::forUser($first)->allows('view', $conversation));
        $this->assertTrue(Gate::forUser($first)->allows('viewMessages', $conversation));
        $this->assertTrue(Gate::forUser($second)->allows('view', $conversation));
        $this->assertTrue(Gate::forUser($second)->allows('view', $message));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $conversation));
        $this->assertFalse(Gate::forUser($outsider)->allows('viewMessages', $conversation));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $message));
    }

    public function test_restricted_participant_remains_read_only_for_messaging(): void
    {
        $active = User::factory()->create();
        $restricted = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($active, $restricted);

        $this->assertTrue(Gate::forUser($restricted)->allows('view', $conversation));
        $this->assertTrue(Gate::forUser($restricted)->allows('viewMessages', $conversation));
        $this->assertFalse(Gate::forUser($restricted)->allows('send', $conversation));
        $this->assertTrue(Gate::forUser($active)->allows('send', $conversation));
    }

    public function test_suspended_and_banned_participants_are_blocked_by_messaging_policies(): void
    {
        foreach ([ModerationState::Suspended, ModerationState::Banned] as $state) {
            $blocked = User::factory()->create(['moderation_state' => $state->value]);
            $other = User::factory()->create();
            $conversation = app(FindOrCreateDirectConversation::class)->handle($blocked, $other);

            $this->assertFalse(Gate::forUser($blocked)->allows('view', $conversation));
            $this->assertFalse(Gate::forUser($blocked)->allows('viewMessages', $conversation));
            $this->assertFalse(Gate::forUser($blocked)->allows('send', $conversation));
        }
    }

    public function test_messages_are_ordered_by_id_and_store_five_thousand_characters_without_truncation(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);
        $body = str_repeat('x', 5000);

        $earlier = $conversation->messages()->create([
            'sender_id' => $first->getKey(),
            'body' => $body,
        ]);
        $later = $conversation->messages()->create([
            'sender_id' => $second->getKey(),
            'body' => 'Follow-up',
        ]);

        $this->assertSame(
            [$earlier->getKey(), $later->getKey()],
            $conversation->messages()->pluck('id')->all(),
        );
        $this->assertSame($body, $earlier->fresh()->body);
        $this->assertSame(5000, mb_strlen($earlier->fresh()->body));
        $this->assertEqualsCanonicalizing(
            ['id', 'conversation_id', 'sender_id', 'body', 'created_at', 'updated_at'],
            Schema::getColumnListing('messages'),
        );
    }

    public function test_account_deletion_preserves_message_history_and_removes_only_deleted_membership(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->getKey(),
            'body' => 'The sender account can be removed without losing this message.',
        ]);

        $settings = $this->actingAs($sender)->get(route('profile.edit', $sender->username));
        $settings->assertOk()
            ->assertSee(__('settings.delete.description'))
            ->assertSee('percakapan langsung');
        $this->assertStringContainsString('direct conversations', __('settings.delete.description', [], 'en'));
        $this->assertStringContainsString('percakapan langsung', __('settings.delete.description', [], 'id'));

        $this->actingAs($sender)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
                'username_confirmation' => $sender->username,
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $sender->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $recipient->getKey()]);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->getKey()]);
        $this->assertDatabaseHas('messages', [
            'id' => $message->getKey(),
            'conversation_id' => $conversation->getKey(),
            'sender_id' => null,
            'body' => 'The sender account can be removed without losing this message.',
        ]);
        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $conversation->getKey(),
            'user_id' => $sender->getKey(),
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->getKey(),
            'user_id' => $recipient->getKey(),
        ]);
        $this->assertNull($message->fresh()->sender);
    }

    public function test_no_messaging_ui_or_routes_are_exposed_in_the_foundation_milestone(): void
    {
        $messagingRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $identity = strtolower(implode(' ', array_filter([
                    $route->uri(),
                    $route->getName(),
                    $route->getActionName(),
                ])));

                return str_contains($identity, 'message') || str_contains($identity, 'conversation');
            });

        $this->assertCount(0, $messagingRoutes);
        $this->assertFileDoesNotExist(app_path('Http/Controllers/MessageController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/ConversationController.php'));
        $this->assertDirectoryDoesNotExist(resource_path('views/messages'));
        $this->assertDirectoryDoesNotExist(resource_path('js/pages/Messages'));
    }
}
