@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold leading-5 text-[var(--skuy-text-primary)]']) }}>
    {{ $value ?? $slot }}
</label>
