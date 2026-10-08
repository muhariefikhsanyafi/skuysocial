<x-app-layout>
    <div class="skuy-feed-page skuy-saved-page" data-skuy-prism-saved x-data="skuyPostMediaViewer">
        <div class="skuy-feed-content skuy-saved-content">
            <header class="skuy-list-page-header skuy-saved-header" data-saved-page-header>
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action skuy-saved-header__back">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <span class="skuy-saved-header__icon" aria-hidden="true">
                    <x-fa-icon name="bookmark" weight="solid" />
                </span>
                <div class="skuy-saved-header__identity">
                    <p class="skuy-saved-header__eyebrow">{{ __('saved.collection_label') }}</p>
                    <h1 class="skuy-list-page-header__title">{{ __('saved.title') }}</h1>
                    <p class="skuy-saved-header__description">{{ __('saved.subtitle') }}</p>
                </div>
            </header>

            @if ($posts->isEmpty())
                <div class="skuy-saved-collection">
                    @include('partials.saved-empty-state', ['pageOnly' => $posts->currentPage() > 1])

                    @if ($posts->currentPage() > 1)
                        <div class="skuy-saved-pagination">
                            <a href="{{ route('bookmarks.index') }}" class="skuy-saved-empty__action" data-saved-collection-link>
                                <x-fa-icon name="arrow-left-long" aria-hidden="true" />
                                <span>{{ __('saved.back_to_collection') }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            @else
                @php($savedPageHasOtherPages = $posts->currentPage() > 1 || $posts->hasMorePages())

                <div
                    class="skuy-saved-collection"
                    data-saved-post-collection
                    data-initial-count="{{ $posts->count() }}"
                    x-data="skuySavedPage"
                    @skuy-post-action-state.window="removeUnsaved($event)"
                >
                    <div x-ref="savedPosts" class="skuy-feed-list skuy-saved-post-list">
                        @include('partials.feed-threads', ['posts' => $posts])
                    </div>

                    <div x-ref="savedEmptyState" x-show="visiblePostCount === 0" style="display: none;">
                        @include('partials.saved-empty-state', ['pageOnly' => $savedPageHasOtherPages])
                    </div>

                    @if ($posts->hasPages())
                        <div class="skuy-saved-pagination" x-ref="savedPagination">
                            {{ $posts->links() }}
                        </div>
                    @endif
                </div>

                @include('partials.post-media-lightbox')
            @endif
        </div>
    </div>
</x-app-layout>
