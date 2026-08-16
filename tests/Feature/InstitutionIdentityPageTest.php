<?php

namespace Tests\Feature;

use App\Filament\Pages\InstitutionIdentity;
use App\Filament\Pages\ScopusReview;
use App\Filament\Pages\SystemSettings;
use App\Helpers\Institution;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The institution profile moved out of System Settings onto its own page.
 *
 * Two things had to stay true through that move: whoever runs a Scopus review
 * can now reach it without being handed the whole settings page, and System
 * Settings — which still saves by looping over every field it holds — can no
 * longer touch these keys.
 */
class InstitutionIdentityPageTest extends TestCase
{
    /**
     * These tests run against the real database without a transaction, so a row
     * left behind by an earlier test — or by a run that failed halfway — would
     * decide the result of the next one. Each test starts from nothing written.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetInstitutionSettings();
    }

    protected function tearDown(): void
    {
        $this->forgetInstitutionSettings();

        parent::tearDown();
    }

    protected function forgetInstitutionSettings(): void
    {
        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Setting::where('key', $key)->delete();
            Cache::forget("setting.{$key}");
        }
    }

    protected function userWithRole(string $role): ?User
    {
        return User::role($role)->first();
    }

    public function test_it_opens_showing_what_is_actually_in_force(): void
    {
        $user = $this->userWithRole('super_admin');

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $this->actingAs($user);

        Livewire::test(InstitutionIdentity::class)
            ->assertOk()
            // Nothing has been saved, so the built-in defaults have to be shown
            // rather than empty boxes — an admin cannot otherwise tell a working
            // configuration from a blank one.
            ->assertSet('data.institution_name', Institution::DEFAULTS['institution_name'])
            ->assertSet('data.institution_match_patterns', Institution::DEFAULTS['institution_match_patterns'])
            ->assertSet('data.institution_not_us', Institution::DEFAULTS['institution_not_us']);
    }

    public function test_the_research_team_can_reach_it_without_settings_access(): void
    {
        $user = $this->userWithRole('research_team');

        if (! $user) {
            $this->markTestSkipped('No research_team user in this database.');
        }

        $this->actingAs($user);

        // The point of the move: the same people who run the review, and not
        // by way of the settings permission, which they do not have.
        $this->assertFalse(
            $user->can('View:SystemSettings'),
            'This test is meaningless if the research team already has settings access.',
        );

        $this->assertTrue(ScopusReview::canAccess());
        $this->assertTrue(InstitutionIdentity::canAccess());
        $this->assertFalse(SystemSettings::canAccess());
    }

    public function test_a_teacher_cannot_reach_it(): void
    {
        $user = $this->userWithRole('teacher');

        if (! $user) {
            $this->markTestSkipped('No teacher user in this database.');
        }

        $this->actingAs($user);

        $this->assertFalse(InstitutionIdentity::canAccess());

        Livewire::test(InstitutionIdentity::class)->assertForbidden();
    }

    public function test_saving_writes_the_settings_and_the_matcher_reads_them(): void
    {
        $user = $this->userWithRole('super_admin');

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $this->actingAs($user);

        Livewire::test(InstitutionIdentity::class)
            ->set('data.institution_name', 'University of Dhaka')
            ->set('data.institution_short_name', 'DU')
            ->set('data.institution_match_patterns', ['dhaka\s+univ[a-z]*'])
            ->set('data.institution_not_us', [])
            ->call('save')
            ->assertHasNoErrors();

        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Cache::forget("setting.{$key}");
        }

        $this->assertSame('University of Dhaka', Institution::name());
        $this->assertSame('DU', Institution::shortName());
        $this->assertSame(['/dhaka\s+univ[a-z]*/i'], Institution::matchPatterns());

