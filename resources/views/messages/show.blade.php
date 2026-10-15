<x-app-layout>
    <div class="skuy-feed-page skuy-messages-page skuy-direct-chat-page" data-flow-messages="conversation">
        <div class="skuy-feed-content skuy-messages-content skuy-direct-chat-content mx-auto w-full max-w-2xl px-4 py-6 sm:px-6 sm:py-8">
            <header class="skuy-direct-chat-header">
                <a href="{{ route('messages.index') }}" class="skuy-direct-chat-back">
                    <x-fa-icon name="arrow-left-long" />
                    <span>{{ __('messages.back_to_inbox') }}</span>
                </a>

                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="skuy-direct-chat-identity">
                        <div class="skuy-avatar skuy-direct-chat-avatar" aria-hidden="true">
                        @if ($counterpart && filled($counterpart->avatar))
                            <img src="{{ asset('storage/' . $counterpart->avatar) }}" alt="">
                        @elseif ($counterpart)
                            <span>{{ mb_substr($counterpart->name, 0, 1) }}</span>
                        @else
                            <x-fa-icon name="user" />
                        @endif
                    </div>

                    <div class="skuy-direct-chat-identity__copy">
                        <h1 class="skuy-direct-chat-title">
                            @if ($counterpart)
                                <a href="{{ route('user.profile', $counterpart->username) }}">{{ $counterpart->name }}</a>
                            @else
                                <span>{{ __('messages.deleted_account') }}</span>
                            @endif

                            @if ($counterpart?->is_verified)
                                <span class="skuy-profile-verified" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path>
                                    </svg>
                                </span>
                            @endif
                        </h1>
                        @if ($counterpart)
                            <p class="skuy-direct-chat-username">{{ '@' . $counterpart->username }}</p>
                        @endif
                    </div>
                </div>

                @if ($counterpart)
                    @if ($isBlockingCounterpart)
                        <form action="{{ route('messages.unblock', $conversation) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="{{ __('messages.unblock_messages') }}" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[var(--radius-medium)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] px-4 py-2 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] sm:w-auto">
                                <x-fa-icon name="lock-open" weight="solid" />
                                <span>{{ __('messages.unblock_messages') }}</span>
                            </button>
                        </form>
                    @else
                        <form action="{{ route('messages.block', $conversation) }}" method="POST">
                            @csrf
                            <button type="submit" aria-label="{{ __('messages.block_messages') }}" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[var(--radius-medium)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] px-4 py-2 text-sm font-semibold text-[var(--skuy-danger)] transition-colors hover:bg-[var(--skuy-surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] sm:w-auto">
                                <x-fa-icon name="ban" weight="solid" />
                                <span>{{ __('messages.block_messages') }}</span>
                            </button>
                        </form>
                    @endif
                @endif
                </div>
            </header>

            @if ($messages->previousPageUrl() || $messages->nextPageUrl())
                <nav class="skuy-direct-chat-history-nav" aria-label="{{ __('messages.history_navigation') }}">
                    @if ($messages->nextPageUrl())
                        <a href="{{ $messages->nextPageUrl() }}">{{ __('messages.older_messages') }}</a>
                    @endif
                    @if ($messages->previousPageUrl())
                        <a href="{{ $messages->previousPageUrl() }}">{{ __('messages.newer_messages') }}</a>
                    @endif
                </nav>
            @endif

            <ol class="skuy-direct-chat-history" data-message-history role="log" aria-live="polite" aria-relevant="additions" aria-label="{{ __('messages.message_history') }}">
                @forelse ($messages as $message)
                    @php($isOwnMessage = $message->sender_id !== null && (int) $message->sender_id === $viewerId)
                    <li class="skuy-direct-chat-message-row{{ $isOwnMessage ? ' skuy-direct-chat-message-row--own' : ' skuy-direct-chat-message-row--other' }}">
                        <article class="skuy-direct-chat-message" data-message-id="{{ $message->id }}">
                            <p class="skuy-direct-chat-message__body">{{ $message->body }}</p>
                            <time class="skuy-direct-chat-message__time" datetime="{{ $message->created_at->toIso8601String() }}">
                                {{ $message->created_at->locale(app()->getLocale())->diffForHumans() }}
                            </time>
                        </article>
                    </li>
                @empty
                    <li class="skuy-direct-chat-empty" role="status">{{ __('messages.no_messages') }}</li>
                @endforelse
            </ol>

            @if ($canSend)
                <form
                    action="{{ route('messages.store', $conversation) }}"
                    method="POST"
                    class="skuy-direct-chat-composer"
                    data-message-composer
                    data-conversation-id="{{ $conversation->getKey() }}"
                    data-sender-id="{{ $viewerId }}"
                    data-send-label="{{ __('messages.send') }}"
                    data-sending-label="{{ __('messages.sending') }}"
                    data-sent-label="{{ __('messages.message_sent') }}"
                    data-session-expired="{{ __('messages.feedback.session_expired') }}"
                    data-restricted="{{ __('messages.feedback.restricted') }}"
                    data-unavailable="{{ __('messages.unavailable') }}"
                    data-validation="{{ __('messages.feedback.validation') }}"
                    data-failed="{{ __('messages.feedback.failed') }}"
                    data-network="{{ __('messages.feedback.network') }}"
                >
                    @csrf
                    <label for="message-body" class="sr-only">{{ __('messages.message') }}</label>
                    <textarea
                        id="message-body"
                        name="body"
                        rows="3"
                        maxlength="5000"
                        required
                        placeholder="{{ __('messages.composer_placeholder') }}"
                        aria-describedby="message-feedback"
                        aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}"
                        class="skuy-direct-chat-composer__input"
                    >{{ old('body') }}</textarea>
                    <p id="message-feedback" class="skuy-direct-chat-feedback" data-message-feedback role="status" aria-live="polite" @if (! $errors->has('body')) hidden @endif>{{ $errors->first('body') }}</p>
                    <div class="skuy-direct-chat-composer__actions">
                        <button type="submit" class="skuy-direct-chat-submit" data-message-submit>
                            {{ __('messages.send') }}
                        </button>
                    </div>
                </form>
            @else
                <p class="skuy-direct-chat-readonly" role="status">{{ $sendUnavailableReason }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
