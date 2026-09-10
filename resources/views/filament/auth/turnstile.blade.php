@if (config('services.turnstile.enabled'))
    <x-filament-forms::field-wrapper state-path="data.turnstile_token">
        <div
            x-data="{
                widgetId: null,
                init() {
                    if (window.turnstile) {
                        this.renderWidget();
                    } else {
                        window.addEventListener('skuy-admin-turnstile-loaded', () => this.renderWidget(), { once: true });
                    }
                },
                renderWidget() {
                    if (this.widgetId !== null || ! window.turnstile) {
                        return;
                    }

                    this.widgetId = window.turnstile.render(this.$refs.widget, {
                        sitekey: @js(config('services.turnstile.site_key')),
                        action: 'login',
                        callback: (token) => $wire.set('data.turnstile_token', token, false),
                        'expired-callback': () => $wire.set('data.turnstile_token', null, false),
                        'error-callback': () => $wire.set('data.turnstile_token', null, false),
                    });
                },
                resetWidget() {
                    if (this.widgetId !== null && window.turnstile) {
                        window.turnstile.reset(this.widgetId);
                    }

                    $wire.set('data.turnstile_token', null, false);
                },
            }"
            x-on:admin-turnstile-reset.window="resetWidget()"
        >
            <div x-ref="widget" wire:ignore></div>
        </div>
    </x-filament-forms::field-wrapper>

    @once
        <script>
            window.skuySocialAdminTurnstileLoaded = () => {
                window.dispatchEvent(new CustomEvent('skuy-admin-turnstile-loaded'));
            };
        </script>
        <script
            src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=skuySocialAdminTurnstileLoaded&render=explicit"
            async
            defer
        ></script>
    @endonce
@endif
