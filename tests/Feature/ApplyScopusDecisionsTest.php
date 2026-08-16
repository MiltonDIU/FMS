<?php

namespace Tests\Feature;

use App\Filament\Pages\ScopusReview;
use App\Jobs\ApplyScopusDecisions;
use App\Models\ScopusImport;
use App\Models\User;
use App\Services\Scopus\ScopusAnalysisPayloadService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Applying decisions moved off the request.
 *
 * It ran inline until a reviewer approved 793 papers in one go with the bulk
 * action and the request was cut off at 30 seconds, mid-select, having changed
 * nothing. The work itself is fine — it is just minutes long, so it belongs on
 * the queue beside the job that builds the workbook.
 */
class ApplyScopusDecisionsTest extends TestCase
{
    protected ?ScopusImport $import = null;

    protected function tearDown(): void
    {
        if ($this->import) {
            Storage::disk('local')->delete(app(ScopusAnalysisPayloadService::class)->diskPath($this->import->id));
            $this->import->delete();
        }

        parent::tearDown();
    }

    /** @param  array<int, string>  $decisions */
    protected function anImportWith(array $decisions): ScopusImport
    {
        $user = User::role('super_admin')->first();

        $this->import = ScopusImport::create([
            'original_filename' => 'decisions-test.csv',
            'source_path' => 'scopus/decisions-test.csv',
            'status' => ScopusImport::STATUS_READY,
            'uploaded_by' => $user?->id,
        ]);

        $papers = [];

        foreach ($decisions as $i => $decision) {
            $papers['eid:test-' . $i] = [
                'key' => 'eid:test-' . $i,
                'title' => 'A paper ' . $i,
                'year' => '2025',
                'doi' => '',
                'eid' => 'test-' . $i,
                'source_title' => 'A journal',
                'document_type' => 'Article',
                'cited_by' => '',
                'all_authors' => 'Somebody, A.',
                'existing_publication_id' => null,
                'decision' => $decision,
            ];
        }

        Storage::disk('local')->put(
            app(ScopusAnalysisPayloadService::class)->diskPath($this->import->id),
            json_encode(['papers' => $papers, 'people' => [], 'summary' => []]),
        );

        return $this->import;
    }

    public function test_the_import_action_queues_the_work_instead_of_doing_it(): void
    {
        $user = User::role('super_admin')->first();

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $import = $this->anImportWith(['approve', 'approve', 'pending']);

        $this->actingAs($user);
        Queue::fake();

        // Both entry points — the modal's submit and the tab's own button —
        // go through the same applyDecisions helper; this is the one a test
        // can drive directly.
        Livewire::test(ScopusReview::class)
            ->call('importTabDecisions', $import->id)
            ->assertHasNoErrors();

        Queue::assertPushed(ApplyScopusDecisions::class, 1);

        // And nothing was written on the way past: the whole point is that the
        // click returns at once and the work happens later.
        $payload = app(ScopusAnalysisPayloadService::class)->getPayload($import->id);
        $this->assertSame('approve', $payload['papers']['eid:test-0']['decision']);
    }

    public function test_it_says_so_rather_than_queueing_nothing(): void
    {
        $user = User::role('super_admin')->first();

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $import = $this->anImportWith(['pending', 'ignore', 'imported']);

        $this->actingAs($user);
        Queue::fake();

        Livewire::test(ScopusReview::class)
            ->call('importTabDecisions', $import->id)
            ->assertHasNoErrors();

        // 'imported' is done, 'ignore' and 'pending' are not approvals, so there
        // is nothing to apply and no job worth starting.
        Queue::assertNothingPushed();
    }

    public function test_the_job_applies_the_decisions_it_is_given(): void
    {
        $user = User::role('super_admin')->first();

        if (! $user) {
            $this->markTestSkipped('No super_admin in this database.');
        }

        $import = $this->anImportWith(['approve']);

        $this->actingAs($user);

        (new ApplyScopusDecisions($import, $user->id))
            ->handle(app(ScopusAnalysisPayloadService::class));

        $payload = app(ScopusAnalysisPayloadService::class)->getPayload($import->id);

        $this->assertSame('imported', $payload['papers']['eid:test-0']['decision']);
        $this->assertNotNull($payload['papers']['eid:test-0']['existing_publication_id']);

        // Clean up the publication the run created.
        \App\Models\Publication::where('scopus_eid', 'test-0')->forceDelete();
    }
}
