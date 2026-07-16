<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Resolves the active DIU color palette used across the frontend.
 *
 * A palette is derived from a single base color. Shades (dark, light, hover)
 * and a complementary accent are generated algorithmically so the admin only
 * ever picks one color (or a named preset) and the whole UI follows.
 */
class ColorPalette
{
    /**
     * Named preset palettes keyed by a base hex.
     */
    public const PRESETS = [
        'diu'     => ['label' => 'DIU Blue',     'base' => '#034ea2'],
        'emerald' => ['label' => 'Emerald',      'base' => '#047857'],
        'violet'  => ['label' => 'Violet',       'base' => '#6d28d9'],
        'rose'    => ['label' => 'Rose',         'base' => '#be123c'],
        'amber'   => ['label' => 'Amber',        'base' => '#b45309'],
        'teal'    => ['label' => 'Teal',         'base' => '#0f766e'],
        'slate'   => ['label' => 'Slate',        'base' => '#334155'],
        'crimson'  => ['label' => 'Crimson',      'base' => '#9f1239'],
    ];

    /**
     * The exact original DIU theme colors (pre color-system). Used as the
     * default palette and as the Reset target.
     */
    public const ORIGINAL = [
        '--color-diu-primary-dark'   => '#002652',
        '--color-diu-primary'        => '#034ea2',
        '--color-diu-primary-light'  => '#0072bc',
        '--color-diu-primary-hover'  => '#023b7a',
        '--color-diu-secondary-dark' => '#002652',
        '--color-diu-secondary'      => '#034ea2',
        '--color-diu-secondary-light' => '#0072bc',
        '--color-diu-accent'         => '#0072bc',
        '--color-diu-accent-light'   => '#4dc3e6',
        '--color-diu-accent-hover'   => '#005fa3',
    ];

    /**
     * Setting keys for individually overridable colors (manual management).
     */
    public const OVERRIDE_KEYS = [
        'diu_primary_dark',
        'diu_primary',
        'diu_primary_light',
        'diu_primary_hover',
        'diu_secondary_dark',
        'diu_secondary',
        'diu_secondary_light',
        'diu_accent',
        'diu_accent_light',
        'diu_accent_hover',
    ];

    /**
     * Human-readable metadata for each color: label + where it is used.
     */
    public const COLOR_META = [
        'diu_primary'        => ['label' => 'Primary',        'usage' => 'Main brand color: buttons, links, active states, headers.'],
        'diu_primary_dark'   => ['label' => 'Primary Dark',   'usage' => 'Top micro-bar & statistics bar background (dark gradient).'],
        'diu_primary_light'  => ['label' => 'Primary Light',  'usage' => 'Page background tint, soft highlights, hover chips.'],
        'diu_primary_hover'  => ['label' => 'Primary Hover',  'usage' => 'Button hover / pressed background.'],
        'diu_secondary'      => ['label' => 'Secondary',      'usage' => 'Secondary gradient stops (statistics bar).'],
        'diu_secondary_dark' => ['label' => 'Secondary Dark', 'usage' => 'Dark gradient stops for bars.'],
        'diu_secondary_light'=> ['label' => 'Secondary Light','usage' => 'Light gradient stops / tints.'],
        'diu_accent'         => ['label' => 'Accent',         'usage' => 'Icon & link pop color on colored surfaces (login icon).'],
        'diu_accent_light'   => ['label' => 'Accent Light',   'usage' => 'Header bar icons (faculty/dept stats).'],
        'diu_accent_hover'   => ['label' => 'Accent Hover',   'usage' => 'Accent hover state.'],
    ];

    /**
     * Default base used when nothing is configured.
     */
    public const DEFAULT_BASE = '#034ea2';

    /**
     * Returns the available preset options for a select field.
     */
    public static function presetOptions(): array
    {
        return collect(self::PRESETS)->mapWithKeys(fn ($p, $k) => [$k => $p['label']])->all();
    }

