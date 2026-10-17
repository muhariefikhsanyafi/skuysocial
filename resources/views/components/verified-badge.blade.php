@props(['flow' => false])

<span {{ $attributes->merge(['class' => 'skuy-verified-badge']) }} data-verified-badge @if ($flow) data-flow-verified @endif role="img" title="{{ __('threads.verified') }}" aria-label="{{ __('threads.verified') }}">
    <img src="{{ asset('images/verified-badge.svg') }}" alt="" aria-hidden="true" width="22" height="22" loading="eager">
</span>
