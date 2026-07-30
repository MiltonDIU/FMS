import { existsSync, readdirSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const THEMES_DIR = 'resources/views/frontend/themes';

/**
 * Build entry points for every installed frontend theme.
 *
 * System Settings discovers themes off the filesystem, so listing them here by
 * hand meant a dropped-in theme showed up in the picker but shipped no CSS.
 * Uses node:fs rather than a glob package on purpose: the only one installed is
 * a transitive dependency of Vite, not something this project declares.
 *
 * Adding a theme still needs a rebuild, since this runs at config load time.
 */
function themeInputs() {
    if (!existsSync(THEMES_DIR)) {
        return [];
    }

    return readdirSync(THEMES_DIR, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .flatMap((entry) => [
            `${THEMES_DIR}/${entry.name}/assets/css/theme.css`,
            `${THEMES_DIR}/${entry.name}/assets/js/theme.js`,
        ])
        .filter(existsSync);
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/views/welcome.css',
                'resources/views/welcome.js',
                ...themeInputs(),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
