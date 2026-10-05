@php
    $mobileBookmarkActive = (bool) ($displayPost->bookmarked_by_me ?? $displayPost->bookmarked_by_current_user ?? false);
@endphp

<div
    x-data="{
        openOptions: false,
        closeOptions(restoreFocus = false) {
            this.openOptions = false;

            if (restoreFocus) {
                this.$nextTick(() => this.$refs.optionsTrigger?.focus());
            }
        }
    }"
    @keydown.escape.window="if (openOptions) closeOptions(true)"
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
            class="skuy-post-options__bookmark"
        >
            @csrf
            @if ($mobileBookmarkActive)
                @method('DELETE')
            @endif
            <button type="submit" class="skuy-menu-link flex w-full items-center gap-2" role="menuitem" aria-pressed="{{ $mobileBookmarkActive ? 'true' : 'false' }}">
                <x-fa-icon name="bookmark" :weight="$mobileBookmarkActive ? 'solid' : 'regular'" class="text-base" />
                {{ $mobileBookmarkActive ? __('threads.actions.remove_from_saved') : __('threads.actions.save') }}
            </button>
        </form>
    </div>
</div>
