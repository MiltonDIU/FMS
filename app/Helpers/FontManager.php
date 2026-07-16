<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Resolves the active frontend fonts for a given theme.
 *
 * Each theme can configure three font roles (sans / display / mono). A role
 * may point to either a built-in Google Font preset or a custom "installed"
 * font (uploaded file or external URL). The resolved output is injected into
 * the theme <head>: Google <link> tags for presets, @font-face blocks for
 * custom fonts, and --font-* CSS variables consumed by Tailwind's @theme.
 */
class FontManager
{
    /**
     * Curated Google Font presets. Key => [label, family, google weights].
     */
    public const PRESETS = [
        'inter'          => ['label' => 'Inter (Sans)',          'family' => 'Inter',          'weights' => '300;400;500;600;700'],
        'roboto'         => ['label' => 'Roboto (Sans)',         'family' => 'Roboto',         'weights' => '300;400;500;700'],
        'open-sans'      => ['label' => 'Open Sans (Sans)',      'family' => 'Open Sans',      'weights' => '400;600;700'],
        'poppins'        => ['label' => 'Poppins (Sans)',        'family' => 'Poppins',        'weights' => '400;500;600;700'],
        'montserrat'     => ['label' => 'Montserrat (Display)',  'family' => 'Montserrat',     'weights' => '400;600;700;800'],
        'playfair'       => ['label' => 'Playfair Display',      'family' => 'Playfair Display','weights' => '400;600;700'],
        'space-grotesk'  => ['label' => 'Space Grotesk (Display)','family' => 'Space Grotesk',  'weights' => '400;500;600;700'],
        'jetbrains-mono' => ['label' => 'JetBrains Mono',         'family' => 'JetBrains Mono',  'weights' => '400;500'],
        'roboto-mono'    => ['label' => 'Roboto Mono',            'family' => 'Roboto Mono',    'weights' => '400;500'],
        'source-code-pro'=> ['label' => 'Source Code Pro',       'family' => 'Source Code Pro','weights' => '400;500'],
    ];

    public const ROLES = ['sans', 'display', 'mono'];

    /**
     * Default font per role (used when nothing is configured).
     */
    public const DEFAULTS = [
        'sans'   => 'inter',
        'display'=> 'space-grotesk',
        'mono'   => 'jetbrains-mono',
    ];

    /**
     * Combined options for a font-role select: presets + installed custom fonts.
     *
     * @return array<string,string>
     */
    public static function optionsForSelect(string $themeSlug): array
    {
        $options = [];
        foreach (self::PRESETS as $key => $p) {
            $options[$key] = $p['label'];
        }

        foreach (self::customFonts($themeSlug) as $font) {
            $options['custom:' . $font['id']] = '★ ' . ($font['name'] ?? $font['id']) . ' (Custom)';
        }

        return $options;
    }

