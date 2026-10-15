import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/skuy-flow-feed.css',
                'resources/css/skuy-flow-profile.css',
                'resources/css/skuy-flow-messages.css',
                'resources/css/skuy-flow-activity.css',
                'resources/css/skuy-flow-thread.css',
                'resources/css/skuy-flow-discovery.css',
                'resources/css/skuy-flow-support.css',
                'resources/css/skuy-flow-post-actions.css',
                'resources/js/app.js',
                'resources/js/skuy-flow-profile.js',
                'resources/js/inertia.tsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
