import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    resolve: (name) => resolvePageComponent<{ default: ResolvedComponent }>(
        `./pages/${name}.tsx`,
        import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx'),
    ).then((page) => page.default),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