    /**
     * Resolve the full set of CSS custom properties for the active palette.
     *
     * Starts from the generated set (preset / manual base), then layers any
     * individually overridden colors on top so admins can fine-tune each role.
     *
     * @return array<string,string> map of --color-* names to hex values
     */
    public static function resolve(): array
    {
        $manual = trim((string) Setting::get('diu_primary_color', ''));
        $presetKey = Setting::get('diu_color_palette', 'diu');

        // The "diu" preset reproduces the exact original theme colors.
        if (($presetKey === 'diu' || ! isset(self::PRESETS[$presetKey])) && $manual === '') {
            $set = self::ORIGINAL;
        } else {
            $base = $manual !== '' && self::isValidHex($manual)
                ? $manual
                : (self::PRESETS[$presetKey]['base'] ?? self::DEFAULT_BASE);
            $set = self::fromBase($base);
        }

        // Layer individual overrides on top of the generated/default set.
        // Override setting keys use underscores (diu_accent_light) while CSS
        // variables use hyphens (--color-diu-accent-light); map accordingly.
        foreach (self::OVERRIDE_KEYS as $key) {
            $val = trim((string) Setting::get($key, ''));
            if ($val !== '' && self::isValidHex($val)) {
                $cssKey = '--color-' . str_replace('_', '-', $key);
                $set[$cssKey] = $val;
            }
        }

        // Always keep the readable foreground tokens in sync with primary.
        $set['--diu-on-primary']      = self::readableOn($set['--color-diu-primary']);
        $set['--diu-on-primary-dark'] = self::readableOn($set['--color-diu-primary-dark']);
        $set['--diu-on-secondary']    = self::readableOn($set['--color-diu-secondary']);
        $set['--diu-on-accent']       = self::readableOn($set['--color-diu-accent']);

        return $set;
    }

    /**
     * Reset all color settings back to the original DIU theme defaults.
     */
    public static function resetToDefaults(): void
    {
        Setting::set('diu_color_palette', 'diu');
        Setting::set('diu_primary_color', null);
        foreach (self::OVERRIDE_KEYS as $key) {
            Setting::set($key, null);
        }
        self::forgetCache();
    }

