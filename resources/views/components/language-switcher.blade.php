@props(['variant' => 'default'])

@php
    $isAppVariant = $variant === 'app';
    $supportedLocales = config('localization.supported', []);
    $activeLocale = app()->getLocale();

    if (! array_key_exists($activeLocale, $supportedLocales)) {
        $activeLocale = array_key_first($supportedLocales) ?? '';
    }

    $languageNames = __('localization.languages');
    $languageNames = is_array($languageNames) ? $languageNames : [];
    $choiceNames = __('localization.choices');
    $choiceNames = is_array($choiceNames) ? $choiceNames : [];
    $returnTo = request()->getPathInfo();
    $queryString = request()->getQueryString();

    if (is_string($queryString) && $queryString !== '') {
        $returnTo .= '?'.$queryString;
    }

    $menuId = 'language-menu-'.substr(md5($returnTo), 0, 8);
@endphp

<div
    {{ $attributes->merge(['class' => 'relative shrink-0 skuy-language-switcher']) }}
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        @click="open = !open"
        aria-expanded="false"
        :aria-expanded="open"
        aria-haspopup="menu"
        aria-controls="{{ $menuId }}"
        aria-label="{{ __('localization.choose_language') }}"
        class="{{ $isAppVariant ? 'skuy-icon-button' : 'skuy-guest-language-trigger' }}"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <path stroke-linecap="square" d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9S14.5 18.5 12 21M12 3c-2.5 2.5-3.5 5.5-3.5 9S9.5 18.5 12 21"></path>
        </svg>
        <span class="sr-only">{{ __('localization.choose_language') }}</span>
    </button>

    <div
        id="{{ $menuId }}"
        x-show="open"
        style="display: none;"
        role="menu"
        aria-label="{{ __('localization.change_language') }}"
        class="{{ $isAppVariant ? 'skuy-menu-surface absolute end-0 top-full z-[60] mt-2 w-48 max-w-[calc(100vw-2rem)]' : 'skuy-guest-language-menu' }}"
    >
        <div class="{{ $isAppVariant ? 'border-b border-[var(--skuy-border-subtle)] px-3 py-2 text-xs font-bold uppercase text-[var(--skuy-text-muted)]' : 'skuy-guest-language-menu__heading' }}">
            {{ __('localization.language') }}
        </div>

        <div class="space-y-1 p-1">
            @foreach ($supportedLocales as $locale => $configuredName)
                @php
                    $languageName = $languageNames[$locale] ?? $configuredName;
                    $choiceName = $choiceNames[$locale] ?? $languageName;
                    $isActive = $locale === $activeLocale;
                    $itemLabel = $isActive
                        ? __('localization.current_language').': '.$languageName
                        : __('localization.switch_to', ['language' => $languageName]);
                @endphp

                <form method="POST" action="{{ route('locale.update') }}" class="block" aria-label="{{ $itemLabel }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">

                    <button
                        type="submit"
                        role="menuitem"
                        name="locale"
                        value="{{ $locale }}"
                        aria-label="{{ $itemLabel }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        @if ($isActive) aria-current="true" @endif
                        class="{{ $isAppVariant ? 'flex min-h-[44px] w-full items-center justify-between gap-3 border border-transparent px-3 py-2 text-left text-sm font-bold text-[var(--skuy-text-secondary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] hover:text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]' : 'skuy-guest-language-menu__item' }}{{ $isActive && ! $isAppVariant ? ' is-active' : '' }}{{ $isActive && $isAppVariant ? ' bg-[var(--skuy-selected)] text-[var(--skuy-on-selected)]' : '' }}"
                    >
                        <span>{{ $choiceName }}</span>

                        @if ($isActive)
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                <path stroke-linecap="square" stroke-linejoin="miter" d="m5 12 4 4L19 6"></path>
                            </svg>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
