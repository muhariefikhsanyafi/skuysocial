@props(['flow' => false])

<span {{ $attributes->merge(['class' => 'skuy-verified-badge']) }} data-verified-badge @if ($flow) data-flow-verified @endif role="img" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
    @if ($flow)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 1.35c1.1 0 2.2 1.2 3.3 1.45 1.1.26 2.5-.28 3.45.45.95.73.8 2.28 1.5 3.2.7.92 2.25 1.35 2.45 2.55.2 1.2-.8 2-.8 3s1 1.8.8 3c-.2 1.2-1.75 1.63-2.45 2.55-.7.92-.55 2.47-1.5 3.2-.95.73-2.35.19-3.45.45-1.1.25-2.2 1.45-3.3 1.45s-2.2-1.2-3.3-1.45c-1.1-.26-2.5.28-3.45-.45-.95-.73-.8-2.28-1.5-3.2C3.05 16.63 1.5 16.2 1.3 15c-.2-1.2.8-2 .8-3s-1-1.8-.8-3c.2-1.2 1.75-1.63 2.45-2.55.7-.92.55-2.47 1.5-3.2.95-.73 2.35-.19 3.45-.45C9.8 2.55 10.9 1.35 12 1.35Z"/>
            <path d="m7.2 12.1 3.25 3.15 6.35-6.5" stroke="#fff" stroke-width="2.45" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @else
        <img src="{{ asset('images/verified-badge.svg') }}" alt="" aria-hidden="true" width="22" height="22" loading="eager">
    @endif
</span>
