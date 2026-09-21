@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[var(--skuy-secondary)] text-sm font-medium leading-5 text-[var(--skuy-text-primary)] focus:outline-none focus:border-[var(--skuy-focus)] transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[var(--skuy-text-muted)] hover:text-[var(--skuy-secondary)] hover:border-[var(--skuy-border-subtle)] focus:outline-none focus:text-[var(--skuy-secondary)] focus:border-[var(--skuy-focus)] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
