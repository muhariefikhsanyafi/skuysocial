<script data-skuy-theme @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
    (() => {
        let savedTheme = null;
        try {
            savedTheme = window.localStorage.getItem('theme');
        } catch (error) {}

        const dark = savedTheme === 'dark' || (savedTheme !== 'light' && (
            @json($darkDefault ?? false) || window.matchMedia('(prefers-color-scheme: dark)').matches
        ));
        document.documentElement.classList.toggle('dark', dark);

        window.skuyTheme = {
            persist(value) {
                try {
                    window.localStorage.setItem('theme', value ? 'dark' : 'light');
                } catch (error) {}
            },
        };
    })();
</script>
