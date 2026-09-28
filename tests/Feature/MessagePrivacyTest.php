<?php

namespace Tests\Feature;

use App\Enums\MessagePrivacy;
use App\Enums\ModerationState;
use App\Actions\FindOrCreateDirectConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_privacy_allows_an_eligible_sender_to_start_a_conversation(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create(['locale' => 'en']);

        $this->assertSame(MessagePrivacy::Everyone, $recipient->message_privacy);

        $this->actingAs($sender)
            ->post(route('messages.start', $recipient->username))
            ->assertRedirect();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_nobody_prevents_profile_start_and_does_not_create_a_conversation(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create([
            'message_privacy' => MessagePrivacy::Nobody,
        ]);

        $this->actingAs($sender)
            ->get(route('user.profile', $recipient->username))
            ->assertOk()
            ->assertDontSee(route('messages.start', $recipient->username), false)
            ->assertDontSee(__('messages.blocked_by_recipient', [], 'en'));

        $this->postJson(route('messages.start', $recipient->username))
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_people_i_follow_requires_the_recipient_to_follow_the_sender(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create([
            'message_privacy' => MessagePrivacy::PeopleIFollow,
        ]);

        $sender->followings()->attach($recipient->getKey());

        $this->actingAs($sender)
            ->postJson(route('messages.start', $recipient->username))
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));
        $this->assertDatabaseCount('conversations', 0);

        $recipient->followings()->attach($sender->getKey());

        $this->post(route('messages.start', $recipient->username))
            ->assertRedirect();
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_privacy_changes_block_future_sends_without_deleting_history_or_rewinding_unread_state(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);
        $recipient = User::factory()->create(['locale' => 'en']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
        $existingMessage = $conversation->messages()->create([
            'sender_id' => $recipient->getKey(),
            'body' => 'History remains available.',
        ]);
        $recipient->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail()
            ->forceFill(['last_read_message_id' => $existingMessage->getKey()])
            ->save();
        $previousActivity = now()->subHour();
        $conversation->forceFill(['last_message_at' => $previousActivity])->save();

        $this->actingAs($recipient)
            ->patch(route('settings.messaging-privacy.update'), [
                'message_privacy' => MessagePrivacy::Nobody->value,
            ])
            ->assertRedirect(route('profile.edit', $recipient->username));

        $this->actingAs($sender)
            ->postJson(route('messages.start', $recipient->username))
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));
        $this->postJson(route('messages.store', $conversation), ['body' => 'Denied while private.'])
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));

        $this->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSeeText('History remains available.')
            ->assertSeeText(__('messages.unavailable', [], 'en'))
            ->assertDontSee('data-message-composer', false)
            ->assertDontSee(__('messages.blocked_by_you', [], 'en'));

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertSame(
            $previousActivity->toDateTimeString(),
            $conversation->fresh()->last_message_at->toDateTimeString(),
        );
        $this->assertSame($existingMessage->getKey(), $recipient->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->value('last_read_message_id'));

        $this->actingAs($recipient)
            ->patch(route('settings.messaging-privacy.update'), [
                'message_privacy' => MessagePrivacy::Everyone->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($sender)
            ->postJson(route('messages.store', $conversation), ['body' => 'Allowed again.'])
            ->assertCreated();

        $this->assertDatabaseCount('messages', 2);
        $this->assertSame(1, $recipient->fresh()->unreadMessageCount());
    }

    public function test_suspended_and_banned_recipients_cannot_receive_new_messages_but_history_remains_viewable(): void
    {
        $sender = User::factory()->create(['locale' => 'en']);

        foreach ([ModerationState::Suspended, ModerationState::Banned] as $state) {
            $recipient = User::factory()->create(['locale' => 'en']);
            $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);
            $conversation->messages()->create([
                'sender_id' => $recipient->getKey(),
                'body' => 'Retained '.$state->value.' history.',
            ]);
            $recipient->forceFill(['moderation_state' => $state->value])->save();

            $this->actingAs($sender)
                ->postJson(route('messages.start', $recipient->username))
                ->assertForbidden()
                ->assertJsonPath('message', __('messages.unavailable', [], 'en'));
            $this->postJson(route('messages.store', $conversation), ['body' => 'Must not be stored.'])
                ->assertForbidden()
                ->assertJsonPath('message', __('messages.unavailable', [], 'en'));
            $this->get(route('user.profile', $recipient->username))
                ->assertOk()
                ->assertDontSee(route('messages.start', $recipient->username), false);
            $this->get(route('messages.show', $conversation))
                ->assertOk()
                ->assertSeeText('Retained '.$state->value.' history.')
                ->assertSeeText(__('messages.unavailable', [], 'en'))
                ->assertDontSee('data-message-composer', false);
        }

        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_account_settings_show_localized_messaging_privacy_choices(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('profile.edit', $user->username))
            ->assertOk()
            ->assertSeeText(__('settings.messaging.heading', [], 'en'))
            ->assertSeeText(__('settings.messaging.options.everyone', [], 'en'))
            ->assertSeeText(__('settings.messaging.options.people_i_follow', [], 'en'))
            ->assertSeeText(__('settings.messaging.options.nobody', [], 'en'))
            ->assertSeeText(__('settings.messaging.descriptions.everyone', [], 'en'))
            ->assertSeeText(__('settings.messaging.descriptions.people_i_follow', [], 'en'))
            ->assertSeeText(__('settings.messaging.descriptions.nobody', [], 'en'))
            ->assertSee('name="message_privacy"', false)
            ->assertSee('value="everyone"', false)
            ->assertSee('value="people_i_follow"', false)
            ->assertSee('value="nobody"', false);

        $this->assertSame(3, substr_count($this->get(route('profile.edit', $user->username))->getContent(), 'name="message_privacy"'));

        $user->forceFill(['locale' => 'id'])->save();

        $this->get(route('profile.edit', $user->username))
            ->assertOk()
            ->assertSeeText(__('settings.messaging.heading', [], 'id'))
            ->assertSeeText(__('settings.messaging.options.everyone', [], 'id'))
            ->assertSeeText(__('settings.messaging.options.people_i_follow', [], 'id'))
            ->assertSeeText(__('settings.messaging.options.nobody', [], 'id'))
            ->assertSeeText(__('settings.messaging.descriptions.everyone', [], 'id'))
            ->assertSeeText(__('settings.messaging.descriptions.people_i_follow', [], 'id'))
            ->assertSeeText(__('settings.messaging.descriptions.nobody', [], 'id'));
    }

    public function test_restricted_user_can_change_only_their_own_message_privacy(): void
    {
        $restricted = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $other = User::factory()->create();

        $this->actingAs($restricted)
            ->patch(route('settings.messaging-privacy.update'), [
                'message_privacy' => MessagePrivacy::Nobody->value,
                'user_id' => $other->getKey(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit', $restricted->username));

        $this->get(route('profile.edit', $restricted->username))
            ->assertOk()
            ->assertSeeText(__('settings.messaging.saved', [], 'id'));

        $this->assertSame(MessagePrivacy::Nobody, $restricted->fresh()->message_privacy);
        $this->assertSame(MessagePrivacy::Everyone, $other->fresh()->message_privacy);
    }

    public function test_message_privacy_update_rejects_values_outside_the_enum(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('profile.edit', $user->username))
            ->patch(route('settings.messaging-privacy.update'), [
                'message_privacy' => 'everyone_except_someone',
            ])
            ->assertOk()
            ->assertSee('id="message-privacy-error"', false)
            ->assertSee('aria-describedby="message-privacy-error"', false);

        $this->assertSame(MessagePrivacy::Everyone, $user->fresh()->message_privacy);
    }

    public function test_new_users_default_to_everyone_message_privacy(): void
    {
        $user = User::factory()->create();

        $this->assertSame('everyone', $user->fresh()->getRawOriginal('message_privacy'));
    }
}
