@props(['action'])

@if (config('services.turnstile.enabled'))
    <div
        class="cf-turnstile"
        data-sitekey="{{ config('services.turnstile.site_key') }}"
        data-action="{{ $action }}"
    ></div>

    <x-input-error variant="auth" :messages="$errors->get('cf-turnstile-response')" class="mt-2" />

    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
