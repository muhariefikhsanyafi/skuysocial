@props([
    'messages',
    'variant' => 'default',
])

@if ($messages)
    @php
        $defaultClass = $variant === 'auth'
            ? 'skuy-auth-error'
            : 'space-y-1 text-sm font-semibold text-[var(--skuy-danger)]';
    @endphp

    <ul {{ $attributes->merge(['class' => $defaultClass, 'role' => 'alert']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
