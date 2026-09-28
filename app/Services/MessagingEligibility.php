<?php

namespace App\Services;

use App\Enums\MessageEligibility;
use App\Enums\MessagePrivacy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessagingEligibility
{
    public function evaluate(User $sender, User $recipient): MessageEligibility
    {
        $senderId = (int) $sender->getKey();
        $recipientId = (int) $recipient->getKey();

        if ($senderId === $recipientId) {
            return MessageEligibility::SelfConversation;
        }

        $sender = $sender->fresh() ?? $sender;
        $recipient = $recipient->fresh() ?? $recipient;

        if (! $sender->canPerformSocialWrites()) {
            return MessageEligibility::SenderReadOnly;
        }

        if (! $recipient->canMaintainAuthenticatedSession()) {
            return MessageEligibility::RecipientUnavailable;
        }

        if ($this->hasBlock($sender, $recipient)) {
            return MessageEligibility::SenderBlockedRecipient;
        }

        if ($this->hasBlock($recipient, $sender)) {
            return MessageEligibility::RecipientBlockedSender;
        }

        return match ($recipient->message_privacy) {
            MessagePrivacy::Everyone => MessageEligibility::Allowed,
            MessagePrivacy::PeopleIFollow => DB::table('follows')
                ->where('follower_id', $recipientId)
                ->where('following_id', $senderId)
                ->exists()
                ? MessageEligibility::Allowed
                : MessageEligibility::PrivacyDisallows,
            MessagePrivacy::Nobody => MessageEligibility::PrivacyDisallows,
            default => MessageEligibility::RecipientUnavailable,
        };
    }

    public function hasBlock(User $blocker, User $blocked): bool
    {
        return DB::table('message_blocks')
            ->where('blocker_id', $blocker->getKey())
            ->where('blocked_id', $blocked->getKey())
            ->exists();
    }
}
