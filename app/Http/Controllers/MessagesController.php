<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MessagesController extends Controller
{
    public function index(Request $request): View
    {
        $viewerId = (int) $request->user()->getKey();

        $conversations = Conversation::query()
            ->direct()
            ->whereHas(
                'participants',
                static fn (Builder $query): Builder => $query->where('user_id', $viewerId),
            )
            ->with([
                'participants' => static fn ($query) => $query->select(['id', 'conversation_id', 'user_id']),
                'participants.user' => static fn ($query) => $query->select(['id', 'name', 'username', 'avatar', 'is_verified']),
                'latestMessage' => static fn ($query) => $query->select([
                    'messages.id',
                    'messages.conversation_id',
                    'messages.sender_id',
                    'messages.body',
                    'messages.created_at',
                ]),
            ])
            ->orderByRaw(
                'COALESCE(conversations.last_message_at, (SELECT messages.created_at FROM messages WHERE messages.conversation_id = conversations.id ORDER BY messages.id DESC LIMIT 1), conversations.created_at) DESC',
            )
            ->orderByDesc('conversations.id')
            ->simplePaginate(20);

        $rows = $conversations->getCollection()->map(static function (Conversation $conversation) use ($viewerId): array {
            $counterpart = $conversation->participants->first(
                static fn (ConversationParticipant $participant): bool => (int) $participant->user_id !== $viewerId,
            )?->user;
            $latestMessage = $conversation->latestMessage;

            return [
                'counterpart' => $counterpart,
                'preview' => $latestMessage === null ? __('messages.no_messages') : Str::squish($latestMessage->body),
                'sender_prefix' => $latestMessage !== null
                    && $latestMessage->sender_id !== null
                    && (int) $latestMessage->sender_id === $viewerId
                    ? __('messages.you_prefix')
                    : null,
                'activity_at' => $conversation->last_message_at ?? $latestMessage?->created_at ?? $conversation->created_at,
            ];
        });

        return view('messages.index', [
            'conversations' => $conversations,
            'rows' => $rows,
        ]);
    }
}
