<x-app-layout>
    <div class="skuy-feed-page skuy-edit-thread-page" data-flow-thread="edit">
        <div class="skuy-edit-thread-shell">
            <header class="skuy-edit-thread-header">
                <a href="{{ route('feed') }}" class="skuy-action" aria-label="{{ __('navigation.back_to_feed') }}">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <h1 id="edit-post-form-title">{{ __('threads.edit.title') }}</h1>
            </header>
            <section class="skuy-composer skuy-edit-thread-composer" aria-labelledby="edit-post-form-title">
                <form action="{{ route('posts.update', $post->uid) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <label for="post-content" class="sr-only">{{ __('threads.edit.title') }}</label>
                    <textarea
                        id="post-content"
                        name="content"
                        rows="6"
                        class="skuy-composer__textarea min-h-[160px]"
                        required
                        maxlength="1000"
                    >{{ old('content', $post->content) }}</textarea>

                    <div class="mt-5 flex flex-wrap items-center justify-end gap-3 border-t border-[var(--skuy-border-subtle)] pt-4">
                        <!-- Tombol Batal -->
                        <a href="{{ route('feed') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)] px-4 py-2 text-sm font-semibold text-[var(--skuy-text-primary)] transition-colors hover:bg-[var(--skuy-surface)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--skuy-focus)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--skuy-surface)]">
                            {{ __('threads.actions.cancel') }}
                        </a>
                        <!-- Tombol Simpan -->
                        <button type="submit" class="skuy-button skuy-button--primary">
                            {{ __('threads.actions.save_changes') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
