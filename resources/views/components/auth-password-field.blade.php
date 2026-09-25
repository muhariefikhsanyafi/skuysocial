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
            <x-fa-icon x-show="! visible" name="eye" class="skuy-auth-password-icon" />
            <x-fa-icon x-show="visible" style="display: none;" name="eye-slash" weight="solid" class="skuy-auth-password-icon" />
        </button>
    </div>

    <x-input-error variant="auth" :messages="$errors->get($name)" :id="$errorId" />
</div>
