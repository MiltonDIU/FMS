<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Everything the application knows about frontend themes.
 *
 * A theme is a folder under resources/views/frontend/themes. It is meant to be
 * droppable and deletable as one unit — nothing outside it may assume it exists.
 * Before this class, every controller built its view name by interpolating the
 * active_theme setting, so deleting the folder that setting pointed at answered
 * 500 on every public page until someone edited the database.
 *
 * So the rules are:
 *
 *  - a theme counts as installed only when it ships every view in REQUIRED,
 *    because a half-theme fails at render time rather than at selection time;
 *  - the active theme is resolved through active(), which falls back to a
 *    theme that is actually present when the stored one has gone;
 *  - if every theme has been deleted, the site says so on a plain page instead
 *    of throwing.
 *
 * Metadata (display name, description, screenshot) comes from an optional
 * theme.json inside the folder, so a new theme describes itself rather than
 * needing an entry in a list somewhere else.
 */
class Theme
{
    /** Where theme folders live, relative to resource_path(). */
    public const PATH = 'views/frontend/themes';

    /** View-name prefix for a theme's own views. */
    public const PREFIX = 'frontend.themes.';

    /** Preferred fallback when the stored theme is gone. Any installed theme will do if this one is missing too. */
    public const FALLBACK = 'theme_default';

    /** Shown when no theme at all is installed. */
    public const MISSING_VIEW = 'frontend.theme-missing';

    /**
     * The views a theme must ship to be selectable.
     *
     * Every one of these is reached directly by a controller, a Livewire
     * component or a layout, so a theme without them cannot serve the site.
     * Anything else a theme includes is its own business.
     */
    public const REQUIRED = [
        'layouts.app',
        'home',
        'department',
        'contact',
        'profile',
        'publication',
        'partials.head',
        'partials.header',
        'partials.footer',
        'partials.teacher_card',
        'partials.pagination',
        'partials.social_icon',
        'livewire.teacher-search',
        'livewire.department-search',
    ];

    /** Image names accepted as a theme's screenshot, in order of preference. */
    public const SCREENSHOTS = ['screenshot.png', 'screenshot.jpg', 'screenshot.jpeg', 'screenshot.webp', 'screenshot.svg'];

    /**
     * The folder scan is memoised because the filesystem does not change while a
     * request is being served.
     *
     * The resolved active theme deliberately is not. It depends on a setting,
     * and a static that outlives the request goes stale the moment more than one
     * request shares a process — which is exactly what a test run is, and what
     * Octane or a queue worker would be. The lookup behind it is cache-backed
     * and cheap.
     */
    protected static ?array $installed = null;

    /** So a missing theme logs once per process rather than once per page view. */
    protected static bool $warned = false;

    /**
     * Every usable theme, keyed by slug, each with its metadata.
     *
     * @return array<string, array{slug: string, name: string, description: string|null, author: string|null, version: string|null, screenshot: string|null, path: string}>
     */
    public static function installed(): array
    {
        if (static::$installed !== null) {
            return static::$installed;
        }

        $root = resource_path(static::PATH);
        $themes = [];

        if (is_dir($root)) {
            foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $slug = basename($dir);

                if (static::missingViews($slug) !== []) {
                    continue;
                }

                $themes[$slug] = static::readMeta($slug, $dir);
            }
        }

        ksort($themes);

