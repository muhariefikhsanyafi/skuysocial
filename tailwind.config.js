import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
	darkMode: 'class',
	
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
    ],

    theme: {
        extend: {
            colors: {
                skuy: {
                    background: 'var(--skuy-background)',
                    foreground: 'var(--skuy-text-primary)',
                    surface: 'var(--skuy-surface)',
                    card: 'var(--skuy-surface)',
                    elevated: 'var(--skuy-surface-elevated)',
                    popover: 'var(--skuy-surface-elevated)',
                    'surface-subtle': 'var(--skuy-surface-subtle)',
                    muted: 'var(--skuy-surface-subtle)',
                    'muted-foreground': 'var(--skuy-text-muted)',
                    'text-secondary': 'var(--skuy-text-secondary)',
                    border: 'var(--skuy-border-subtle)',
                    'border-strong': 'var(--skuy-border-strong)',
                    focus: 'var(--skuy-focus)',
                    primary: 'var(--skuy-brand)',
                    'primary-hover': 'var(--skuy-brand-hover)',
                    'primary-pressed': 'var(--skuy-brand-pressed)',
                    'on-primary': 'var(--skuy-on-brand)',
                    selected: 'var(--skuy-selected)',
                    'on-selected': 'var(--skuy-on-selected)',
                    reaction: 'var(--skuy-reaction)',
                    'on-reaction': 'var(--skuy-on-reaction)',
                    danger: 'var(--skuy-danger)',
                    'on-danger': 'var(--skuy-on-danger)',
                },
            },
            borderRadius: {
                'skuy-none': 'var(--radius-none)',
                'skuy-small': 'var(--radius-small)',
                'skuy-medium': 'var(--radius-medium)',
                'skuy-large': 'var(--radius-large)',
                'skuy-full': 'var(--radius-full)',
            },
            boxShadow: {
                'skuy-1': 'var(--skuy-elevation-1)',
                'skuy-2': 'var(--skuy-elevation-2)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
