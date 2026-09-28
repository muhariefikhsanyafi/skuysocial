<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Enums\ModerationState;
use App\Models\MessageBlock;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_participant_can_block_then_unblock_without_changing_conversation_history(): void
    {
        $blocker = User::factory()->create(['locale' => 'en']);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($blocker, $counterpart);
        $message = $conversation->messages()->create([
            'sender_id' => $counterpart->getKey(),
            'body' => 'Keep this history.',
        ]);
        $blockerParticipant = $blocker->conversationParticipants()
            ->where('conversation_id', $conversation->getKey())
            ->firstOrFail();
        $conversationUrl = route('messages.show', $conversation);

        $this->actingAs($blocker)
            ->post(route('messages.block', $conversation))
            ->assertRedirect($conversationUrl);
        $this->assertNull($blockerParticipant->fresh()->last_read_message_id);
        $this->assertSame(1, $blocker->fresh()->unreadMessageCount());
        $this->post(route('messages.block', $conversation))->assertRedirect($conversationUrl);
        $this->assertDatabaseCount('message_blocks', 1);

        $this->assertDatabaseHas('message_blocks', [
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $counterpart->getKey(),
        ]);

        $this->get($conversationUrl)
            ->assertOk()
            ->assertSeeText(__('messages.blocked_by_you', [], 'en'))
            ->assertSeeText(__('messages.unblock_messages', [], 'en'))
            ->assertSeeText('Keep this history.')
            ->assertDontSee('data-message-composer', false);
        $readMarkerAfterView = $blockerParticipant->fresh()->last_read_message_id;
        $this->assertSame($message->getKey(), $readMarkerAfterView);

        $this->delete(route('messages.unblock', $conversation))
            ->assertRedirect($conversationUrl);
        $this->delete(route('messages.unblock', $conversation))->assertRedirect($conversationUrl);

        $this->assertDatabaseMissing('message_blocks', [
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $counterpart->getKey(),
        ]);
        $this->assertDatabaseHas('messages', ['id' => $message->getKey(), 'body' => 'Keep this history.']);
        $this->assertSame($readMarkerAfterView, $blockerParticipant->fresh()->last_read_message_id);
    }

    public function test_either_side_block_prevents_sends_until_both_parties_unblock(): void
    {
        $first = User::factory()->create(['locale' => 'en']);
        $second = User::factory()->create(['locale' => 'en']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($first, $second);
        $history = $conversation->messages()->create([
            'sender_id' => $first->getKey(),
            'body' => 'Block state does not erase history.',
        ]);
        $conversationUrl = route('messages.show', $conversation);

        $this->actingAs($first)
            ->post(route('messages.block', $conversation))
            ->assertRedirect($conversationUrl);

        $this->actingAs($second)
            ->get(route('user.profile', $first->username))
            ->assertOk()
            ->assertDontSee(route('messages.start', $first->username), false);
        $this->postJson(route('messages.start', $first->username))
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));
        $this->get($conversationUrl)
            ->assertOk()
            ->assertSeeText(__('messages.unavailable', [], 'en'))
            ->assertSeeText('Block state does not erase history.')
            ->assertDontSeeText(__('messages.blocked_by_you', [], 'en'))
            ->assertDontSee('data-message-composer', false);
        $this->postJson(route('messages.store', $conversation), ['body' => 'Blocked recipient send'])
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));

        $this->actingAs($first)
            ->postJson(route('messages.store', $conversation), ['body' => 'Blocker send'])
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));

        $this->actingAs($second)
            ->post(route('messages.block', $conversation))
            ->assertRedirect($conversationUrl);
        $this->actingAs($first)
            ->delete(route('messages.unblock', $conversation))
            ->assertRedirect($conversationUrl);

        $this->assertDatabaseHas('message_blocks', [
            'blocker_id' => $second->getKey(),
            'blocked_id' => $first->getKey(),
        ]);
        $this->actingAs($first)
            ->get(route('user.profile', $second->username))
            ->assertOk()
            ->assertDontSee(route('messages.start', $second->username), false);
        $this->postJson(route('messages.store', $conversation), ['body' => 'Still blocked'])
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.unavailable', [], 'en'));

        $this->actingAs($second)
            ->get($conversationUrl)
            ->assertOk()
            ->assertSeeText(__('messages.blocked_by_you', [], 'en'))
            ->assertSeeText(__('messages.unblock_messages', [], 'en'));

        $this->delete(route('messages.unblock', $conversation))->assertRedirect($conversationUrl);
        $this->actingAs($first)
            ->postJson(route('messages.store', $conversation), ['body' => 'Allowed after both unblock'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['id' => $history->getKey(), 'body' => 'Block state does not erase history.']);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('message_blocks', 0);
    }

    public function test_restricted_participant_can_block_and_unblock_as_a_self_protection_control(): void
    {
        $restricted = User::factory()->create([
            'locale' => 'en',
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $counterpart = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($restricted, $counterpart);

        $this->actingAs($restricted)
            ->post(route('messages.block', $conversation))
            ->assertRedirect(route('messages.show', $conversation));

        $this->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSeeText(__('messages.read_only', [], 'en'))
            ->assertSeeText(__('messages.unblock_messages', [], 'en'))
            ->assertDontSee('data-message-composer', false);

        $this->delete(route('messages.unblock', $conversation))
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseMissing('message_blocks', [
            'blocker_id' => $restricted->getKey(),
            'blocked_id' => $counterpart->getKey(),
        ]);
    }

    public function test_nonparticipant_cannot_block_or_unblock_a_guessed_conversation(): void
    {
        $participant = User::factory()->create();
        $counterpart = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($participant, $counterpart);

        $this->actingAs($outsider)
            ->post(route('messages.block', $conversation))
            ->assertForbidden();
        $this->delete(route('messages.unblock', $conversation))
            ->assertForbidden();

        $this->assertDatabaseCount('message_blocks', 0);
    }

    public function test_self_block_is_rejected_by_the_block_management_action(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Actions\ManageMessageBlocks::class)->block($user, $user);
    }

    public function test_user_deletion_cascades_only_their_message_block_rows(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();
        $unrelated = User::factory()->create();
        $unrelatedBlock = MessageBlock::query()->create([
            'blocker_id' => $unrelated->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);
        MessageBlock::query()->create([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);

        $blocker->delete();

        $this->assertDatabaseMissing('message_blocks', [
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);
        $this->assertDatabaseHas('message_blocks', ['id' => $unrelatedBlock->getKey()]);

        $blocked->delete();

        $this->assertDatabaseMissing('message_blocks', ['id' => $unrelatedBlock->getKey()]);
    }

    public function test_database_enforces_one_directional_block_per_pair(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();
        MessageBlock::query()->create([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);

        $this->expectException(QueryException::class);
        MessageBlock::query()->create([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);
    }

    public function test_message_blocks_have_only_directional_user_relationships_and_a_unique_pair(): void
    {
        $this->assertTrue(Schema::hasTable('message_blocks'));
        $this->assertEqualsCanonicalizing(
            ['id', 'blocker_id', 'blocked_id', 'created_at', 'updated_at'],
            Schema::getColumnListing('message_blocks'),
        );
        $this->assertTrue(Schema::hasIndex('message_blocks', ['blocker_id', 'blocked_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('message_blocks', 'msg_blocks_target_owner_index'));
    }
}
