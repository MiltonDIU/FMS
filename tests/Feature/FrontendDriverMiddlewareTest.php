<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\TestCase;

class FrontendDriverMiddlewareTest extends TestCase
{
    protected $initialDriver;
    protected $initialNextjsUrl;
    protected $initialActiveTheme;

    protected function setUp(): void
    {
        parent::setUp();

        // Backup current settings
        $this->initialDriver = Setting::get('frontend_driver', 'blade');
        $this->initialNextjsUrl = Setting::get('nextjs_url', '');
        $this->initialActiveTheme = Setting::get('active_theme', 'theme_default');
    }

    protected function tearDown(): void
    {
        // Restore settings
        Setting::set('frontend_driver', $this->initialDriver);
        Setting::set('nextjs_url', $this->initialNextjsUrl);
        Setting::set('active_theme', $this->initialActiveTheme);

        parent::tearDown();
    }

    public function test_loads_blade_frontend_by_default()
    {
        Setting::set('frontend_driver', 'blade');
        Setting::set('active_theme', 'theme_default');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Explore Our Distinguished');
    }

    public function test_redirects_to_nextjs_url_when_nextjs_driver_active()
    {
        Setting::set('frontend_driver', 'nextjs');
        Setting::set('nextjs_url', 'https://teachers.diu.edu.bd');

        $response = $this->get('/teachers/test-id');

        $response->assertRedirect('https://teachers.diu.edu.bd/teachers/test-id');
    }

    public function test_settings_api_endpoint()
    {
        Setting::set('frontend_driver', 'nextjs');
        Setting::set('nextjs_url', 'https://teachers.diu.edu.bd');
        Setting::set('active_theme', 'theme_modern');

        $response = $this->getJson('/api/v1/settings');

        $response->assertStatus(200);
        $response->assertJson([
            'frontend_driver' => 'nextjs',
            'nextjs_url' => 'https://teachers.diu.edu.bd',
            'active_theme' => 'theme_modern',
        ]);
    }
}
