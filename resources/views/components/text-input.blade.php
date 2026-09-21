@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block min-h-[48px] w-full rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] text-[var(--skuy-text-primary)] shadow-none placeholder:text-[var(--skuy-text-muted)] focus:border-[var(--skuy-focus)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)]']) }}>
