<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-4 py-3 text-sm font-semibold text-[var(--skuy-text-primary)] hover:bg-[var(--skuy-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)] disabled:cursor-not-allowed disabled:opacity-50 transition-colors duration-150']) }}>
    {{ $slot }}
</button>
