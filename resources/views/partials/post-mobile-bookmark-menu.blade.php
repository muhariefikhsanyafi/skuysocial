@php
    $mobileBookmarkActive = (bool) ($displayPost->bookmarked_by_me ?? $displayPost->bookmarked_by_current_user ?? false);
@endphp

<div
    x-data="{
        openOptions: false,
        postUid: @js((string) $displayPost->uid),
        bookmarkState: @js($mobileBookmarkActive),
        closeOptions(restoreFocus = false) {
            this.openOptions = false;

            if (restoreFocus) {
                this.$nextTick(() => this.$refs.optionsTrigger?.focus());
            }
        },
        syncBookmarkState(event) {
            const state = event?.detail;
            if (state?.postUid !== this.postUid || typeof state.bookmarked !== 'boolean') return;

            const changed = this.bookmarkState !== state.bookmarked;
            this.bookmarkState = state.bookmarked;
            if (changed) this.closeOptions(true);
        }
    }"
    @keydown.escape.window="if (openOptions) closeOptions(true)"
    @skuy-post-action-state.window="syncBookmarkState($event)"
    class="skuy-post__options skuy-post__options--mobile-only"
    data-mobile-post-options="{{ $displayPost->uid }}"
>
    <button
        id="post-mobile-options-trigger-{{ $displayPost->uid }}"
        x-ref="optionsTrigger"
        @click="openOptions = !openOptions"
        type="button"
        aria-label="{{ __('threads.actions.open_options') }}"
        aria-haspopup="menu"
        aria-expanded="false"
        :aria-expanded="openOptions"
        aria-controls="post-mobile-options-{{ $displayPost->uid }}"
        class="skuy-action"
    >
        <x-fa-icon name="ellipsis" class="text-xl" />
    </button>

    <div
        id="post-mobile-options-{{ $displayPost->uid }}"
        x-show="openOptions"
        @click.away="closeOptions()"
        style="display: none;"
        role="menu"
        aria-labelledby="post-mobile-options-trigger-{{ $displayPost->uid }}"
        class="skuy-menu-surface absolute right-0 z-50 mt-2 w-44"
    >
        <form
            action="{{ $mobileBookmarkActive ? route('bookmarks.destroy', $displayPost->uid) : route('bookmarks.store', $displayPost->uid) }}"
            method="POST"
            class="skuy-post-options__bookmark skuy-bookmark-menu-action"
            x-data="likeControl({
                liked: @js((bool) $displayPost->liked_by_me),
                likeCount: @js((int) ($displayPost->likes_count ?? 0)),
                reposted: @js((bool) $displayPost->reposted_by_me),
                repostCount: @js((int) ($displayPost->reposts_count ?? 0)),
                bookmarked: @js($mobileBookmarkActive),
                postUid: @js((string) $displayPost->uid),
                urls: {
                    bookmarkStore: @js(route('bookmarks.store', $displayPost->uid)),
                    bookmarkDestroy: @js(route('bookmarks.destroy', $displayPost->uid)),
                },
                labels: {
                    save: @js(__('threads.actions.save')),
                    removeFromSaved: @js(__('threads.actions.remove_from_saved')),
                    sessionExpired: @js(__('threads.feedback.session_expired')),
                    restricted: @js(__('threads.feedback.restricted')),
                    invalid: @js(__('threads.feedback.invalid')),
                    failed: @js(__('threads.feedback.failed')),
                    network: @js(__('threads.feedback.network')),
                },
            })"
            @skuy-post-action-state.window="syncPeerState($event)"
            @submit.prevent="toggleBookmark($event)"
            :action="bookmarkFormAction()"
        >
            @csrf
            <input type="hidden" name="_method" value="DELETE" x-bind:disabled="!bookmarked" @if (! $mobileBookmarkActive) disabled @endif>
            <button
                type="submit"
                class="skuy-menu-link flex w-full items-center gap-2"
                role="menuitem"
                aria-label="{{ $mobileBookmarkActive ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}"
                :aria-label="bookmarkButtonLabel()"
                aria-pressed="{{ $mobileBookmarkActive ? 'true' : 'false' }}"
                :aria-pressed="bookmarked.toString()"
                :aria-busy="isBookmarkRequestInFlight.toString()"
                :disabled="isBookmarkRequestInFlight"
            >
                <x-fa-icon name="bookmark" :weight="$mobileBookmarkActive ? 'solid' : 'regular'" class="text-base" />
                <span x-text="bookmarkButtonLabel()">{{ $mobileBookmarkActive ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}</span>
            </button>
            <p
                x-show="feedbackMessage"
                x-text="feedbackMessage"
                :role="feedbackIsError ? 'alert' : 'status'"
                :aria-live="feedbackIsError ? 'assertive' : 'polite'"
                aria-atomic="true"
                style="display: none;"
                class="px-3 py-2 text-xs font-semibold text-[var(--skuy-danger)]"
            ></p>
        </form>
    </div>
</div>