    /**
     * Clear the cached Setting values so frontend reflects changes immediately.
     */
    public static function forgetCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('setting.diu_color_palette');
        \Illuminate\Support\Facades\Cache::forget('setting.diu_primary_color');
        foreach (self::OVERRIDE_KEYS as $key) {
            \Illuminate\Support\Facades\Cache::forget("setting.{$key}");
        }
    }

    /**
     * Metadata for the admin manual-color form.
     *
     * @return array<int,array{key:string,label:string,usage:string}>
     */
    public static function colorFields(): array
    {
        return collect(self::COLOR_META)
            ->map(fn ($m, $key) => ['key' => $key, 'label' => $m['label'], 'usage' => $m['usage']])
            ->values()
            ->all();
    }

    /**
     * The auto-generated (palette-based, non-overridden) value for a single
     * override key. Used by the admin UI to show admins what the color will be
     * if they leave the override empty.
     */
    public static function defaultValueFor(string $key): ?string
    {
        if (! array_key_exists($key, self::COLOR_META)) {
            return null;
        }

        $generated = self::resolve();
        $cssKey = '--color-' . str_replace('_', '-', $key);

        return $generated[$cssKey] ?? null;
    }

    /**
     * Generate the full shade set from a single base hex.
     *
     * @return array<string,string>
     */
    public static function fromBase(string $base): array
    {
        [$h, $s, $l] = self::hexToHsl($base);

        $primary      = $base;
        $primaryDark  = self::hslToHex($h, $s, max(0, $l - 22));
        $primaryLight = self::hslToHex($h, $s, min(100, $l + 18));
        $primaryHover = self::hslToHex($h, $s, max(0, $l - 10));

        // Accent: a contrasting (complementary) hue so icons/links pop on the
        // primary-colored bars instead of blending into the same hue.
        $accentH = ($h + 150) % 360;
        $accentS = min(100, max(60, $s + 6));
        $accentL = min(62, max(38, $l + 8));

        $accent        = self::hslToHex($accentH, $accentS, $accentL);
        $accentLight   = self::hslToHex($accentH, $accentS, min(78, max(64, $accentL + 16)));
        $accentHover   = self::hslToHex($accentH, $accentS, max(0, $accentL - 10));

        // Readable foreground colors for text/icons placed on each brand surface.
        $onPrimary    = self::readableOn($primary);
        $onPrimaryDark = self::readableOn($primaryDark);
        $onSecondary  = self::readableOn($primary);
        $onAccent     = self::readableOn($accent);

        return [
            '--color-diu-primary-dark'   => $primaryDark,
            '--color-diu-primary'        => $primary,
            '--color-diu-primary-light'  => $primaryLight,
            '--color-diu-primary-hover'  => $primaryHover,
            '--color-diu-secondary-dark' => $primaryDark,
            '--color-diu-secondary'      => $primary,
            '--color-diu-secondary-light' => $primaryLight,
            '--color-diu-accent'         => $accent,
            '--color-diu-accent-light'   => $accentLight,
            '--color-diu-accent-hover'   => $accentHover,
            '--diu-on-primary'           => $onPrimary,
            '--diu-on-primary-dark'      => $onPrimaryDark,
            '--diu-on-secondary'         => $onSecondary,
            '--diu-on-accent'            => $onAccent,
        ];
    }

    /**
     * Pick a readable foreground (#ffffff or near-black) for a given background
     * using WCAG relative luminance + contrast ratio.
     */
    private static function readableOn(string $hex): string
    {
        $lum = self::relativeLuminance($hex);
        $white = 1.0;
        $black = 0.04;

        $contrastWhite = (max($lum, $white) + 0.05) / (min($lum, $white) + 0.05);
        $contrastBlack = (max($lum, $black) + 0.05) / (min($lum, $black) + 0.05);

        return $contrastWhite >= $contrastBlack ? '#ffffff' : '#0b0f1a';
    }

    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $channel = fn (string $c) => (function (float $v) {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        })(hexdec($c));

        $r = $channel(substr($hex, 0, 2));
        $g = $channel(substr($hex, 2, 2));
        $b = $channel(substr($hex, 4, 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Build an inline CSS :root block for injecting into <head>.
     */
    public static function cssRootBlock(): string
    {
        $vars = self::resolve();
        $lines = array_map(fn ($k, $v) => "    {$k}: {$v};", array_keys($vars), array_values($vars));

        return ":root {\n" . implode("\n", $lines) . "\n}";
    }

    private static function isValidHex(string $value): bool
    {
        return (bool) preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value);
    }

    /**
     * @return array{0:float,1:float,2:float} [h(0-360), s(0-100), l(0-100)]
     */
    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        $h = $s = 0;
        $d = $max - $min;

        if ($d !== 0.0) {
            $s = $d / (1 - abs(2 * $l - 1));
            switch ($max) {
                case $r:
                    $h = 60 * fmod((($g - $b) / $d), 6);
                    break;
                case $g:
                    $h = 60 * ((($b - $r) / $d) + 2);
                    break;
                case $b:
                    $h = 60 * ((($r - $g) / $d) + 4);
                    break;
            }
            if ($h < 0) {
                $h += 360;
            }
        }

        return [round($h, 2), round($s * 100, 2), round($l * 100, 2)];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360);
        if ($h < 0) {
            $h += 360;
        }
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        $r = $g = $b = 0;
        if ($h < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($h < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($h < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($h < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($h < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }

        $toHex = fn (float $v) => str_pad(dechex((int) round(($v + $m) * 255)), 2, '0', STR_PAD_LEFT);

        return '#' . $toHex($r) . $toHex($g) . $toHex($b);
    }
}
