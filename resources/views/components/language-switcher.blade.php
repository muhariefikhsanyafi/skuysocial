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
        class="{{ $isAppVariant ? 'skuy-icon-button' : 'inline-flex min-h-[44px] min-w-[44px] items-center justify-center border-2 border-black bg-white p-2 text-black shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] transition-all hover:translate-x-[2px] hover:translate-y-[2px] hover:bg-cyan-300 hover:shadow-none focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-pink-500 dark:border-[#333333] dark:bg-[#181818] dark:text-white dark:shadow-[3px_3px_0px_0px_rgba(51,51,51,1)] dark:hover:bg-cyan-500 dark:hover:text-black' }}"
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
        class="{{ $isAppVariant ? 'skuy-menu-surface absolute end-0 top-full z-[60] mt-2 w-48 max-w-[calc(100vw-2rem)]' : 'absolute end-0 top-full z-[60] mt-2 w-48 max-w-[calc(100vw-2rem)] border-4 border-black bg-white text-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:border-[#333333] dark:bg-[#181818] dark:text-white dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]' }}"
    >
        <div class="{{ $isAppVariant ? 'border-b border-[var(--skuy-border-subtle)] px-3 py-2 text-xs font-bold uppercase text-[var(--skuy-text-muted)]' : 'border-b-2 border-black px-3 py-2 text-xs font-black uppercase dark:border-[#333333]' }}">
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
                        class="{{ $isAppVariant ? 'flex min-h-[44px] w-full items-center justify-between gap-3 border border-transparent px-3 py-2 text-left text-sm font-bold text-[var(--skuy-text-secondary)] transition-colors hover:bg-[var(--skuy-surface-subtle)] hover:text-[var(--skuy-text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]' : 'flex min-h-[44px] w-full items-center justify-between gap-3 border-2 border-transparent px-3 py-2 text-left text-sm font-black focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-pink-500' }} {{ $isActive ? ($isAppVariant ? 'bg-[var(--skuy-selected)] text-[var(--skuy-on-selected)]' : 'border-black bg-yellow-300 text-black dark:border-[#333333] dark:bg-yellow-400') : ($isAppVariant ? '' : 'text-black hover:border-black hover:bg-cyan-300 dark:text-white dark:hover:border-[#333333] dark:hover:bg-cyan-500 dark:hover:text-black') }}"
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
