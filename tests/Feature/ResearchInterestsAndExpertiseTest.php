<?php

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Helpers\Theme;
use App\Models\AreaOfExpertise;
use App\Models\ResearchInterest;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherVersionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Research interests and areas of expertise come from two places and are kept
 * in two tables.
 *
 * Interests are parsed from the old site's teacher.currentResearch and arrive
 * through import:old-teachers-research-interests. Expertise is the research
 * directory's list in researchers.json and arrives through
 * import:researcher-profiles. Each import writes only its own table.
 */
class ResearchInterestsAndExpertiseTest extends TestCase
{
    protected Teacher $teacher;

    /** @var array<int, string> files this test wrote, removed afterwards */
    protected array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A teacher whose employee id is theirs alone, so the import cannot
        // land on a duplicate profile instead.
        $this->teacher = Teacher::whereNotNull('webpage')
            ->where('webpage', '!=', '')
            ->whereIn('employee_id', DB::table('teachers')
                ->select('employee_id')
                ->whereNotNull('employee_id')
                ->groupBy('employee_id')
                ->havingRaw('COUNT(*) = 1'))
            ->firstOrFail();

        $this->teacher->researchInterests()->forceDelete();
        $this->teacher->areasOfExpertise()->forceDelete();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_interests_are_added_after_the_teachers_own_without_repeating_them(): void
    {
        ResearchInterest::create(['teacher_id' => $this->teacher->id, 'interest' => 'Machine Learning', 'sort_order' => 0]);

        $file = $this->exportFile([
            ['interest' => 'machine learning', 'description' => null],
            ['interest' => 'Computer Vision', 'description' => 'Plant leaf disease detection'],
            ['interest' => 'Natural Language Processing', 'description' => null],
        ]);

        $this->artisan('import:old-teachers-research-interests', ['--file' => $file])->assertSuccessful();

        $interests = $this->teacher->researchInterests()->get();

        $this->assertSame(['Machine Learning', 'Computer Vision', 'Natural Language Processing'], $interests->pluck('interest')->all());
        $this->assertSame([0, 1, 2], $interests->pluck('sort_order')->all());
        $this->assertSame('Plant leaf disease detection', $interests[1]->description);

        // A second run finds everything already there.
        $this->artisan('import:old-teachers-research-interests', ['--file' => $file])->assertSuccessful();
        $this->assertSame(3, $this->teacher->researchInterests()->count());

        $this->assertSame(0, $this->teacher->areasOfExpertise()->count());
    }

