@if (auth()->check() && (int) auth()->id() === (int) $comment->user_id)
    <div x-data="{ editing: false }" data-comment-edit="{{ $comment->getKey() }}">
        <p x-show="!editing" class="skuy-comment__text">{{ $comment->content }}</p>
        <button
            type="button"
            x-show="!editing"
            @click="editing = true; $nextTick(() => $refs.commentContent.focus())"
            class="skuy-comment__reply"
            :aria-expanded="editing.toString()"
        >{{ __('threads.actions.edit') }}</button>
        <form
            x-show="editing"
            style="display: none;"
            method="POST"
            action="{{ route('comments.update', $comment) }}"
            class="mt-2 flex flex-col gap-2"
        >
            @csrf
            @method('PATCH')
            <textarea
                x-ref="commentContent"
                name="content"
                rows="2"
                required
                maxlength="500"
                class="skuy-inline-input w-full"
                aria-label="{{ __('threads.actions.edit') }}"
            >{{ $comment->content }}</textarea>
            <div class="flex items-center gap-2">
                <button type="submit" class="skuy-button skuy-button--primary min-h-[36px] px-3 py-1 text-xs">{{ __('threads.actions.save_changes') }}</button>
                <button type="button" @click="editing = false" class="skuy-comment__reply">{{ __('threads.actions.cancel') }}</button>
            </div>
        </form>
    </div>
@else
    <p class="skuy-comment__text">{{ $comment->content }}</p>
@endif
