import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/views/welcome.css',
                'resources/views/welcome.js',
                'resources/views/frontend/themes/theme_diu/assets/css/theme.css',
                'resources/views/frontend/themes/theme_diu/assets/js/theme.js',
                'resources/views/frontend/themes/theme_default/assets/css/theme.css',
                'resources/views/frontend/themes/theme_default/assets/js/theme.js',
                'resources/views/frontend/themes/theme_modern/assets/css/theme.css',
                'resources/views/frontend/themes/theme_modern/assets/js/theme.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
