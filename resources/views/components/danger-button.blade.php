<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-danger)] bg-[var(--skuy-danger)] px-4 py-3 text-sm font-semibold text-[var(--skuy-on-danger)] hover:brightness-95 active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[var(--skuy-danger)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)] disabled:cursor-not-allowed disabled:opacity-50 transition-[filter,opacity] duration-150']) }}>
    {{ $slot }}
</button>