        return static::$installed = $themes;
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(static::installed());
    }

    /** Slug => display name, for a settings dropdown. */
    public static function options(): array
    {
        return array_map(fn (array $meta) => $meta['name'], static::installed());
    }

    public static function isInstalled(string $slug): bool
    {
        return array_key_exists($slug, static::installed());
    }

    /**
     * Which required views a theme folder is missing. Empty means usable.
     *
     * @return array<int, string>
     */
    public static function missingViews(string $slug): array
    {
        $dir = resource_path(static::PATH . '/' . $slug);

        if ($slug === '' || ! is_dir($dir)) {
            return static::REQUIRED;
        }

        $missing = [];

        foreach (static::REQUIRED as $view) {
            if (! is_file($dir . '/' . str_replace('.', '/', $view) . '.blade.php')) {
                $missing[] = $view;
            }
        }

        return $missing;
    }

    /**
     * The theme to render with.
     *
     * Falls back rather than failing: the stored setting is a name, and names
     * outlive the folders they point at.
     */
    public static function active(): string
    {
        if ($preview = static::previewOverride()) {
            return $preview;
        }

        $stored = trim((string) Setting::get('active_theme', static::FALLBACK));

        if (static::isInstalled($stored)) {
            return $stored;
        }

        $fallback = static::isInstalled(static::FALLBACK)
            ? static::FALLBACK
            : (static::slugs()[0] ?? '');

        if ($stored !== '' && ! static::$warned) {
            static::$warned = true;

            Log::warning('Active theme is not installed; falling back.', [
                'requested' => $stored,
                'using' => $fallback ?: '(none installed)',
            ]);
        }

        return $fallback;
    }

    /**
     * Resolve one of a theme's views to a name Blade can render.
     *
     * Callers pass the bare name — 'home', 'partials.header' — and never build
     * the prefix themselves, which is what made a deleted theme fatal.
     */
    public static function view(string $name, ?string $slug = null): string
    {
        $slug ??= static::active();

        if ($slug !== '') {
            $candidate = static::PREFIX . $slug . '.' . $name;

            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        // A theme that passed missingViews() should never land here, so this is
        // for optional views only — or for the case where nothing is installed.
        foreach (static::slugs() as $other) {
            $candidate = static::PREFIX . $other . '.' . $name;

            if ($other !== $slug && View::exists($candidate)) {
                return $candidate;
            }
        }

        return static::MISSING_VIEW;
    }

    /** @return array{slug: string, name: string, description: string|null, author: string|null, version: string|null, screenshot: string|null, path: string}|null */
    public static function meta(string $slug): ?array
    {
        return static::installed()[$slug] ?? null;
    }

    /**
     * Absolute path to a theme's screenshot, or null when it ships none.
     *
     * The file lives inside the theme folder so a theme stays one deletable
     * unit; resources/ is not web-served, so it is streamed by a route rather
     * than linked directly.
     */
    public static function screenshotPath(string $slug): ?string
    {
        $meta = static::meta($slug);

        return $meta['screenshot'] ?? null;
    }

    /** The query parameter that previews a theme without switching the site to it. */
    public const PREVIEW_PARAM = 'preview_theme';

    /**
     * A theme requested with ?preview_theme=, for someone allowed to change the
     * setting anyway.
     *
     * This is how an administrator sees a theme on the real site before
     * committing to it. Gated rather than public: without the check, any visitor
     * could pin themselves to a theme the university did not choose, and every
     * page would exist at two addresses.
     */
    public static function previewOverride(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $slug = request()->query(static::PREVIEW_PARAM);

        if (! is_string($slug) || $slug === '' || ! static::isInstalled($slug)) {
            return null;
        }

        $user = auth()->user();

        if (! $user || ! $user->can('View:SystemSettings', Setting::class)) {
            return null;
        }

        return $slug;
    }

    /** Drop the per-request memo. For tests and for the settings page after a save. */
    public static function forget(): void
    {
        static::$installed = null;
        static::$warned = false;
    }

    /**
     * Read theme.json if present, filling in sensible values from the folder
     * name when it is not. A theme should not need one to work.
     */
    protected static function readMeta(string $slug, string $dir): array
    {
        $meta = [
            'slug' => $slug,
            'name' => Str::title(str_replace(['_', '-'], ' ', $slug)),
            'description' => null,
            'author' => null,
            'version' => null,
            'screenshot' => null,
            'path' => $dir,
        ];

        $file = $dir . '/theme.json';
        $declared = [];

        if (is_file($file)) {
            $declared = json_decode((string) file_get_contents($file), true) ?: [];

            if (is_array($declared)) {
                foreach (['name', 'description', 'author', 'version'] as $key) {
                    if (filled($declared[$key] ?? null) && is_string($declared[$key])) {
                        $meta[$key] = $declared[$key];
                    }
                }
            }
        }

        // A declared screenshot wins; otherwise look for the conventional names.
        // basename() keeps a theme.json from pointing outside its own folder.
        $candidates = static::SCREENSHOTS;

        if (is_string($declared['screenshot'] ?? null)) {
            array_unshift($candidates, basename($declared['screenshot']));
        }

        foreach ($candidates as $candidate) {
            if (is_file($dir . '/' . $candidate)) {
                $meta['screenshot'] = $dir . '/' . $candidate;
                break;
            }
        }

        return $meta;
    }
}
