<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[var(--skuy-brand)] border border-transparent rounded-md font-semibold text-xs text-[var(--skuy-on-brand)] uppercase tracking-widest hover:bg-[var(--skuy-brand-hover)] focus:bg-[var(--skuy-brand-hover)] active:bg-[var(--skuy-brand-pressed)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
