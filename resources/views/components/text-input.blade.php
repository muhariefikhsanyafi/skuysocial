@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[var(--skuy-border-subtle)] focus:border-[var(--skuy-focus)] focus:ring-[var(--skuy-focus)] rounded-md shadow-sm']) }}>