        // A list has to survive the round trip as a list: Setting::set encodes
        // it without recording that it did, so a naive read gives back a string.
        $this->assertSame([], Institution::notUs());
    }

    public function test_system_settings_can_no_longer_write_institution_keys(): void
    {
        $user = $this->userWithRole('super_admin');

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $this->actingAs($user);

        Setting::set('institution_name', 'Set by the institution page');
        Cache::forget('setting.institution_name');

        /*
         * System Settings saves by looping over its whole form state and
         * calling Setting::set on every key in it. While the tab lived there
         * that loop covered these keys, so what protects them now is simply
         * that the page no longer has the fields — asserted directly, rather
         * than by calling save(), which in this database trips on unrelated
         * required mail fields.
         */
        $page = Livewire::test(SystemSettings::class)->assertOk()->instance();

        $fields = array_keys($page->getSchema('form')->getFlatFields(withHidden: true));

        $leaked = array_filter($fields, fn (string $field) => str_starts_with($field, Institution::PREFIX));

        $this->assertSame([], array_values($leaked),
            'System Settings still holds institution fields, so saving it would rewrite the matcher\'s rules.');

        // And the value set elsewhere is still the one in force.
        Cache::forget('setting.institution_name');
        $this->assertSame('Set by the institution page', Setting::get('institution_name'));
    }

    public function test_seeding_produces_exactly_what_the_code_falls_back_to(): void
    {
        // Read with nothing in the table: this is what an unseeded install uses.
        $beforeSeeding = [
            'name' => Institution::name(),
            'short' => Institution::shortName(),
            'patterns' => Institution::matchPatterns(),
            'not_us' => Institution::notUs(),
            'domains' => Institution::emailDomains(),
            'student' => Institution::studentEmailPattern(),
            'aliases' => Institution::unitAliases(),
        ];

        $this->seed(\Database\Seeders\InstitutionIdentitySeeder::class);

        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Cache::forget("setting.{$key}");
            $this->assertDatabaseHas('settings', ['key' => $key, 'group' => 'institution']);
        }

        // And with the rows present, every answer has to be the same one. If
        // these ever diverge, a migrate:refresh silently changes what a run
        // concludes, which is the whole thing the seeder exists to prevent.
        $this->assertSame($beforeSeeding['name'], Institution::name());
        $this->assertSame($beforeSeeding['short'], Institution::shortName());
        $this->assertSame($beforeSeeding['patterns'], Institution::matchPatterns());
        $this->assertSame($beforeSeeding['not_us'], Institution::notUs());
        $this->assertSame($beforeSeeding['domains'], Institution::emailDomains());
        $this->assertSame($beforeSeeding['student'], Institution::studentEmailPattern());
        $this->assertSame($beforeSeeding['aliases'], Institution::unitAliases());
    }

    public function test_seeding_again_does_not_undo_what_an_admin_changed(): void
    {
        Setting::set('institution_name', 'University of Dhaka');
        Setting::set('institution_match_patterns', ['dhaka\s+univ[a-z]*']);
        Cache::forget('setting.institution_name');
        Cache::forget('setting.institution_match_patterns');

        // A db:seed on a live installation must not reset another university's
        // matching rules back to Daffodil's.
        $this->seed(\Database\Seeders\InstitutionIdentitySeeder::class);

        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Cache::forget("setting.{$key}");
        }

        $this->assertSame('University of Dhaka', Institution::name());
        $this->assertSame(['/dhaka\s+univ[a-z]*/i'], Institution::matchPatterns());

        // Rows never written are still filled in with the defaults.
        $this->assertSame(Institution::DEFAULTS['institution_email_domains'], Institution::emailDomains());

        // The row keeps its value but is filed properly: a row saved from the
        // page goes in under group "system" with no type, and the seeder is
        // where that gets tidied up.
        $this->assertDatabaseHas('settings', [
            'key' => 'institution_match_patterns',
            'group' => 'institution',
            'type' => 'json',
        ]);
        $this->assertSame(['dhaka\s+univ[a-z]*'], Setting::get('institution_match_patterns'));
    }

    public function test_a_seeded_list_reads_back_as_a_list(): void
    {
        $this->seed(\Database\Seeders\InstitutionIdentitySeeder::class);

        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Cache::forget("setting.{$key}");
        }

        // Seeded with type 'json', so Setting::get decodes it rather than
        // handing back the raw string a page save would have stored.
        $this->assertIsArray(Setting::get('institution_match_patterns'));
        $this->assertIsArray(Setting::get('institution_unit_aliases'));
        $this->assertSame('string', Setting::where('key', 'institution_name')->value('type'));
        $this->assertSame('json', Setting::where('key', 'institution_not_us')->value('type'));

        // The page has to be able to render them straight into its TagsInput.
        $user = $this->userWithRole('super_admin');

        if ($user) {
            $this->actingAs($user);

            Livewire::test(InstitutionIdentity::class)
                ->assertOk()
                ->assertSet('data.institution_not_us', Institution::DEFAULTS['institution_not_us']);
        }
    }

    public function test_the_page_refuses_keys_outside_its_own_prefix(): void
    {
        $user = $this->userWithRole('super_admin');

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $this->actingAs($user);

        $before = Setting::get('branding_site_name');

        Livewire::test(InstitutionIdentity::class)
            ->set('data.branding_site_name', 'Hijacked')
            ->call('save')
            ->assertHasNoErrors();

        Cache::forget('setting.branding_site_name');

        $this->assertSame($before, Setting::get('branding_site_name'));
    }
}
