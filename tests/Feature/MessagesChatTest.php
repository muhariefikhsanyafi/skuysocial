<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessagesChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_show_start_or_send_a_direct_conversation(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);

        $this->get('/pesan/'.$conversation->getKey())
            ->assertRedirect(route('login'));
        $this->post('/pesan/'.$conversation->getKey(), ['body' => 'Hello'])
            ->assertRedirect(route('login'));
        $this->post('/pesan/mulai/'.$second->username)
            ->assertRedirect(route('login'));
    }

    public function test_participants_can_view_conversation_history_oldest_first(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create(['name' => 'Chat Counterpart', 'username' => 'chat_friend']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);
        $conversation->messages()->create([
            'sender_id' => $counterpart->getKey(),
            'body' => '<b>First message</b>',
        ]);
        $conversation->messages()->create([
            'sender_id' => $viewer->getKey(),
            'body' => "Second\nmessage",
        ]);

        $response = $this->actingAs($viewer)->get('/pesan/'.$conversation->getKey());

        $response->assertOk()
            ->assertSeeText('Chat Counterpart')
            ->assertSeeText('@chat_friend')
            ->assertSee('&lt;b&gt;First message&lt;/b&gt;', false)
            ->assertDontSee('<b>First message</b>', false)
            ->assertSeeText("Second\nmessage");
        $messagesUrl = preg_quote(route('messages.index'), '/');
        $activeMessagesLink = '/<a\\s+href="'.$messagesUrl.'"[^>]*aria-current="page"[^>]*class="skuy-app-nav__link"/';
        $this->assertMatchesRegularExpression($activeMessagesLink, $response->getContent());
        $this->assertSame(
            ['<b>First message</b>', "Second\nmessage"],
            $response->viewData('messages')->pluck('body')->all(),
        );
    }

    public function test_nonparticipant_cannot_view_a_guessed_conversation_id(): void
    {
        $participant = User::factory()->create();
        $counterpart = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($participant, $counterpart);

        $this->actingAs($outsider)
            ->get('/pesan/'.$conversation->getKey())
            ->assertForbidden();
    }

    public function test_history_is_bounded_to_fifty_and_older_messages_remain_reachable(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $counterpart);

        for ($index = 1; $index <= 55; $index++) {
            $conversation->messages()->create([
                'sender_id' => $index % 2 === 0 ? $viewer->getKey() : $counterpart->getKey(),
                'body' => 'History '.$index,
            ]);
        }

        $pageOne = $this->actingAs($viewer)->get(route('messages.show', $conversation));
        $pageOne->assertOk()
            ->assertSeeText(__('messages.older_messages'))
            ->assertSee(route('messages.show', $conversation).'?page=2', false);
        $this->assertCount(50, $pageOne->viewData('messages'));
        $this->assertSame(
            array_map(static fn (int $index): string => 'History '.$index, range(6, 55)),
            $pageOne->viewData('messages')->pluck('body')->all(),
        );
        $this->assertTrue($pageOne->viewData('messages')->hasMorePages());

        $pageTwo = $this->get(route('messages.show', $conversation).'?page=2');
        $pageTwo->assertOk()->assertSeeText(__('messages.newer_messages'));
        $this->assertSame(
            array_map(static fn (int $index): string => 'History '.$index, range(1, 5)),
            $pageTwo->viewData('messages')->pluck('body')->all(),
        );
        $this->assertFalse($pageTwo->viewData('messages')->hasMorePages());
    }

    public function test_active_user_can_start_and_reuse_the_same_direct_conversation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $path = '/pesan/mulai/'.$recipient->username;

        $firstResponse = $this->actingAs($sender)->post($path);
        $this->assertDatabaseCount('conversations', 1);
        $conversation = $sender->conversations()->firstOrFail();
        $expectedUrl = route('messages.show', $conversation);
        $firstResponse->assertRedirect($expectedUrl);

        $this->post($path)->assertRedirect($expectedUrl);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_user_cannot_start_a_conversation_with_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/pesan/mulai/'.$user->username)
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_missing_target_username_cannot_start_a_conversation(): void
    {
        $sender = User::factory()->create();

        $this->actingAs($sender)
            ->post('/pesan/mulai/not_a_real_account')
            ->assertNotFound();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_restricted_recipient_can_be_messaged_but_inactive_targets_cannot(): void
    {
        $sender = User::factory()->create();
        $restrictedRecipient = User::factory()->create([
            'moderation_state' => 'restricted',
        ]);

        $this->actingAs($sender)
            ->post('/pesan/mulai/'.$restrictedRecipient->username)
            ->assertRedirect();
        $this->assertDatabaseCount('conversations', 1);

        foreach (['suspended', 'banned'] as $state) {
            $inactiveRecipient = User::factory()->create(['moderation_state' => $state]);

            $this->post('/pesan/mulai/'.$inactiveRecipient->username)
                ->assertForbidden();
        }

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_profile_message_action_is_available_only_on_another_eligible_profile(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create(['username' => 'message_target']);

        $ownProfile = $this->actingAs($viewer)->get(route('user.profile', $viewer->username));
        $ownProfile->assertOk()
            ->assertDontSee(route('messages.start', $viewer->username), false);

        $recipientProfile = $this->get(route('user.profile', $recipient->username));
        $recipientProfile->assertOk()
            ->assertSeeText('Message')
            ->assertSee(route('messages.start', $recipient->username), false)
            ->assertSee('method="POST"', false);
    }

    public function test_profile_message_action_respects_sender_and_recipient_account_states(): void
    {
        $active = User::factory()->create(['locale' => 'en']);
        $restrictedTarget = User::factory()->create(['moderation_state' => 'restricted']);
        $inactiveTarget = User::factory()->create(['moderation_state' => 'suspended']);

        $this->actingAs($active)
            ->get(route('user.profile', $restrictedTarget->username))
            ->assertSee(route('messages.start', $restrictedTarget->username), false);
        $this->get(route('user.profile', $inactiveTarget->username))
            ->assertDontSee(route('messages.start', $inactiveTarget->username), false);

        $restrictedSender = User::factory()->create([
            'locale' => 'en',
            'moderation_state' => 'restricted',
        ]);
        $this->actingAs($restrictedSender)
            ->get(route('user.profile', $restrictedTarget->username))
            ->assertDontSee(route('messages.start', $restrictedTarget->username), false);
    }

    public function test_active_participant_sends_a_safe_json_message_as_the_authenticated_sender(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create();
        $spoofer = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $previousActivity = now()->subDay();
        $conversation->forceFill(['last_message_at' => $previousActivity])->save();
        $body = "Line one\n<script>alert('not HTML')</script>";
        Notification::fake();

        $response = $this->actingAs($sender)->postJson('/pesan/'.$conversation->getKey(), [
            'body' => $body,
            'sender_id' => $spoofer->getKey(),
            'participant_id' => $spoofer->getKey(),
            'created_at' => now()->subYears(10)->toIso8601String(),
            'last_message_at' => now()->subYears(10)->toIso8601String(),
        ]);

        $response->assertCreated();
        $message = Message::query()->firstOrFail();
        $payload = $response->json();
        $this->assertSame(['message'], array_keys($payload));
        $this->assertEqualsCanonicalizing(
            ['id', 'conversation_id', 'sender_id', 'body', 'created_at', 'display_time'],
            array_keys($payload['message']),
        );
        $this->assertSame((int) $message->getKey(), $payload['message']['id']);
        $this->assertSame((int) $conversation->getKey(), $payload['message']['conversation_id']);
        $this->assertSame((int) $sender->getKey(), $payload['message']['sender_id']);
        $this->assertSame($body, $payload['message']['body']);
        $this->assertSame($message->created_at->toIso8601String(), $payload['message']['created_at']);
        $this->assertIsString($payload['message']['display_time']);
        $this->assertSame($body, $message->body);
        $this->assertEquals($message->created_at, $conversation->fresh()->last_message_at);
        Notification::assertNothingSent();
    }

    public function test_whitespace_only_message_is_rejected(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);

        $this->actingAs($sender)
            ->postJson('/pesan/'.$conversation->getKey(), ['body' => " \t\r\n"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_unicode_separator_only_message_is_rejected(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);

        $this->actingAs($sender)
            ->postJson('/pesan/'.$conversation->getKey(), ['body' => "\u{00A0}\u{2003}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_longer_than_five_thousand_characters_is_rejected_without_truncation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $body = str_repeat('x', 5001);

        $this->actingAs($sender)
            ->postJson('/pesan/'.$conversation->getKey(), ['body' => $body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_five_thousand_multibyte_characters_are_stored_without_truncation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $body = str_repeat('🙂', 5000);

        $response = $this->actingAs($sender)->postJson('/pesan/'.$conversation->getKey(), ['body' => $body]);
        $response->assertCreated();
        $this->assertSame($body, $response->json('message.body'));
        $this->assertSame(5000, mb_strlen($response->json('message.body')));
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_nonparticipant_cannot_send_using_a_guessed_conversation_id(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);

        $this->actingAs($outsider)
            ->postJson('/pesan/'.$conversation->getKey(), ['body' => 'Not allowed'])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_restricted_participant_can_read_but_cannot_start_or_send(): void
    {
        $restricted = User::factory()->create([
            'locale' => 'en',
            'moderation_state' => 'restricted',
        ]);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($restricted, $counterpart);

        $this->actingAs($restricted)
            ->get('/pesan/'.$conversation->getKey())
            ->assertOk()
            ->assertSeeText(__('messages.read_only'))
            ->assertDontSee('data-message-composer', false);

        $this->postJson('/pesan/'.$conversation->getKey(), ['body' => 'No send'])
            ->assertForbidden();
        $this->post('/pesan/mulai/'.User::factory()->create()->username)
            ->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_deleted_counterpart_history_remains_readable_but_cannot_receive_new_messages(): void
    {
        $viewer = User::factory()->create(['locale' => 'en']);
        $deletedCounterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $deletedCounterpart);
        $retainedMessage = $conversation->messages()->create([
            'sender_id' => $deletedCounterpart->getKey(),
            'body' => 'History remains after account deletion.',
        ]);
        $deletedProfileUrl = route('user.profile', $deletedCounterpart->username);
        $deletedCounterpart->delete();

        $response = $this->actingAs($viewer)->get('/pesan/'.$conversation->getKey());
        $response->assertOk()
            ->assertSeeText(__('messages.deleted_account'))
            ->assertSeeText(__('messages.deleted_unavailable'))
            ->assertSeeText('History remains after account deletion.')
            ->assertDontSee('data-message-composer', false)
            ->assertDontSee($deletedProfileUrl, false)
            ->assertSee('skuy-direct-chat-message-row--other', false);

        $this->postJson('/pesan/'.$conversation->getKey(), ['body' => 'Cannot send to deleted account'])
            ->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', [
            'id' => $retainedMessage->getKey(),
            'sender_id' => null,
        ]);
    }

    public function test_active_conversation_has_a_valid_multiline_form_fallback(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $conversationUrl = route('messages.show', $conversation);

        $page = $this->actingAs($sender)->get($conversationUrl);
        $page->assertOk()
            ->assertSee(route('messages.store', $conversation), false)
            ->assertSee('data-message-composer', false)
            ->assertSee('name="body"', false)
            ->assertSee('maxlength="5000"', false)
            ->assertSeeText(__('messages.send'));

        $body = "First line\nSecond line";
        $this->post(route('messages.store', $conversation), ['body' => $body])
            ->assertRedirect($conversationUrl);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'body' => $body,
        ]);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }
}
