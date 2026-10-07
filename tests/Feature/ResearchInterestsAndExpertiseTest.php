<?php

namespace Tests\Feature;

use App\Models\AreaOfExpertise;
use App\Models\ResearchInterest;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
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
