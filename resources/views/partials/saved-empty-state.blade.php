@php($pageOnly = $pageOnly ?? false)

<div class="skuy-social-empty skuy-saved-empty" role="status" data-saved-empty-state>
    <span class="skuy-social-empty__mark skuy-saved-empty__mark" aria-hidden="true">
        <x-fa-icon name="bookmark" />
    </span>
    <p class="skuy-saved-empty__eyebrow">{{ __('saved.collection_label') }}</p>
    <h2 class="skuy-social-empty__title">{{ $pageOnly ? __('saved.page_empty_title') : __('saved.empty') }}</h2>
    <p class="skuy-social-empty__description">
        {{ $pageOnly ? __('saved.page_empty_description') : __('saved.empty_description') }}
    </p>
    @unless ($pageOnly)
        <a href="{{ route('feed') }}" class="skuy-saved-empty__action" data-saved-feed-link>
            <span>{{ __('saved.browse_feed') }}</span>
            <x-fa-icon name="arrow-right-long" aria-hidden="true" />
        </a>
    @endunless
</div>
