<?php

namespace Tests\Feature;

use App\Helpers\MaintenanceMode;
use App\Helpers\SearchEngines;
use App\Models\Setting;
use Tests\TestCase;

/**
 * System Settings → Frontend Settings → Search Engines: while on, every answer
 * says noindex, so a demo address stays out of search results.
 */
class SearchEnginesBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(MaintenanceMode::ENABLED_KEY, false);
    }

    public function test_public_pages_and_the_panel_say_noindex_while_on(): void
    {
        Setting::set(SearchEngines::BLOCKED_KEY, true);

        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', SearchEngines::HEADER);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', SearchEngines::HEADER);
    }

    public function test_nothing_is_said_while_off(): void
    {
        Setting::set(SearchEngines::BLOCKED_KEY, false);

        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    /** A missing page or the maintenance page is an answer too. */
    public function test_error_and_maintenance_answers_say_noindex_too(): void
    {
        Setting::set(SearchEngines::BLOCKED_KEY, true);

        $this->get('/this-page-does-not-exist-' . uniqid())->assertNotFound()->assertHeader('X-Robots-Tag', SearchEngines::HEADER);

        Setting::set(MaintenanceMode::ENABLED_KEY, true);
        $this->get('/')->assertStatus(503)->assertHeader('X-Robots-Tag', SearchEngines::HEADER);
    }
}
