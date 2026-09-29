<?php

namespace Tests\Feature;

use App\Jobs\BuildScopusReviewFile;
use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\ScopusImport;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Scopus\ReviewWorkbook;
use App\Services\Scopus\ScopusAnalysis;
use App\Services\Scopus\ScopusAnalysisPayloadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScopusOnlineReviewTest extends TestCase
{
    protected function anAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@scopus-review.test'],
            ['name' => 'Admin User', 'password' => bcrypt('password')],
        );
    }

    protected function aCsvWith(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scopus') . '.csv';

        $handle = fopen($path, 'w');
        fputcsv($handle, $header, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);

        return $path;
    }

    public function test_build_scopus_review_job_saves_json_analysis_payload(): void
    {
        $path = $this->aCsvWith(
            ['Title', 'Year', 'DOI', 'EID', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                'Online Review Paper',
                '2026',
                '10.1000/online.review',
                '2-s2.0-888',
                'Mahmud, Sakil (222)',
                '222',
                'Mahmud S., Department of Textile Engineering, Daffodil International University, Dhaka',
            ]],
        );

        Storage::disk('local')->put('scopus/online_test.csv', file_get_contents($path));

        $import = ScopusImport::create([
            'original_filename' => 'online_test.csv',
            'source_path' => 'scopus/online_test.csv',
            'status' => ScopusImport::STATUS_UPLOADED,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        app(BuildScopusReviewFile::class, ['import' => $import])
            ->handle(app(ScopusAnalysis::class), app(ReviewWorkbook::class));

        $payloadService = app(ScopusAnalysisPayloadService::class);
        $payload = $payloadService->getPayload($import->id);

        $this->assertNotNull($payload);
        $this->assertArrayHasKey('papers', $payload);
        $this->assertArrayHasKey('people', $payload);

        Storage::disk('public')->delete($import->fresh()->result_path);
        Storage::disk('local')->delete('scopus/online_test.csv');
        Storage::disk('local')->delete($payloadService->diskPath($import->id));
        @unlink($path);
    }

    public function test_it_imports_online_decisions_from_payload(): void
    {
        $payloadService = app(ScopusAnalysisPayloadService::class);

        $result = [
            'papers' => [
                'eid:2-s2.0-999' => [
                    'title' => 'Payload Paper',
                    'year' => '2026',
                    'doi' => '10.1000/payload.doi',
                    'eid' => '2-s2.0-999',
                    'source_title' => 'Journal of Payload',
                    'link' => '',
                    'document_type' => 'Article',
                    'cited_by' => '10',
                    'all_authors' => 'Sakil, M. (111)',
                    'diu_authors' => [],
                    'publication' => null,
                    'confidence' => 'none',
                    'match_basis' => 'none',
                    'authorship' => ['status' => 'nobody_credited', 'note' => ''],
                    'our_authors' => '',
                ],
            ],
            'people' => [],
            'summary' => [
                'papers' => ['total' => 1, 'already_here' => 0, 'new' => 1],
            ],
        ];

        $payloadService->savePayload(999999, $result);
        $payloadService->setPaperDecision(999999, 'eid:2-s2.0-999', 'approve');

        $importStats = $payloadService->importOnlineDecisions(999999);

        $this->assertSame(1, $importStats['created']);

        $publication = Publication::where('doi', '10.1000/payload.doi')->first();
        $this->assertNotNull($publication);
        $this->assertSame('Payload Paper', $publication->title);

        Storage::disk('local')->delete($payloadService->diskPath(999999));
    }

    /**
     * The whole point of the Imported tab: an approved paper leaves the queue.
     *
     * It did not. `authors.email` is NOT NULL, creating the external author
     * threw, and the throw landed in the catch that wraps the paper — so the
     * publication was created but the decision stayed on Approve, the row never
     * reached Imported, and clicking again reconsidered the same paper.
     */
    public function test_an_approved_paper_is_marked_imported_and_its_authors_attached(): void
    {
        $payloadService = app(ScopusAnalysisPayloadService::class);

        $payloadService->savePayload(999998, [
            'papers' => [
                'eid:2-s2.0-777' => [
                    'title' => 'A Paper With An Author Nobody Here Is',
                    'year' => '2026',
                    'doi' => '10.1000/attach.authors',
                    'eid' => '2-s2.0-777',
                    'source_title' => 'Journal of Attachment',
                    'link' => '',
                    'document_type' => 'Article',
                    'cited_by' => '3',
                    'all_authors' => 'Nobodyhere, Quirin (555); Elsewhere, Tamsin (556)',
                    'all_author_affiliations' => 'Department of Computer Science, Daffodil International University; Department of Physics, BRAC University',
                    'diu_authors' => [],
                    'publication' => null,
                    'confidence' => 'none',
                    'match_basis' => 'none',
                    'authorship' => ['status' => 'nobody_credited', 'note' => ''],
                    'our_authors' => '',
                ],
            ],
            'people' => [],
            'summary' => ['papers' => ['total' => 1, 'already_here' => 0, 'new' => 1]],
        ]);

        $payloadService->setPaperDecision(999998, 'eid:2-s2.0-777', 'approve');

        $result = $payloadService->importOnlineDecisions(999998);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['created']);

        $publication = Publication::where('scopus_eid', '2-s2.0-777')->first();
        $this->assertNotNull($publication);
        $this->assertSame(2, $publication->externalAuthors()->count());

        $affiliationsInDb = \Illuminate\Support\Facades\DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->pluck('affiliation')
            ->toArray();
        $this->assertContains('Department of Computer Science, Daffodil International University', $affiliationsInDb);

        $payload = $payloadService->getPayload(999998);
        $this->assertSame('imported', $payload['papers']['eid:2-s2.0-777']['decision']);
        $this->assertSame($publication->id, $payload['papers']['eid:2-s2.0-777']['existing_publication_id']);

        // And a second click has nothing left to do with it.
        $again = $payloadService->importOnlineDecisions(999998);
        $this->assertSame(0, $again['created']);

        Storage::disk('local')->delete($payloadService->diskPath(999998));
    }

    /** Skip means skip — every suggested match used to be bound regardless. */
    public function test_a_person_the_reviewer_skipped_is_not_bound(): void
    {
        $payloadService = app(ScopusAnalysisPayloadService::class);

        $teacher = Teacher::query()->firstOrFail();

        $payloadService->savePayload(999997, [
            'papers' => [],
            'people' => [
                'sid:60606060' => [
                    'name' => 'Skipped, Someone',
                    'scopus_id' => '60606060',
                    'email' => null,
                    'units' => [],
                    'papers' => 1,
                    'faculty' => null,
                    'department' => null,
                    'match' => ['kind' => 'teacher', 'confidence' => 'likely', 'basis' => 'name', 'teacher' => $teacher],
                    'candidates' => [],
                ],
            ],
            'summary' => [],
        ]);

        $payloadService->setPersonDecision(999997, 'sid:60606060', 'skip');

        $result = $payloadService->importOnlineDecisions(999997);

        $this->assertSame(0, $result['people_linked']);
        $this->assertFalse(
            ScopusAuthorId::where('scopus_author_id', '60606060')->exists(),
            'A person marked Skip was bound to a teacher anyway.',
        );

        Storage::disk('local')->delete($payloadService->diskPath(999997));
    }

    public function test_scopus_online_review_view_renders_people_filters_and_counters(): void
    {
        $import = ScopusImport::create([
            'original_filename' => 'view_test.csv',
            'source_path' => 'scopus/view_test.csv',
            'status' => ScopusImport::STATUS_READY,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        $payload = [
            'import_id' => $import->id,
            'summary' => [],
            'papers' => [],
            'people' => [
                'p1' => [
                    'name' => 'Teacher Person',
                    'scopus_id' => '111',
                    'papers' => 2,
                    'match_kind' => 'teacher',
                    'teacher_id' => 1,
                    'teacher_name' => 'Dr. Teacher',
                    'author_id' => null,
                    'author_name' => null,
                    'confidence' => 'certain',
                ],
                'p2' => [
                    'name' => 'Author Person',
                    'scopus_id' => '222',
                    'papers' => 1,
                    'match_kind' => 'author',
                    'teacher_id' => null,
                    'teacher_name' => null,
                    'author_id' => 5,
                    'author_name' => 'Ext Author',
                    'confidence' => 'likely',
                ],
                'p3' => [
                    'name' => 'Unmatched Person',
                    'scopus_id' => '333',
                    'papers' => 1,
                    'match_kind' => 'unknown',
                    'teacher_id' => null,
                    'teacher_name' => null,
                    'author_id' => null,
                    'author_name' => null,
                    'confidence' => 'none',
                ],
            ],
        ];

        $view = $this->view('filament.pages.partials.scopus-online-review', [
            'import' => $import,
            'payload' => $payload,
        ]);

        $view->assertSee('Filter:');
        $view->assertSee('Match with Teacher Table');
        $view->assertSee('Match with Author Table');
        $view->assertSee('Not Matched');
        $view->assertSee('Dr. Teacher');
        $view->assertSee('Ext Author');
        $view->assertSee('Author Table');
        $view->assertSee('Not matched');

        $import->delete();
    }
}

