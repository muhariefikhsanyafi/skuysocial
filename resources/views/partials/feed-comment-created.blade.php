@php
    $isReply = $comment->parent_id !== null;
    $avatarSize = $isReply ? 'tiny' : 'small';
    $avatarLabel = __('threads.media.avatar_of', ['name' => $comment->user->name]);
@endphp

<div
    @unless ($isReply) x-data="{ openReply: false }" @endunless
    data-feed-comment-id="{{ $comment->getKey() }}"
    class="skuy-comment"
>
    @if ($comment->user->avatar)
        <div class="skuy-avatar skuy-avatar--{{ $avatarSize }}">
            <img src="{{ asset('storage/' . $comment->user->avatar) }}" alt="{{ $avatarLabel }}">
        </div>
    @else
        <div class="skuy-avatar skuy-avatar--{{ $avatarSize }}" aria-label="{{ $avatarLabel }}">
            {{ substr($comment->user->name, 0, 1) }}
        </div>
    @endif

    <div class="skuy-comment__body">
        <div class="skuy-comment__identity">
            <span class="skuy-comment__user"><span aria-hidden="true">@</span>{{ $comment->user->username }}</span>
            <time datetime="{{ $comment->created_at->toIso8601String() }}" class="skuy-comment__time">
                {{ $comment->created_at->locale(app()->getLocale())->diffForHumans(null, true) }}
            </time>
        </div>
        <p class="skuy-comment__text">{{ $comment->content }}</p>

        @unless ($isReply)
            <button @click="openReply = !openReply" type="button" :aria-expanded="openReply.toString()" class="skuy-comment__reply">
                {{ __('threads.actions.reply') }}
            </button>

            <div x-show="openReply" style="display: none;" class="mt-2">
                <form
                    action="{{ route('comments.store', $displayPost) }}"
                    method="POST"
                    class="skuy-inline-form"
                    x-data="feedCommentComposer({
                        url: @js(route('comments.store', $displayPost)),
                        postUid: @js((string) $displayPost->uid),
                        parentId: @js((int) $comment->getKey()),
                        labels: {
                            sessionExpired: @js(__('threads.comment_feedback.session_expired')),
                            restricted: @js(__('moderation.denials.social_write')),
                            invalid: @js(__('threads.comment_feedback.invalid')),
                            failed: @js(__('threads.comment_feedback.failed')),
                            network: @js(__('threads.comment_feedback.network')),
                        },
                    })"
                    @submit.prevent="submit($event)"
                >
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $comment->getKey() }}">
                    <input
                        x-model="content"
                        type="text"
                        name="content"
                        class="skuy-inline-input"
                        placeholder="{{ __('threads.comments.reply_to', ['name' => $comment->user->name]) }}"
                        required
                        maxlength="500"
                        autocomplete="off"
                        :disabled="pending"
                    >
                    <button
                        type="submit"
                        :disabled="pending || content.trim() === ''"
                        :aria-busy="pending.toString()"
                        class="skuy-button skuy-button--primary min-h-[40px] px-3 py-1 text-xs"
                    >
                        <span x-show="!pending">{{ __('threads.actions.send') }}</span>
                        <span x-show="pending">...</span>
                    </button>
                    <p x-show="error" x-text="error" style="display: none;" class="basis-full text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive"></p>
                </form>
            </div>
        @endunless
    </div>
</div>
