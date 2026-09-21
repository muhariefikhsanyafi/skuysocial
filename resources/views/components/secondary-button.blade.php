<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-[var(--skuy-surface)] border border-[var(--skuy-border-subtle)] rounded-md font-semibold text-xs text-[var(--skuy-text-secondary)] uppercase tracking-widest shadow-sm hover:bg-[var(--skuy-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
