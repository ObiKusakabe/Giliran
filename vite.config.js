import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
            // Disable preloading for fonts to avoid browser warnings
            // Modern browsers cache fonts efficiently without preload hints
            preload: (src) => !src.includes('.woff'),
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        strictPort: false,
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
