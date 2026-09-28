<?php

namespace App\Enums;

enum MessageEligibility: string
{
    case Allowed = 'allowed';
    case SelfConversation = 'self_conversation';
    case SenderReadOnly = 'sender_read_only';
    case RecipientUnavailable = 'recipient_unavailable';
    case SenderBlockedRecipient = 'sender_blocked_recipient';
    case RecipientBlockedSender = 'recipient_blocked_sender';
    case PrivacyDisallows = 'privacy_disallows';
}
