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
     *
     * 'weights' is the value of the css2 `wght` axis: either a variable-font
     * range ('200..800') or a ';'-separated list of static weights ('400;700').
     * Every value here has been checked against the live css2 API — an axis the
     * family does not publish makes the whole request 400, which silently drops
     * every font on the page.
     *
     * The ranges deliberately reach 900 where the family allows it, because the
     * themes use font-medium through font-black. Requesting a narrower set makes
     * the browser synthesise the missing weights, which looks visibly worse than
     * the real cut.
     *
     * Set 'bangla' => true for families that carry the Bengali unicode block,
     * so they can be used as an automatic fallback for Bangla text.
     */
    public const PRESETS = [
        'manrope'        => ['label' => 'Manrope (Sans, variable)',  'family' => 'Manrope',           'weights' => '200..800'],
        'inter'          => ['label' => 'Inter (Sans, variable)',    'family' => 'Inter',             'weights' => '100..900'],
        'roboto'         => ['label' => 'Roboto (Sans, variable)',   'family' => 'Roboto',            'weights' => '100..900'],
        'open-sans'      => ['label' => 'Open Sans (Sans, variable)','family' => 'Open Sans',         'weights' => '300..800'],
        'poppins'        => ['label' => 'Poppins (Sans)',            'family' => 'Poppins',           'weights' => '300;400;500;600;700;800;900'],
        'montserrat'     => ['label' => 'Montserrat (Display, variable)', 'family' => 'Montserrat',   'weights' => '100..900'],
        'playfair'       => ['label' => 'Playfair Display (variable)','family' => 'Playfair Display', 'weights' => '400..900'],
        'space-grotesk'  => ['label' => 'Space Grotesk (Display, variable)', 'family' => 'Space Grotesk', 'weights' => '300..700'],
        'mina'           => ['label' => 'Mina (Bangla)',             'family' => 'Mina',              'weights' => '400;700', 'bangla' => true],
        'hind-siliguri'  => ['label' => 'Hind Siliguri (Bangla)',    'family' => 'Hind Siliguri',     'weights' => '300;400;500;600;700', 'bangla' => true],
        'baloo-da-2'     => ['label' => 'Baloo Da 2 (Bangla, variable)', 'family' => 'Baloo Da 2',    'weights' => '400..800', 'bangla' => true],
        'jetbrains-mono' => ['label' => 'JetBrains Mono (variable)', 'family' => 'JetBrains Mono',    'weights' => '100..800'],
        'roboto-mono'    => ['label' => 'Roboto Mono (variable)',    'family' => 'Roboto Mono',       'weights' => '100..700'],
        'source-code-pro'=> ['label' => 'Source Code Pro (variable)','family' => 'Source Code Pro',   'weights' => '200..900'],
    ];

    public const ROLES = ['sans', 'display', 'mono', 'bangla'];

    /**
     * Default font per role (used when nothing is configured).
     *
     * Matches daffodilvarsity.edu.bd: Manrope for body/UI, Montserrat for
     * headings, Mina for Bangla.
     */
    public const DEFAULTS = [
        'sans'   => 'manrope',
        'display'=> 'montserrat',
        'mono'   => 'jetbrains-mono',
        'bangla' => 'mina',
    ];

    /**
     * Default root font size.
     */
    public const SIZE_DEFAULT = '16px';

    /**
     * Weights requested for a custom Google family when the admin leaves the
     * field blank. Covers font-normal through font-black, the span the themes
     * use.
     *
     * Deliberately a static list rather than a range. css2 tolerates listing a
     * static weight a family does not publish, but answers 400 for a range like
     * "100..900" on a family that ships no variable axis — and one bad family
     * fails the whole request, dropping every font on the page.
     */
    public const FALLBACK_WEIGHTS = '400;500;600;700;800;900';

    /**
     * Default weight per role. Bangla has no weight control of its own; it
     * inherits whichever weight the surrounding text is using.
     */
    public const WEIGHT_DEFAULTS = [
        'sans'    => '400',
        'display' => '700',
        'mono'    => '400',
    ];

    /**
     * Every per-theme setting key suffix, mapped to its default value and the
     * column type it is stored as.
     *
     * This is the single source of truth shared by three places: the runtime
     * fallback in this class, the form defaults in System Settings, and
     * ThemeSettingsSeeder. Adding a per-theme setting means adding it here.
     *
     * @return array<string,array{value:string,type:string,label:string}>
     */
    public static function settingDefaults(): array
    {
        $defaults = [];

        foreach (self::ROLES as $role) {
            $defaults["font_{$role}"] = [
                'value' => self::DEFAULTS[$role],
                'type'  => 'string',
                'label' => ucfirst($role) . ' Font',
            ];
        }

        $defaults['font_base_size'] = [
            'value' => self::SIZE_DEFAULT,
            'type'  => 'string',
            'label' => 'Base Font Size (Root)',
        ];

        foreach (self::WEIGHT_DEFAULTS as $role => $weight) {
            $defaults["font_{$role}_weight"] = [
                'value' => $weight,
                'type'  => 'string',
                'label' => ucfirst($role) . ' Font Weight',
            ];
        }

        $defaults['footer_match_theme'] = [
            'value' => 'false',
            'type'  => 'boolean',
            'label' => 'Match Footer Background with Theme Color',
        ];

        return $defaults;
    }

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
            return self::withInferredSource($globalFonts);
        }

        if (is_array($raw)) {
            return self::withInferredSource($raw);
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::withInferredSource($decoded);
            }
        }

        return [];
    }

    /**
     * Give every entry an explicit 'source', inferring it for ones saved before
     * the field existed.
     *
     * Without this, a legacy entry would read as source-less in the System
     * Settings repeater and its upload/URL field would be hidden.
     *
     * @param  array<int,array<string,mixed>>  $fonts
     * @return array<int,array<string,mixed>>
     */
    protected static function withInferredSource(array $fonts): array
    {
        return array_map(function (array $font): array {
            if (filled($font['source'] ?? null)) {
                return $font;
            }

            $font['source'] = match (true) {
                filled($font['file'] ?? null) => 'upload',
                filled($font['url'] ?? null) => 'url',
                default => 'google',
            };

            return $font;
        }, $fonts);
    }

    /**
     * Read a typography setting, treating a blank stored value as "not set".
     *
     * Setting::get() only falls back to its default when the row is missing
     * entirely. These keys are seeded as rows holding NULL, so without this the
     * defaults below would never apply and every font role would resolve empty.
     */
    protected static function setting(string $themeSlug, string $suffix, ?string $default): ?string
    {
        $value = Setting::get(self::settingKey($themeSlug, $suffix), $default);

        return (is_string($value) && trim($value) !== '') ? $value : $default;
    }

    /**
     * The raw setting value chosen for a role (preset key or "custom:<id>").
     */
    protected static function roleValue(string $themeSlug, string $role): ?string
    {
        return self::setting($themeSlug, "font_{$role}", self::DEFAULTS[$role] ?? null);
    }

    /**
     * The custom font entry a role points at, or null if it points elsewhere.
     *
     * @return array<string,mixed>|null
     */
    protected static function roleCustomFont(string $themeSlug, string $role): ?array
    {
        $value = (string) self::roleValue($themeSlug, $role);

        if (! str_starts_with($value, 'custom:')) {
            return null;
        }

        $id = substr($value, strlen('custom:'));

        foreach (self::customFonts($themeSlug) as $font) {
            if (($font['id'] ?? null) === $id) {
                return $font;
            }
        }

        return null;
    }

    /**
     * Just the family name for a role, unquoted. Null if it cannot be resolved.
     */
    protected static function roleFamily(string $themeSlug, string $role): ?string
    {
        $value = self::roleValue($themeSlug, $role);

        if (str_starts_with((string) $value, 'custom:')) {
            $font = self::roleCustomFont($themeSlug, $role);

            if ($font === null) {
                return null;
            }

            return str_replace("'", '', $font['family'] ?? $font['name'] ?? $font['id']);
        }

        return isset(self::PRESETS[$value]) ? self::PRESETS[$value]['family'] : null;
    }

    /**
     * Is this custom font entry a Google Fonts family we should build a css2
     * request for, rather than an uploaded file or a raw stylesheet URL?
     *
     * @param  array<string,mixed>  $font
     */
    protected static function isGoogleFont(array $font): bool
    {
        return ($font['source'] ?? null) === 'google' && filled($font['family'] ?? null);
    }

    /**
     * One `family=` value for a css2 request.
     *
     * The axis prefix is mandatory: "Inter:400;700" answers 400 Bad Request and
     * takes every other family in the same request down with it, silently.
     */
    protected static function css2Family(string $family, string $weights): string
    {
        $weights = trim($weights) !== '' ? trim($weights) : self::FALLBACK_WEIGHTS;

        return str_replace(' ', '+', $family) . ':wght@' . $weights;
    }

    /**
     * Resolve the chosen value for a role into its CSS font-family stack.
     *
     * Latin-only families (Manrope, Montserrat, ...) carry no Bengali glyphs, so
     * the Bangla family is spliced in behind the sans and display stacks. The
     * browser picks it per-character, which means mixed English/Bangla content
     * renders correctly without every Bangla string needing a `font-bangla`
     * class of its own.
     */
    public static function resolveRole(string $themeSlug, string $role): ?string
    {
        $family = self::roleFamily($themeSlug, $role);

        if ($family === null) {
            return null;
        }

        $stack = ["'{$family}'"];

        if (in_array($role, ['sans', 'display'], true)) {
            $bangla = self::roleFamily($themeSlug, 'bangla');
            if ($bangla !== null && $bangla !== $family) {
                $stack[] = "'{$bangla}'";
            }
        }

        $generic = $role === 'mono'
            ? ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace']
            : ['ui-sans-serif', 'system-ui', 'sans-serif'];

        return implode(', ', array_merge($stack, $generic));
    }

    /**
     * Google Fonts <link> tags for every family a role points at, deduped.
     *
     * Covers both the curated presets and custom-library entries marked as
     * Google fonts, so an admin can use any family on Google Fonts without
     * hand-writing a css2 URL.
     */
    public static function googleLinks(string $themeSlug): string
    {
        $families = [];

        foreach (self::ROLES as $role) {
            $value = self::roleValue($themeSlug, $role);

            if (isset(self::PRESETS[$value])) {
                $preset = self::PRESETS[$value];
                $families[$preset['family']] = self::css2Family($preset['family'], $preset['weights']);

                continue;
            }

            $font = self::roleCustomFont($themeSlug, $role);

            if ($font !== null && self::isGoogleFont($font)) {
                $family = str_replace("'", '', $font['family']);
                $families[$family] = self::css2Family($family, $font['weights'] ?? self::FALLBACK_WEIGHTS);
            }
        }

        if (empty($families)) {
            return '';
        }

        // Not URL-encoded on purpose: css2 needs the literal ':', '@', ';' and
        // '..' separators, and '+' already stands in for spaces in family names.
        $query = 'family=' . implode('&family=', $families);
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
     *
     * Returns null for anything that arrives as a stylesheet instead of a font
     * file — those become <link> tags, not @font-face blocks.
     */
    protected static function fontSrc(array $font): ?string
    {
        if (self::isGoogleFont($font)) {
            return null;
        }

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
     * Hand-written stylesheet URLs from the custom font library.
     *
     * Entries marked as Google fonts are skipped: googleLinks() folds those into
     * the single css2 request, so emitting them here too would load them twice.
     */
    public static function customStylesheetLinks(string $themeSlug): string
    {
        $links = [];
        foreach (self::customFonts($themeSlug) as $font) {
            if (self::isGoogleFont($font)) {
                continue;
            }
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

        $baseSize = self::setting($themeSlug, 'font_base_size', self::SIZE_DEFAULT);
        $sansWeight = self::setting($themeSlug, 'font_sans_weight', self::WEIGHT_DEFAULTS['sans']);
        $displayWeight = self::setting($themeSlug, 'font_display_weight', self::WEIGHT_DEFAULTS['display']);
        $monoWeight = self::setting($themeSlug, 'font_mono_weight', self::WEIGHT_DEFAULTS['mono']);

        // Nothing to override: emit no <style> at all and let the build-time
        // @theme tokens in the theme's CSS stand on their own.
        if (empty($vars) && ! $fontFaces
            && $baseSize === self::SIZE_DEFAULT
            && $sansWeight === self::WEIGHT_DEFAULTS['sans']
            && $displayWeight === self::WEIGHT_DEFAULTS['display']
            && $monoWeight === self::WEIGHT_DEFAULTS['mono']) {
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

    /**
     * Formats dompdf can actually embed.
     *
     * Its stylesheet parser only accepts a URL source declared as
     * format('truetype') and php-font-lib ships no WOFF2 reader, so the woff2
     * that Google Fonts serves is unusable in a PDF. A theme font only reaches
     * the CV if it was uploaded as a .ttf or .otf.
     */
    protected const PDF_EXTENSIONS = ['ttf', 'otf'];

    /**
     * Absolute path to a custom font's uploaded file, or null if it has none.
     *
     * @param  array<string,mixed>  $font
     */
    protected static function uploadedFontPath(array $font): ?string
    {
        if (empty($font['file'])) {
            return null;
        }

        $file = $font['file'];
        $relative = str_starts_with($file, 'fonts/') ? $file : 'fonts/' . ltrim($file, '/');
        $path = storage_path('app/public/' . $relative);

        return is_file($path) ? $path : null;
    }

    /**
     * The role's font plus its file path, but only when dompdf can embed it.
     *
     * @return array{family:string,path:string}|null
     */
    /**
     * A TrueType/OpenType file for a font role, for anything that rasterises
     * text rather than shipping CSS — the PDF renderer and the share-card
     * generator.
     *
     * Falls back to a font the operating system provides, because a theme may
     * be using Google Fonts, which are fetched by the browser and never exist
     * on disk here. Returns null when nothing usable is found, and the caller
     * is expected to degrade rather than draw with a broken font.
     */
    public static function truetypePath(string $role = 'sans', ?string $themeSlug = null): ?string
    {
        $font = static::pdfFontForRole($themeSlug ?? Theme::active(), $role);

        if ($font !== null && is_file($font['path'])) {
            return $font['path'];
        }

        foreach (static::SYSTEM_FONT_FALLBACKS as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Ordinary places a sans-serif TrueType lives on a Linux host. Bold first
     * for display use; the caller picks the role it wants.
     */
    protected const SYSTEM_FONT_FALLBACKS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
    ];

    protected static function pdfFontForRole(string $themeSlug, string $role): ?array
    {
        $font = self::roleCustomFont($themeSlug, $role);

        if ($font === null) {
            return null;
        }

        $path = self::uploadedFontPath($font);

        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::PDF_EXTENSIONS, true)) {
            return null;
        }

        return [
            'family' => str_replace("'", '', $font['family'] ?? $font['name'] ?? $font['id']),
            'path' => $path,
        ];
    }

    /**
     * A <style> block for PDF templates: @font-face for any embeddable theme
     * font, plus the rules that apply it.
     *
     * Returns '' when the theme's fonts cannot be embedded, which leaves the
     * template's own font stack in place rather than silently falling back to a
     * font dompdf would substitute anyway.
     */
    public static function pdfCssBlock(?string $themeSlug = null): string
    {
        // Theme::active() rather than the raw setting: the stored name may point
        // at a theme that has since been deleted, and a CV should still render.
        $themeSlug ??= Theme::active();

        $sans = self::pdfFontForRole($themeSlug, 'sans');
        $display = self::pdfFontForRole($themeSlug, 'display');

        if ($sans === null && $display === null) {
            return '';
        }

        $faces = [];
        $rules = [];

        foreach (['sans' => $sans, 'display' => $display] as $role => $font) {
            if ($font === null) {
                continue;
            }

            // Keyed by family so one font used for both roles is embedded once.
            $faces[$font['family']] = "@font-face {\n"
                . "    font-family: '{$font['family']}';\n"
                . "    font-style: normal;\n"
                . "    src: url('{$font['path']}') format('truetype');\n"
                . '}';

            $selector = $role === 'sans' ? 'body' : 'h1, h2, h3, h4, h5, h6';
            $rules[] = "{$selector} {\n    font-family: '{$font['family']}', 'Helvetica Neue', Arial, sans-serif;\n}";
        }

        return "<style>\n" . implode("\n", $faces) . "\n\n" . implode("\n\n", $rules) . "\n</style>";
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
