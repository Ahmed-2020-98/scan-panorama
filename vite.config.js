import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                google('Tajawal', {
                    weights: [400, 500, 700, 800],
                    subsets: ['arabic', 'latin'],
                }),
                google('Montserrat', {
                    weights: [400, 500, 600, 700, 800],
                    subsets: ['latin'],
                }),
                // Optional interface fonts (Settings > Appearance): not preloaded,
                // so a browser only downloads the one its user picked.
                google('Cairo', { weights: [400, 500, 600, 700, 800], subsets: ['arabic', 'latin'], preload: false }),
                google('IBM Plex Sans Arabic', { weights: [400, 500, 600, 700], subsets: ['arabic', 'latin'], preload: false }),
                google('Almarai', { weights: [400, 700, 800], subsets: ['arabic'], preload: false }),
                google('Readex Pro', { weights: [400, 500, 600, 700], subsets: ['arabic', 'latin'], preload: false }),
                google('DM Mono', {
                    weights: [400, 500],
                    subsets: ['latin'],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
