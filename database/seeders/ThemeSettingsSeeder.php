<?php

namespace Database\Seeders;

use App\Helpers\FontManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  ADDING A NEW FRONTEND THEME — START HERE
 * ============================================================================
 *
 *  Full guide: docs/frontend-theme-development.md
 *
 *  Short version. `theme_{slug}` is your folder under
 *  resources/views/frontend/themes/ — copy `theme_diu`, it is the reference
 *  theme and the fallback for optional views.
 *
 *   1. REQUIRED FILES
 *        theme_{slug}/layouts/app.blade.php     <- decides the theme exists;
 *                                                  no layout = silently hidden
 *                                                  from the Active Theme list
 *        theme_{slug}/home.blade.php            <- HomeController
 *        theme_{slug}/department.blade.php      <- DepartmentController@show
 *        theme_{slug}/contact.blade.php         <- DepartmentController@contact
 *        theme_{slug}/profile.blade.php         <- TeacherController@show
 *        theme_{slug}/publication.blade.php     <- PublicationController@show
 *        theme_{slug}/assets/css/theme.css
 *        theme_{slug}/assets/js/theme.js
 *
 *      Optional: theme_{slug}/livewire/{teacher,department}-search.blade.php
 *      (falls back to theme_diu's). Partials are yours to organise, except keep
 *      the header at partials/header.blade.php to inherit $facultiesCount,
 *      $departmentsCount and $teachersCount from the view composer.
 *
 *   2. RENAME THE SLUG EVERYWHERE  <- the #1 mistake when copying a theme
 *        grep -rn 'theme_diu' resources/views/frontend/themes/theme_{slug}
 *      must come back empty. Stale slugs hide in the layout's @include paths,
 *      both @vite asset paths, and all three FontManager::*() arguments — the
 *      theme then loads another theme's CSS and fonts while looking fine.
 *
 *   3. LAYOUT MUST WIRE UP THE SETTINGS SYSTEM
 *        Appearance::htmlClass() on <html>, Appearance::preloadScript() in
 *        <head>, FontManager::googleLinks(), customStylesheetLinks() and
 *        cssBlock() — cssBlock AFTER @vite so it wins — plus
 *        ColorPalette::cssRootBlock(), @yield('content') and @livewireScripts.
 *
 *   4. theme.css NEEDS
 *        @import 'tailwindcss';
 *        @custom-variant dark (&:where(.dark, .dark *));   <- or no dark mode
 *        the @source lines (six ../ levels up to the project root)
 *        @theme with 4 --font-* and all 10 --color-diu-* tokens
 *
 *   5. THEN RUN
 *        npm run build                                  <- Vite scans for the
 *                                                          theme at config load
 *        php artisan db:seed --class=ThemeSettingsSeeder <- this file
 *        php artisan optimize:clear
 *        Admin -> System Settings -> Active Theme
 *
 * ============================================================================
 *
 * Seeds the per-theme typography and layout settings.
 *
 * Every value comes from FontManager, which is also the runtime fallback, so a
 * seeded database and a fresh one resolve to exactly the same fonts.
 *
 * Precedence, by design:
 *   1. a real value stored in `settings`  (what the admin picked)
 *   2. FontManager::DEFAULTS / SIZE_DEFAULT / WEIGHT_DEFAULTS
 *   3. the build-time @theme tokens in each theme's assets/css/theme.css
 *
 * Re-running this is safe. A row holding a real value is never overwritten —
 * only its metadata is refreshed. Rows that are missing, NULL, or blank get the
 * default, which also repairs installs seeded before the defaults existed.
 *
 * Usage: php artisan db:seed --class=ThemeSettingsSeeder
 */
class ThemeSettingsSeeder extends Seeder
{
    /**
     * Theme installed as active when nothing has been chosen yet.
     */
    protected const FALLBACK_ACTIVE_THEME = 'theme_default';

    public function run(): void
    {
        $themes = $this->discoverThemes();

        if (empty($themes)) {
            $this->command->warn('No usable themes found; skipping theme settings.');

            return;
        }

        $counts = ['inserted' => 0, 'repaired' => 0, 'kept' => 0];
        $sortOrder = 200;

        foreach ($themes as $slug) {
            foreach (FontManager::settingDefaults() as $suffix => $spec) {
                $result = $this->seedSetting(
                    key: FontManager::settingKey($slug, $suffix),
                    value: $spec['value'],
                    type: $spec['type'],
                    label: $spec['label'] . ' — ' . $this->themeLabel($slug),
                    description: "Per-theme typography setting for the {$slug} frontend theme.",
                    sortOrder: $sortOrder++,
                );

                $counts[$result]++;
            }
        }

        // Only installed when absent: switching the live frontend theme is the
        // admin's call, not something a re-seed should undo.
        $activeTheme = in_array(self::FALLBACK_ACTIVE_THEME, $themes, true)
            ? self::FALLBACK_ACTIVE_THEME
            : $themes[0];

        $counts[$this->seedSetting(
            key: 'active_theme',
            value: $activeTheme,
            type: 'string',
            label: 'Active Frontend Theme',
            description: 'The theme used to render the public frontend.',
            sortOrder: 100,
        )]++;

        $this->command->info(sprintf(
            'Theme settings seeded for %d theme(s): %d inserted, %d repaired, %d left as configured.',
            count($themes),
            $counts['inserted'],
            $counts['repaired'],
            $counts['kept'],
        ));
    }

    /**
     * Insert, repair, or preserve one setting row.
     *
     * @return 'inserted'|'repaired'|'kept' which of the three happened
     */
    protected function seedSetting(
        string $key,
        string $value,
        string $type,
        string $label,
        string $description,
        int $sortOrder,
    ): string {
        $existing = DB::table('settings')->where('key', $key)->first();

        $metadata = [
            'group' => 'system',
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'is_public' => false,
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('settings')->insert($metadata + [
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
            ]);

            return 'inserted';
        }

        // A row that exists but holds nothing is treated as unset, because
        // Setting::get() returns that empty value instead of its default.
        if ($existing->value === null || trim((string) $existing->value) === '') {
            DB::table('settings')->where('key', $key)->update($metadata + ['value' => $value]);

            return 'repaired';
        }

        DB::table('settings')->where('key', $key)->update($metadata);

        return 'kept';
    }

    /**
     * Themes that can actually render, matched to how System Settings lists them:
     * a theme without its own layout would crash the frontend if selected.
     *
     * @return array<int,string>
     */
    protected function discoverThemes(): array
    {
        $themesPath = resource_path('views/frontend/themes');

        if (! is_dir($themesPath)) {
            return [];
        }

        $themes = [];

        foreach (array_filter(glob($themesPath . '/*'), 'is_dir') as $dir) {
            if (! is_file($dir . '/layouts/app.blade.php')) {
                continue;
            }

            $themes[] = basename($dir);
        }

        sort($themes);

        return $themes;
    }

    protected function themeLabel(string $slug): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $slug));
    }
}
