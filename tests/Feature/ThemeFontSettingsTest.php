<?php

namespace Tests\Feature;

use App\Helpers\FontManager;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks the font resolution order:
 *   1. a real value stored in `settings`
 *   2. FontManager's hardcoded defaults
 *   3. the build-time @theme tokens in each theme's CSS
 *
 * Step 2 exists because these rows are seeded and can end up holding NULL,
 * which Setting::get() returns verbatim instead of falling back to its default.
 */
class ThemeFontSettingsTest extends TestCase
{
    protected string $key;

    protected mixed $original = null;

    protected bool $existed = false;

    protected mixed $originalLibrary = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = FontManager::settingKey('theme_diu', 'font_sans');

        $row = DB::table('settings')->where('key', $this->key)->first();
        $this->existed = $row !== null;
        $this->original = $row?->value;

        // Restored alongside the role below. These tests share a database, and
        // the runner does not guarantee declaration order, so each one has to
        // put every value it touches back.
        $this->originalLibrary = DB::table('settings')->where('key', 'global_custom_fonts')->value('value');
    }

    protected function tearDown(): void
    {
        DB::table('settings')->where('key', $this->key)->delete();

        if ($this->existed) {
            DB::table('settings')->insert([
                'key' => $this->key,
                'value' => $this->original,
                'group' => 'system',
                'type' => 'string',
                'label' => 'Sans Font — Theme Diu',
                'description' => 'Per-theme typography setting for the theme_diu frontend theme.',
                'is_public' => false,
                'sort_order' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')
            ->where('key', 'global_custom_fonts')
            ->update(['value' => $this->originalLibrary]);

        Cache::flush();

        parent::tearDown();
    }

    protected function setStoredFont(mixed $value): void
    {
        DB::table('settings')->where('key', $this->key)->update(['value' => $value]);
        Cache::flush();
    }

    /**
     * Built from PRESETS rather than written out, so tuning a preset's weights
     * does not turn into a failing assertion here.
     */
    protected function expectedRequest(string $preset): string
    {
        $spec = FontManager::PRESETS[$preset];

        return str_replace(' ', '+', $spec['family']) . ':wght@' . $spec['weights'];
    }

    public function test_a_stored_value_overrides_the_hardcoded_default(): void
    {
        $this->setStoredFont('poppins');

        $this->assertStringContainsString("'Poppins'", FontManager::cssBlock('theme_diu'));
        $this->assertStringContainsString($this->expectedRequest('poppins'), FontManager::googleLinks('theme_diu'));
    }

    public function test_a_null_stored_value_falls_back_to_the_hardcoded_default(): void
    {
        $this->setStoredFont(null);

        $this->assertStringContainsString("'Manrope'", FontManager::cssBlock('theme_diu'));
        $this->assertStringContainsString($this->expectedRequest('manrope'), FontManager::googleLinks('theme_diu'));
    }

    public function test_a_missing_row_falls_back_to_the_hardcoded_default(): void
    {
        DB::table('settings')->where('key', $this->key)->delete();
        Cache::flush();

        $this->assertStringContainsString("'Manrope'", FontManager::cssBlock('theme_diu'));
        $this->assertStringContainsString($this->expectedRequest('manrope'), FontManager::googleLinks('theme_diu'));
    }

    /**
     * The themes use font-medium through font-black, so a preset that stops at
     * 700 leaves the browser synthesising the heavier cuts.
     */
    public function test_presets_cover_the_weights_the_themes_actually_use(): void
    {
        foreach (['manrope', 'montserrat', 'inter', 'poppins'] as $preset) {
            $weights = FontManager::PRESETS[$preset]['weights'];

            if (str_contains($weights, '..')) {
                [, $max] = explode('..', $weights);
                $this->assertGreaterThanOrEqual(800, (int) $max, "{$preset} range stops too low");

                continue;
            }

            $this->assertContains('800', explode(';', $weights), "{$preset} is missing weight 800");
        }
    }

    /**
     * css2 rejects a family without its axis prefix, which would silently leave
     * every theme unstyled.
     */
    public function test_google_font_urls_carry_the_wght_axis_prefix(): void
    {
        foreach (['theme_default', 'theme_diu', 'theme_modern'] as $theme) {
            preg_match_all('/family=([^&"]+)/', FontManager::googleLinks($theme), $matches);

            $this->assertNotEmpty($matches[1], "{$theme} requested no font families");

            foreach ($matches[1] as $family) {
                $this->assertStringContainsString(':wght@', $family);
            }
        }
    }

    public function test_bangla_trails_the_latin_families_so_mixed_text_resolves(): void
    {
        $css = FontManager::cssBlock('theme_diu');

        $this->assertStringContainsString("--font-sans: 'Manrope', 'Mina'", $css);
        $this->assertStringContainsString("--font-display: 'Montserrat', 'Mina'", $css);
        $this->assertStringContainsString("--font-bangla: 'Mina'", $css);
    }

    /**
     * A Google Fonts family cannot go into a dompdf PDF — only woff2 is served
     * and dompdf reads neither woff2 nor a bare stylesheet. Emitting nothing
     * keeps the PDF template's own stack instead of naming a font that would be
     * silently substituted.
     */
    public function test_pdf_css_is_empty_when_the_theme_font_cannot_be_embedded(): void
    {
        $this->setStoredFont('manrope');

        $this->assertSame('', FontManager::pdfCssBlock('theme_diu'));
    }

    public function test_pdf_css_embeds_an_uploaded_truetype_font(): void
    {
        $directory = storage_path('app/public/fonts');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/phpunit-probe.ttf';
        copy(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'), $path);

        try {
            Setting::set('global_custom_fonts', [[
                'id' => 'phpunit-probe',
                'source' => 'upload',
                'name' => 'Probe Sans',
                'family' => 'Probe Sans',
                'file' => 'fonts/phpunit-probe.ttf',
            ]]);
            $this->setStoredFont('custom:phpunit-probe');

            $css = FontManager::pdfCssBlock('theme_diu');

            $this->assertStringContainsString("font-family: 'Probe Sans'", $css);
            $this->assertStringContainsString("format('truetype')", $css);
            $this->assertStringContainsString($path, $css);
        } finally {
            @unlink($path);
            Cache::flush();
        }
    }

    public function test_an_uploaded_woff2_is_not_offered_to_the_pdf_renderer(): void
    {
        $directory = storage_path('app/public/fonts');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/phpunit-probe.woff2';
        file_put_contents($path, 'wOF2 not a real font');

        try {
            Setting::set('global_custom_fonts', [[
                'id' => 'phpunit-probe',
                'source' => 'upload',
                'name' => 'Probe Sans',
                'family' => 'Probe Sans',
                'file' => 'fonts/phpunit-probe.woff2',
            ]]);
            $this->setStoredFont('custom:phpunit-probe');

            $this->assertSame('', FontManager::pdfCssBlock('theme_diu'));
        } finally {
            @unlink($path);
            Cache::flush();
        }
    }
}
