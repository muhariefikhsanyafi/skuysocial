import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/skuy-flow-feed.css',
                'resources/js/app.js',
                'resources/js/inertia.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
