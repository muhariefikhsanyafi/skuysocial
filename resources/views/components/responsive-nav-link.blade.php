@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[var(--skuy-secondary)] text-start text-base font-medium text-[var(--skuy-on-selected)] bg-[var(--skuy-selected)] focus:outline-none focus:text-[var(--skuy-on-selected)] focus:bg-[var(--skuy-selected)] focus:border-[var(--skuy-focus)] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[var(--skuy-text-secondary)] hover:text-[var(--skuy-text-primary)] hover:bg-[var(--skuy-surface-subtle)] hover:border-[var(--skuy-border-subtle)] focus:outline-none focus:text-[var(--skuy-text-primary)] focus:bg-[var(--skuy-surface-subtle)] focus:border-[var(--skuy-focus)] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
