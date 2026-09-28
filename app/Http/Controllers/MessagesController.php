<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateDirectConversation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class MessagesController extends Controller
{
    public function index(Request $request): View
    {
        $viewerId = (int) $request->user()->getKey();
        $unreadMessageCount = Message::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where(static function (Builder $query) use ($viewerId): void {
                $query->whereNull('messages.sender_id')
                    ->orWhere('messages.sender_id', '<>', $viewerId);
            })
            ->whereExists(static function ($query) use ($viewerId): void {
                $query->selectRaw('1')
                    ->from('conversation_participants as unread_participant')
                    ->whereColumn('unread_participant.conversation_id', 'messages.conversation_id')
                    ->where('unread_participant.user_id', $viewerId)
                    ->where(static function ($query): void {
                        $query->whereNull('unread_participant.last_read_message_id')
                            ->orWhereColumn('messages.id', '>', 'unread_participant.last_read_message_id');
                    });
            });

        $conversations = Conversation::query()
            ->direct()
            ->addSelect(['unread_message_count' => $unreadMessageCount])
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
                'conversation_id' => (int) $conversation->getKey(),
                'counterpart' => $counterpart,
                'preview' => $latestMessage === null ? __('messages.no_messages') : Str::squish($latestMessage->body),
                'sender_prefix' => $latestMessage !== null
                    && $latestMessage->sender_id !== null
                    && (int) $latestMessage->sender_id === $viewerId
                    ? __('messages.you_prefix')
                    : null,
                'activity_at' => $conversation->last_message_at ?? $latestMessage?->created_at ?? $conversation->created_at,
                'unread_count' => (int) $conversation->unread_message_count,
            ];
        });

        return view('messages.index', [
            'conversations' => $conversations,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        Gate::authorize('send', $conversation);

        $validated = $request->validate([
            'body' => [
                'bail',
                'required',
                'string',
                'max:5000',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && preg_match('/^\\s*$/u', $value) === 1) {
                        $fail(__('messages.validation.required'));
                    }
                },
            ],
        ], [], [
            'body' => __('messages.message'),
        ]);

        $sender = $request->user();
        $message = DB::transaction(function () use ($conversation, $sender, $validated) {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('send', $lockedConversation);

            $message = $lockedConversation->messages()->create([
                'sender_id' => $sender->getKey(),
                'body' => $validated['body'],
            ]);

            if (! $lockedConversation->forceFill(['last_message_at' => $message->created_at])->save()) {
                throw new RuntimeException('Failed to update direct conversation recency.');
            }

            return $message;
        });

        if (! $request->expectsJson()) {
            return redirect()->route('messages.show', $conversation);
        }

        return response()->json([
            'message' => [
                'id' => (int) $message->getKey(),
                'conversation_id' => (int) $message->conversation_id,
                'sender_id' => (int) $message->sender_id,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'display_time' => $message->created_at->locale(app()->getLocale())->diffForHumans(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function start(
        Request $request,
        User $user,
        FindOrCreateDirectConversation $findOrCreate,
    ): RedirectResponse {
        Gate::authorize('startDirectConversation', $user);

        $conversation = $findOrCreate->handle($request->user(), $user);

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $viewer = $request->user();
        $latestMessageId = '(SELECT MAX(messages.id) FROM messages WHERE messages.conversation_id = conversation_participants.conversation_id)';

        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $viewer->getKey())
            ->whereExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversation_participants.conversation_id');
            })
            ->whereRaw('(last_read_message_id IS NULL OR last_read_message_id < '.$latestMessageId.')')
            ->update(['last_read_message_id' => DB::raw($latestMessageId)]);

        $participants = $conversation->participants()
            ->select(['id', 'conversation_id', 'user_id'])
            ->with([
                'user' => static fn ($query) => $query->select([
                    'id',
                    'name',
                    'username',
                    'avatar',
                    'is_verified',
                ]),
            ])
            ->get();
        $counterpart = $participants
            ->first(static fn (ConversationParticipant $participant): bool => (int) $participant->user_id !== (int) $viewer->getKey())
            ?->user;

        $messages = $conversation->messages()
            ->reorder('messages.id', 'desc')
            ->select([
                'messages.id',
                'messages.conversation_id',
                'messages.sender_id',
                'messages.body',
                'messages.created_at',
            ])
            ->simplePaginate(50);
        $messages->setCollection($messages->getCollection()->reverse()->values());

        $canSend = $counterpart !== null && $viewer->can('send', $conversation);

        return view('messages.show', [
            'conversation' => $conversation,
            'counterpart' => $counterpart,
            'messages' => $messages,
            'viewerId' => (int) $viewer->getKey(),
            'canSend' => $canSend,
            'sendUnavailableReason' => $counterpart === null
                ? __('messages.deleted_unavailable')
                : (! $viewer->canPerformSocialWrites()
                    ? __('messages.read_only')
                    : (! $canSend ? __('messages.unavailable') : null)),
        ]);
    }
}