    /**
     * Installed custom fonts for a theme (from settings JSON).
     *
     * @return array<int,array{id:string,name:string,file:?string,url:?string,family:?string,format:?string,weight?:string}>
     */
    public static function customFonts(?string $themeSlug = null): array
    {
        $raw = Setting::get('global_custom_fonts', null);

        if ($raw === null) {
            // Migrate from old theme-specific keys
            $globalFonts = [];
            $seenIds = [];
            $themesPath = resource_path('views/frontend/themes');
            $themes = ['theme_default', 'theme_diu'];
            if (is_dir($themesPath)) {
                $themes = array_merge($themes, array_map('basename', array_filter(glob($themesPath . '/*'), 'is_dir')));
            }
            $themes = array_unique($themes);

            foreach ($themes as $slug) {
                $themeRaw = Setting::get(self::settingKey($slug, 'custom_fonts'), []);
                $themeFonts = [];
                if (is_array($themeRaw)) {
                    $themeFonts = $themeRaw;
                } elseif (is_string($themeRaw) && trim($themeRaw) !== '') {
                    $decoded = json_decode($themeRaw, true);
                    if (is_array($decoded)) {
                        $themeFonts = $decoded;
                    }
                }
                foreach ($themeFonts as $f) {
                    if (isset($f['id']) && ! in_array($f['id'], $seenIds, true)) {
                        $globalFonts[] = $f;
                        $seenIds[] = $f['id'];
                    }
                }
            }

            Setting::set('global_custom_fonts', $globalFonts);
            return $globalFonts;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Resolve the chosen value for a role into its CSS font-family stack.
     */
    public static function resolveRole(string $themeSlug, string $role): ?string
    {
        $value = Setting::get(self::settingKey($themeSlug, "font_{$role}"), self::DEFAULTS[$role] ?? null);

        if (str_starts_with((string) $value, 'custom:')) {
            $id = substr($value, strlen('custom:'));
            foreach (self::customFonts($themeSlug) as $font) {
                if (($font['id'] ?? null) === $id) {
                    $family = $font['family'] ?? $font['name'] ?? $id;
                    return "'" . str_replace("'", '', $family) . "', ui-sans-serif, system-ui, sans-serif";
                }
            }
            return null;
        }

        if (isset(self::PRESETS[$value])) {
            return "'" . self::PRESETS[$value]['family'] . "', ui-sans-serif, system-ui, sans-serif";
        }

        return null;
    }

    /**
     * Google Fonts <link> tags needed for the selected presets (deduped).
     */
    public static function googleLinks(string $themeSlug): string
    {
        $families = [];
        foreach (self::ROLES as $role) {
            $value = Setting::get(self::settingKey($themeSlug, "font_{$role}"), self::DEFAULTS[$role] ?? null);
            if (isset(self::PRESETS[$value])) {
                $p = self::PRESETS[$value];
                $families[$p['family']] = $p['family'] . ':' . $p['weights'];
            }
        }

        if (empty($families)) {
            return '';
        }

        $query = 'family=' . implode('&family=', array_map('rawurlencode', $families));
        $href = 'https://fonts.googleapis.com/css2?' . $query . '&display=swap';

        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
             . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
             . '<link href="' . $href . '" rel="stylesheet">';
    }

    /**
     * @font-face blocks for installed custom fonts that are referenced by a role.
     */
    public static function fontFaceBlocks(string $themeSlug): string
    {
        $usedIds = [];
        foreach (self::ROLES as $role) {
            $value = Setting::get(self::settingKey($themeSlug, "font_{$role}"), null);
            if (str_starts_with((string) $value, 'custom:')) {
                $usedIds[] = substr($value, strlen('custom:'));
            }
        }

        if (empty($usedIds)) {
            return '';
        }

        $blocks = [];
        foreach (self::customFonts($themeSlug) as $font) {
            if (! in_array($font['id'] ?? null, $usedIds, true)) {
                continue;
            }
            $family = $font['family'] ?? $font['name'] ?? $font['id'];
            $family = "'" . str_replace("'", '', $family) . "'";
            $weight = $font['weight'] ?? '400';
            $src = self::fontSrc($font);
            if (! $src) {
                continue;
            }
            $blocks[] = "@font-face {\n"
                . "    font-family: {$family};\n"
                . "    src: {$src};\n"
                . "    font-weight: {$weight};\n"
                . "    font-display: swap;\n"
                . "}";
        }

        return implode("\n", $blocks);
    }

    /**
     * Build the src for a custom font: local uploaded file or external URL.
     */
    protected static function fontSrc(array $font): ?string
    {
        if (! empty($font['file'])) {
            $file = $font['file'];
            $path = str_starts_with($file, 'fonts/') ? $file : 'fonts/' . ltrim($file, '/');
            $url = asset('storage/' . $path);
            $format = $font['format'] ?? self::guessFormat($path);
            return "url('{$url}') format('{$format}')";
        }

        if (! empty($font['url'])) {
            $url = $font['url'];
            // External stylesheet (e.g. Google css2) is handled via <link>, not @font-face.
            if (str_ends_with($url, '.css') || str_contains($url, 'css2?') || str_contains($url, 'fonts.googleapis')) {
                return null;
            }
            $format = $font['format'] ?? self::guessFormat($url);
            return "url('{$url}') format('{$format}')";
        }

        return null;
    }

    /**
     * Any external stylesheet links (e.g. Google css2) referenced by installed fonts.
     */
    public static function customStylesheetLinks(string $themeSlug): string
    {
        $links = [];
        foreach (self::customFonts($themeSlug) as $font) {
            if (! empty($font['url']) && (str_ends_with($font['url'], '.css') || str_contains($font['url'], 'css2?') || str_contains($font['url'], 'fonts.googleapis'))) {
                $links[] = '<link href="' . $font['url'] . '" rel="stylesheet">';
            }
        }
        return implode("\n", $links);
    }

    /**
     * Full <style> block injected into <head>: @font-face + --font-* vars.
     */
    public static function cssBlock(string $themeSlug): string
    {
        $fontFaces = self::fontFaceBlocks($themeSlug);

        $vars = [];
        foreach (self::ROLES as $role) {
            $stack = self::resolveRole($themeSlug, $role);
            if ($stack) {
                $vars['--font-' . $role] = $stack;
            }
        }

        $baseSize = Setting::get(self::settingKey($themeSlug, 'font_base_size'), '16px');
        $sansWeight = Setting::get(self::settingKey($themeSlug, 'font_sans_weight'), '400');
        $displayWeight = Setting::get(self::settingKey($themeSlug, 'font_display_weight'), '700');
        $monoWeight = Setting::get(self::settingKey($themeSlug, 'font_mono_weight'), '400');

        if (empty($vars) && ! $fontFaces && $baseSize === '16px' && $sansWeight === '400' && $displayWeight === '700' && $monoWeight === '400') {
            return '';
        }

        $lines = [];
        if ($fontFaces) {
            $lines[] = $fontFaces;
        }
        if ($vars) {
            $varLines = array_map(fn ($k, $v) => "    {$k}: {$v};", array_keys($vars), array_values($vars));
            $lines[] = ":root {\n" . implode("\n", $varLines) . "\n}";
        }

        $lines[] = "html {\n    font-size: {$baseSize};\n}";
        $lines[] = "body {\n    font-weight: {$sansWeight};\n}";
        $lines[] = "h1, h2, h3, h4, h5, h6, .font-display {\n    font-weight: {$displayWeight};\n}";
        $lines[] = "code, pre, .font-mono {\n    font-weight: {$monoWeight};\n}";

        return "<style>\n" . implode("\n\n", $lines) . "\n</style>";
    }

    protected static function guessFormat(string $path): string
    {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
        return match ($ext) {
            'woff2' => 'woff2',
            'woff'  => 'woff',
            'ttf'   => 'truetype',
            'otf'   => 'opentype',
            default => 'woff2',
        };
    }

    public static function settingKey(string $themeSlug, string $suffix): string
    {
        return "theme_{$themeSlug}_{$suffix}";
    }
}