    public function test_dry_run_and_skip_existing_write_nothing(): void
    {
        $file = $this->exportFile([['interest' => 'Data Mining', 'description' => null]]);

        $this->artisan('import:old-teachers-research-interests', ['--file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $this->teacher->researchInterests()->count());

        ResearchInterest::create(['teacher_id' => $this->teacher->id, 'interest' => 'Their own', 'sort_order' => 0]);

        $this->artisan('import:old-teachers-research-interests', ['--file' => $file, '--skip-existing' => true])->assertSuccessful();
        $this->assertSame(['Their own'], $this->teacher->researchInterests()->pluck('interest')->all());
    }

    public function test_the_research_directory_writes_areas_of_expertise_not_interests(): void
    {
        $file = storage_path('app/public/exports/_test_researchers_' . uniqid() . '.json');
        $this->files[] = $file;

        file_put_contents($file, json_encode([[
            'name' => 'Test Researcher',
            'diuPortfolio' => 'https://faculty.daffodilvarsity.edu.bd/profile/x/' . strtolower($this->teacher->webpage) . '.html',
            'expertise' => ['Renewable Energy', 'Power Systems', 'renewable energy'],
        ]]));

        $this->artisan('import:researcher-profiles', ['--file' => $file])->assertSuccessful();

        $this->assertSame(['Renewable Energy', 'Power Systems'], $this->teacher->areasOfExpertise()->pluck('expertise')->all());
        $this->assertSame(0, $this->teacher->researchInterests()->count());
        $this->assertInstanceOf(AreaOfExpertise::class, $this->teacher->areasOfExpertise()->first());
    }

    /**
     * Every theme gives areas of expertise a tab of their own, after Research
     * Interest, and leaves it off a profile that has none — the same rule as
     * every other section.
     */
    public function test_every_theme_shows_an_area_of_expertise_tab_only_when_there_is_one(): void
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

        $url = '/' . strtolower($teacher->department->faculty->short_name)
            . '/' . strtolower($teacher->department->code)
            . '/' . $teacher->webpage;

        $teacher->areasOfExpertise()->forceDelete();

        try {
            foreach (Theme::slugs() as $slug) {
                $this->useTheme($slug);

                $this->get($url)->assertOk()->assertDontSee('id="expertise"', false);
            }

            AreaOfExpertise::create(['teacher_id' => $teacher->id, 'expertise' => 'Quantum Error Correction', 'sort_order' => 0]);
            AreaOfExpertise::create(['teacher_id' => $teacher->id, 'expertise' => 'Lattice Cryptography', 'description' => 'Post-quantum key exchange', 'sort_order' => 1]);

            foreach (Theme::slugs() as $slug) {
                $this->useTheme($slug);

                $this->get($url)
                    ->assertOk()
                    ->assertSee('id="expertise"', false)
                    ->assertSee('Area of Expertise')
                    ->assertSeeInOrder(['Quantum Error Correction', 'Lattice Cryptography'])
                    ->assertSee('Post-quantum key exchange');
            }
        } finally {
            Theme::forget();
        }
    }

    /**
     * An administrator's save from the edit page goes through the version
     * service with approval skipped, and the repeater rows are the whole list:
     * a row edited is updated, a row left out is removed, and the order on
     * screen becomes sort_order.
     */
    public function test_an_admin_can_add_edit_reorder_and_delete_areas_of_expertise(): void
    {
        Notification::fake();
        $service = app(TeacherVersionService::class);

        $service->handleUpdateFromForm($this->teacher, ['areasOfExpertise' => [
            ['expertise' => 'Renewable Energy', 'description' => null],
            ['expertise' => 'Power Systems', 'description' => null],
            ['expertise' => 'Smart Grids', 'description' => null],
        ]], skipApproval: true);

        [$renewable, $power, $smart] = $this->teacher->areasOfExpertise()->get()->all();
        $this->assertSame(['Renewable Energy', 'Power Systems', 'Smart Grids'], [$renewable->expertise, $power->expertise, $smart->expertise]);

        // Edit one, drop one, and put the last first.
        $service->handleUpdateFromForm($this->teacher->fresh(), ['areasOfExpertise' => [
            ['id' => $smart->id, 'expertise' => 'Smart Grids', 'description' => null],
            ['id' => $renewable->id, 'expertise' => 'Renewable Energy Systems', 'description' => 'Solar and wind'],
        ]], skipApproval: true);

        $rows = $this->teacher->areasOfExpertise()->get();

        $this->assertSame(['Smart Grids', 'Renewable Energy Systems'], $rows->pluck('expertise')->all());
        $this->assertSame([$smart->id, $renewable->id], $rows->pluck('id')->all());
        $this->assertSame('Solar and wind', $rows[1]->description);
        $this->assertSoftDeleted('area_of_expertises', ['id' => $power->id]);
    }

    /**
     * A teacher's own change waits for approval when the section asks for it,
     * and lands once approved — the section has its own row in
     * approval_settings, so it can be switched on or off by itself.
     */
    public function test_a_teachers_change_to_their_expertise_waits_for_approval_when_the_section_requires_it(): void
    {
        Notification::fake();
        $service = app(TeacherVersionService::class);

        DB::table('approval_settings')->where('section_key', 'area_of_expertise')->update(['requires_approval' => true, 'is_active' => true]);

        AreaOfExpertise::create(['teacher_id' => $this->teacher->id, 'expertise' => 'Operations Research', 'sort_order' => 0]);

        $service->handleUpdateFromForm($this->teacher->fresh(), ['areasOfExpertise' => [
            ['id' => $this->teacher->areasOfExpertise()->value('id'), 'expertise' => 'Operations Research', 'description' => null],
            ['expertise' => 'Supply Chain Optimisation', 'description' => null],
        ]]);

        $version = $this->teacher->versions()->latest('id')->firstOrFail();

        $this->assertSame('pending', $version->status);
        $this->assertContains('area_of_expertise', $version->pending_sections);
        $this->assertSame(['Operations Research'], $this->teacher->areasOfExpertise()->pluck('expertise')->all());

        $this->actingAs(User::role('super_admin')->firstOrFail());
        $service->approveVersion($version);

        $this->assertSame(['Operations Research', 'Supply Chain Optimisation'], $this->teacher->areasOfExpertise()->pluck('expertise')->all());
    }

    public function test_the_admin_edit_page_offers_the_area_of_expertise_tab(): void
    {
        AreaOfExpertise::create(['teacher_id' => $this->teacher->id, 'expertise' => 'Computational Linguistics', 'sort_order' => 0]);

        $this->actingAs(User::role('super_admin')->firstOrFail())
            ->get(TeacherResource::getUrl('edit', ['record' => $this->teacher]))
            ->assertOk()
            ->assertSee('Area of Expertise')
            ->assertSee('Computational Linguistics');
    }

    /**
     * A test of its own, not a second actingAs in the one above: Filament's
     * AuthenticateSession keeps the first user's password hash in the session
     * and logs the second one straight out.
     */
    public function test_a_teachers_own_profile_page_offers_the_area_of_expertise_tab(): void
    {
        AreaOfExpertise::create(['teacher_id' => $this->teacher->id, 'expertise' => 'Computational Linguistics', 'sort_order' => 0]);

        $user = $this->teacher->user;

        if (! $user || ! $user->can('View:MyProfile')) {
            $this->markTestIncomplete('this teacher has no account that can open My Profile');
        }

        $this->actingAs($user)
            ->get(MyProfile::getUrl())
            ->assertOk()
            ->assertSee('Area of Expertise')
            ->assertSee('Computational Linguistics');
    }

    /** Point the site at a theme without touching the stored setting. */
    protected function useTheme(string $slug): void
    {
        Cache::put('setting.active_theme', new Setting([
            'key' => 'active_theme',
            'value' => $slug,
            'type' => 'string',
        ]), 600);

        Theme::forget();
    }

    /**
     * An export file shaped like export:old-teachers-research-interests writes.
     *
     * @param  array<int, array<string, mixed>>  $interests
     */
    protected function exportFile(array $interests): string
    {
        $name = '_test_research_interests_' . uniqid() . '.json';
        $this->files[] = storage_path('app/public/exports/' . $name);

        file_put_contents(end($this->files), json_encode([[
            '_employee_id' => (string) $this->teacher->employee_id,
            '_old_teacher_id' => null,
            'research_interests' => $interests,
        ]]));

        return $name;
    }
}
