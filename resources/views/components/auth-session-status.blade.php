@props([
    'status',
    'variant' => 'default',
])

@if ($status)
    @php
        $defaultClass = $variant === 'auth'
            ? 'skuy-auth-status'
            : 'font-medium text-sm text-[var(--skuy-secondary)]';
    @endphp

    <div {{ $attributes->merge(['class' => $defaultClass, 'role' => 'status']) }}>
        {{ $status }}
    </div>
@endif
