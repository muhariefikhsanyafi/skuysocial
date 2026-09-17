@props([
    'id',
    'name',
    'label',
    'autocomplete' => 'new-password',
])

@php
    $errorId = "{$id}-error";
    $hasError = $errors->has($name);
@endphp

<div class="skuy-auth-field">
    <label for="{{ $id }}" class="skuy-auth-label">{{ $label }}</label>

    <div class="skuy-auth-password-control" x-data="{ visible: false }">
        <input
            id="{{ $id }}"
            type="password"
            name="{{ $name }}"
            required
            autocomplete="{{ $autocomplete }}"
            @if ($hasError) aria-describedby="{{ $errorId }}" aria-invalid="true" @endif
            class="skuy-auth-input skuy-auth-password-input"
            x-bind:type="visible ? 'text' : 'password'"
        >
        <button
            type="button"
            class="skuy-auth-password-toggle"
            aria-label="{{ __('auth.common.show_password') }}"
            aria-pressed="false"
            data-show-label="{{ __('auth.common.show_password') }}"
            data-hide-label="{{ __('auth.common.hide_password') }}"
            x-on:click="visible = ! visible"
            x-bind:aria-label="visible ? $el.dataset.hideLabel : $el.dataset.showLabel"
            x-bind:aria-pressed="visible"
        >
            <svg x-show="! visible" class="skuy-auth-password-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                <circle cx="12" cy="12" r="2.75" />
            </svg>
            <svg x-show="visible" style="display: none;" class="skuy-auth-password-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 0 0 2.84 2.84" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 4.24A10.94 10.94 0 0 1 12 4.04c6 0 9.75 6.75 9.75 6.75a17.4 17.4 0 0 1-3.06 3.77M6.23 6.23C3.77 8.1 2.25 12 2.25 12S6 18.75 12 18.75a10.7 10.7 0 0 0 4.1-.82" />
            </svg>
        </button>
    </div>

    <x-input-error variant="auth" :messages="$errors->get($name)" :id="$errorId" />
</div>
