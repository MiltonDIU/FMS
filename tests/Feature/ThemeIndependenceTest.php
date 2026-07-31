<?php

namespace Tests\Feature;

use App\Helpers\Theme;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A theme is a folder someone can drop in or delete. Nothing outside it may
 * assume it exists.
 *
 * Before App\Helpers\Theme, every controller built its view name by
 * interpolating the active_theme setting, so removing the folder that setting
 * pointed at answered 500 on every public page until the database was edited by
 * hand. These tests hold the line on that.
 */
class ThemeIndependenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Theme::forget();
    }

    protected function tearDown(): void
    {
        Theme::forget();

        parent::tearDown();
    }

    /** Point the site at a theme name without touching the stored row for real. */
    protected function useTheme(string $slug): void
    {
        Cache::put('setting.active_theme', new Setting([
            'key' => 'active_theme',
            'value' => $slug,
            'type' => 'string',
        ]), 600);

        Theme::forget();
    }

    public function test_every_installed_theme_ships_every_view_the_site_needs(): void
    {
        $this->assertNotEmpty(Theme::installed(), 'no themes installed at all');

        foreach (Theme::slugs() as $slug) {
            $this->assertSame(
                [],
                Theme::missingViews($slug),
                "{$slug} is listed as installed but is missing views",
            );
        }
    }

    public function test_a_folder_that_is_not_a_theme_is_not_offered(): void
    {
        $this->assertNotEmpty(Theme::missingViews('definitely-not-a-theme'));
        $this->assertFalse(Theme::isInstalled('definitely-not-a-theme'));
        $this->assertArrayNotHasKey('definitely-not-a-theme', Theme::options());
    }

    public function test_a_deleted_active_theme_falls_back_instead_of_failing(): void
    {
        $this->useTheme('theme_that_was_deleted');

        $this->assertNotSame('theme_that_was_deleted', Theme::active());
        $this->assertTrue(Theme::isInstalled(Theme::active()));
    }

    /**
     * A published faculty, department and profile URL from the data actually
     * present, so the check covers the routes as visitors reach them.
     *
     * @return array<int, string>
     */
    protected function publicUrls(): array
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('department.faculty')
            ->with('department.faculty')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher to render');
        }

        $faculty = strtolower($teacher->department->faculty->short_name);
        $department = strtolower($teacher->department->code);

        return [
            '/',
            "/{$faculty}",
            "/{$faculty}/{$department}",
            "/{$faculty}/{$department}/{$teacher->webpage}",
        ];
    }

    public function test_the_public_pages_still_render_when_the_active_theme_is_gone(): void
    {
        $urls = $this->publicUrls();

        $this->useTheme('theme_that_was_deleted');

        foreach ($urls as $url) {
            $this->get($url)->assertStatus(200);
        }
    }

    public function test_every_installed_theme_can_serve_the_public_pages(): void
    {
        $urls = $this->publicUrls();

        foreach (Theme::slugs() as $slug) {
            $this->useTheme($slug);

            foreach ($urls as $url) {
                $this->get($url)->assertStatus(200);
            }
        }
    }

    public function test_view_names_resolve_through_the_active_theme(): void
    {
        foreach (Theme::slugs() as $slug) {
            $this->useTheme($slug);

            $this->assertSame(Theme::PREFIX . $slug . '.home', Theme::view('home'));
            $this->assertSame(Theme::PREFIX . $slug . '.partials.header', Theme::view('partials.header'));
        }
    }

    /**
     * The preview lets an administrator see a theme on the real site before
     * switching everyone to it. Ungated it would let any visitor pin themselves
     * to a theme the university did not choose, and serve every page at two
     * addresses.
     */
    public function test_a_guest_cannot_preview_a_different_theme(): void
    {
        $this->useTheme('theme_default');

        $this->get('/?' . Theme::PREVIEW_PARAM . '=theme_portrait');

        $this->assertNull(Theme::previewOverride());
    }

    public function test_no_theme_ships_a_reference_to_another_theme(): void
    {
        $root = resource_path(Theme::PATH);

        foreach (Theme::slugs() as $slug) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("{$root}/{$slug}"),
            );

            foreach ($files as $file) {
                if (! str_ends_with($file->getPathname(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach (Theme::slugs() as $other) {
                    if ($other === $slug) {
                        continue;
                    }

                    $this->assertStringNotContainsString(
                        "frontend.themes.{$other}.",
                        $contents,
                        "{$slug} reaches into {$other} ({$file->getFilename()}); deleting {$other} would break it",
                    );
                }
            }
        }
    }
}
