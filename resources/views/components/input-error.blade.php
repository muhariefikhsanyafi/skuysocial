@props([
    'messages',
    'variant' => 'default',
])

@if ($messages)
    @php
        $defaultClass = $variant === 'auth'
            ? 'skuy-auth-error'
            : 'text-sm text-red-600 space-y-1';
    @endphp

    <ul {{ $attributes->merge(['class' => $defaultClass, 'role' => 'alert']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
