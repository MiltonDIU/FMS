<?php

namespace Tests\Feature;

use App\Helpers\AdminNotice;
use App\Helpers\MaintenanceMode;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/**
 * System Settings → Maintenance Mode → Internal Notice: a message across the
 * top of the admin panel for everyone signed in, with the public site left up.
 */
class AdminNoticeTest extends TestCase
{
    protected function admin(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $user) {
            $this->markTestSkipped('no super_admin');
        }

        return $user;
    }

    protected function notice(bool $on, string $message = 'Profile editing pauses at 6 PM <b>today</b>.'): void
    {
        Setting::set(AdminNotice::ENABLED_KEY, $on);
        Setting::set(AdminNotice::MESSAGE_KEY, $message);
        Setting::set(MaintenanceMode::ENABLED_KEY, false);
    }

    public function test_the_notice_is_shown_on_the_dashboard_while_on(): void
    {
        $this->notice(true);

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Profile editing pauses at 6 PM')
            // Text, not markup.
            ->assertSee('&lt;b&gt;today&lt;/b&gt;', false);
    }

    /** Closing is remembered against the notice, so an edited one shows again. */
    public function test_the_notice_can_be_closed_and_a_new_one_is_a_new_notice(): void
    {
        $this->actingAs($this->admin());

        $this->notice(true, 'First notice');
        $first = AdminNotice::render();

        $this->notice(true, 'Second notice');
        $second = AdminNotice::render();

        $this->assertStringContainsString('x-on:click="close()"', $first);
        $this->assertStringContainsString('fms-admin-notice-closed', $first);

        preg_match("/id: '([0-9a-f]+)'/", $first, $a);
        preg_match("/id: '([0-9a-f]+)'/", $second, $b);
        $this->assertNotEmpty($a[1] ?? null);
        $this->assertNotSame($a[1], $b[1] ?? null);
    }

    /** Under the top bar, not above it. */
    public function test_the_notice_sits_under_the_top_bar(): void
    {
        $this->notice(true);

        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertGreaterThan(strpos($html, 'fi-topbar'), strpos($html, 'Profile editing pauses at 6 PM'));
    }

    public function test_the_notice_is_not_shown_while_off(): void
    {
        $this->notice(false);

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Profile editing pauses at 6 PM');
    }

    public function test_an_empty_notice_is_not_shown(): void
    {
        $this->notice(true, '   ');

        $this->assertFalse(AdminNotice::active());
    }

    public function test_the_public_site_stays_up(): void
    {
        $this->notice(true);

        $this->get('/')->assertOk()->assertDontSee('Profile editing pauses at 6 PM');
    }
}
