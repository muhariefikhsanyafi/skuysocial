<button
    type="button"
    aria-label="{{ __('navigation.enable_dark_mode') }}"
    aria-pressed="false"
    :aria-label="darkMode ? '{{ __('navigation.enable_light_mode') }}' : '{{ __('navigation.enable_dark_mode') }}'"
    :aria-pressed="darkMode"
    @click="darkMode = !darkMode"
    class="skuy-theme-toggle"
>
    <span class="skuy-theme-toggle__icon" aria-hidden="true">
        <svg x-show="!darkMode" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 0 1 8.646 3.646 9.003 9.003 0 0 0 12 21a9.003 9.003 0 0 0 8.354-5.646Z"></path>
        </svg>
        <svg x-show="darkMode" style="display: none;" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M3 12h2m14 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
            <circle cx="12" cy="12" r="4"></circle>
        </svg>
    </span>
    <span class="skuy-theme-toggle__content">
        <span class="skuy-theme-toggle__label">{{ __('navigation.theme') }}</span>
        <span class="skuy-theme-toggle__value" x-text="darkMode ? '{{ __('home.public.theme_dark') }}' : '{{ __('home.public.theme_light') }}'">{{ __('home.public.theme_light') }}</span>
    </span>
</button>
