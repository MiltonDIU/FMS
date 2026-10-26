<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettings;
use App\Helpers\GoogleAnalytics;
use App\Helpers\MaintenanceMode;
use App\Helpers\Theme;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * System Settings → Frontend Settings → Google Analytics: the GA4 tag on public
 * pages, whichever theme is active, and nowhere else.
 */
class GoogleAnalyticsTest extends TestCase
{
    protected const TAG = 'googletagmanager.com/gtag/js?id=G-TEST12345';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(MaintenanceMode::ENABLED_KEY, false);
    }

    protected function analytics(bool $on, ?string $id = 'G-TEST12345', bool $skipSignedIn = true): void
    {
        Setting::set(GoogleAnalytics::ENABLED_KEY, $on);
        Setting::set(GoogleAnalytics::ID_KEY, $id);
        Setting::set(GoogleAnalytics::SKIP_SIGNED_IN_KEY, $skipSignedIn);
    }

    protected function admin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first()
            ?? $this->markTestSkipped('no super_admin');
    }

    public function test_the_tag_is_on_public_pages_once_while_on(): void
    {
        $this->analytics(true);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::TAG));
        $this->assertLessThan(stripos($html, '</head>'), strpos($html, self::TAG), 'inside the head');
    }

    public function test_every_theme_gets_the_tag(): void
    {
        $this->analytics(true);
        $active = Setting::get('active_theme');

        try {
            foreach (Theme::slugs() as $slug) {
                Setting::set('active_theme', $slug);
                $this->get('/')->assertOk()->assertSee(self::TAG, false);
            }
        } finally {
            Setting::set('active_theme', $active);
        }
    }

    public function test_nothing_while_off(): void
    {
        $this->analytics(false);

        $this->get('/')->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_a_malformed_id_is_never_put_on_the_page(): void
    {
        $this->analytics(true, "G-1'</script><script>alert(1)");

        $this->get('/')->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_signed_in_users_are_not_counted_unless_asked(): void
    {
        $this->actingAs($this->admin());

        $this->analytics(true);
        $this->get('/')->assertOk()->assertDontSee(self::TAG, false);

        $this->analytics(true, skipSignedIn: false);
        $this->get('/')->assertOk()->assertSee(self::TAG, false);
    }

    public function test_the_admin_panel_is_never_tracked(): void
    {
        $this->analytics(true, skipSignedIn: false);

        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_the_maintenance_page_is_never_tracked(): void
    {
        $this->analytics(true);
        Setting::set(MaintenanceMode::ENABLED_KEY, true);

        $this->get('/')->assertStatus(503)->assertDontSee('googletagmanager', false);
    }

    public function test_the_settings_form_refuses_a_malformed_id(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SystemSettings::class)
            ->set('data.' . GoogleAnalytics::ENABLED_KEY, true)
            ->set('data.' . GoogleAnalytics::ID_KEY, 'UA-12345-1')
            ->call('save')
            ->assertHasErrors(['data.' . GoogleAnalytics::ID_KEY]);
    }
}
