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
                google('Readex Pro', {
                    weights: [300, 400, 500, 600, 700],
                    subsets: ['arabic', 'latin'],
                }),
                google('Alexandria', {
                    weights: [500, 600, 700, 800],
                    subsets: ['arabic', 'latin'],
                }),
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
