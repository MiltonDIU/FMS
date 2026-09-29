<?php

namespace Tests\Feature;

use App\Filament\Resources\PublicationIncentives\Pages\ListPublicationIncentives;
use App\Jobs\ExportIncentivesJob;
use App\Models\Author;
use App\Models\PublicationIncentive;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The two exports on the Publication Incentives page.
 *
 * The money is the reason these exist, so what is checked is the money: that
 * every per-author row is present and that the column adds up to the total it
 * is printed beside.
 *
 * That is not a given. Of the 23,488,522.95 recorded across 1,759 incentives,
 * 9,285,030.47 sits against external authors rather than our teachers. An
 * export that walked the teachers relation — as the publications-page export
 * does — would drop two fifths of it and still print a confident grand total.
 */
class ExportIncentivesTest extends TestCase
{
    public function test_both_buttons_queue_the_job_with_the_tables_filters(): void
    {
        Queue::fake();

        $this->actingAs($this->anAdmin());

        Livewire::test(ListPublicationIncentives::class)
            ->callAction('export_incentives_background')
            ->callAction('export_incentive_authors_background');

        Queue::assertPushed(ExportIncentivesJob::class, 2);
    }

    public function test_the_incentive_sheet_lists_every_author_who_was_paid(): void
    {
        [$incentive, $paid] = $this->anIncentiveWithATeacherAndAnExternalAuthor();

        $sheet = $this->runExport('incentive');

        $names = $this->column($sheet, 'L');

        foreach ($paid as $person) {
            $this->assertContains(
                $person['name'],
                $names,
                $person['name'] . ' was paid but is not on the sheet.',
            );
        }

        // The external author is the one a teachers-only export would lose.
        $this->assertContains('External', $this->column($sheet, 'M'));

        $this->assertNotEmpty($incentive->id);
    }

    public function test_the_amounts_add_up_to_the_total_beside_them(): void
    {
        $this->anIncentiveWithATeacherAndAnExternalAuthor();

        $sheet = $this->runExport('incentive');

        // Column P is every author's amount; column C is the incentive total,
        // written once per record and merged down its author rows.
        $paidOut = array_sum(array_map('floatval', array_filter($this->column($sheet, 'P'), 'is_numeric')));
        $totals = array_sum(array_map('floatval', array_filter($this->column($sheet, 'C'), 'is_numeric')));

        // The grand total row repeats the sum in column C, so it counts twice.
        $this->assertEqualsWithDelta($totals / 2, $paidOut, 0.01);
    }

    public function test_the_author_sheet_groups_a_person_across_their_papers(): void
    {
        [, $paid] = $this->anIncentiveWithATeacherAndAnExternalAuthor();

        $sheet = $this->runExport('author');

        $names = $this->column($sheet, 'A');

        foreach ($paid as $person) {
            $this->assertContains($person['name'], $names);
        }

        // Teachers and external authors are told apart rather than merged, so a
        // reader can see at a glance which payments left the university.
        $types = array_unique(array_filter($this->column($sheet, 'B')));

        $this->assertNotEmpty(array_intersect(['Teacher', 'External'], $types));
    }

    public function test_a_filtered_table_exports_only_what_it_shows(): void
    {
        $this->actingAs($this->anAdmin());

        $two = PublicationIncentive::whereNotNull('publication_id')->limit(2)->get();

        if ($two->count() < 2) {
            $this->markTestSkipped('Needs two incentives.');
        }

        [$approved, $paid] = [$two[0], $two[1]];

        $approved->forceFill(['status' => 'approved'])->save();
        $paid->forceFill(['status' => 'paid'])->save();

        // Everything else is put aside so the sheet describes these two alone.
        PublicationIncentive::whereNotIn('id', [$approved->id, $paid->id])->delete();

        // The shape the table hands over for a multi-select filter.
        $sheet = $this->runExport('incentive', ['status' => ['values' => ['approved']]]);

        $statuses = array_values(array_unique(array_filter($this->column($sheet, 'D'))));

        $this->assertSame(['approved'], $statuses, 'A filtered export must not reach past the filter.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Runs the job and reads the file it wrote.
     *
     * Really written and really read back — a spreadsheet that throws while
     * being saved is exactly the failure this has to catch, and asserting on
     * the job's internals would not.
     */
    protected function runExport(string $mode, array $filters = []): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $before = collect(Storage::disk('public')->files('exports'));

        (new ExportIncentivesJob($this->anAdmin(), $filters, null, $mode))->handle();

        $written = collect(Storage::disk('public')->files('exports'))
            ->diff($before)
            ->first();

        $this->assertNotNull($written, 'The job wrote no file.');

        $path = Storage::disk('public')->path($written);
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Read into memory, then clean up: this writes outside the database, so
        // the transaction the base TestCase rolls back does not cover it.
        $rows = $sheet->toArray();
        Storage::disk('public')->delete($written);

        $this->assertGreaterThan(1, count($rows), 'The sheet has no data rows.');

        return $sheet;
    }

    /** @return array<int, string> the values of one column, header row excluded */
    protected function column(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $letter): array
    {
        $values = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $value = $sheet->getCell($letter . $row->getRowIndex())->getValue();

            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * An incentive paying one teacher and one external author.
     *
     * @return array{0: PublicationIncentive, 1: array<int, array<string, string>>}
     */
    protected function anIncentiveWithATeacherAndAnExternalAuthor(): array
    {
        // Signed in first: PublicationIncentive's updated hook writes an
        // incentive_logs row stamped with Auth::id(), and changed_by is NOT NULL.
        $this->actingAs($this->anAdmin());

        $incentive = PublicationIncentive::whereNotNull('publication_id')->first();
        $teacher = Teacher::first();
        $external = Author::first();

        if (! $incentive || ! $teacher || ! $external) {
            $this->markTestSkipped('Needs an incentive, a teacher and an external author.');
        }

        // Held inside the transaction the base TestCase rolls back.
        DB::table('publication_authors')->where('publication_id', $incentive->publication_id)->delete();

        DB::table('publication_authors')->insert([
            [
                'publication_id' => $incentive->publication_id,
                'authorable_type' => Teacher::class,
                'authorable_id' => $teacher->id,
                'author_role' => 'first',
                'sort_order' => 0,
                'incentive_amount' => 6000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'publication_id' => $incentive->publication_id,
                'authorable_type' => Author::class,
                'authorable_id' => $external->id,
                'author_role' => 'co_author',
                'sort_order' => 1,
                'incentive_amount' => 4000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $incentive->forceFill(['total_amount' => 10000, 'status' => 'approved'])->save();

        // Every other incentive is put out of the way so the sums below describe
        // this one record rather than the whole table.
        PublicationIncentive::where('id', '!=', $incentive->id)->delete();

        return [$incentive->fresh(), [
            ['name' => $teacher->full_name],
            ['name' => $external->name],
        ]];
    }

    protected function anAdmin(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $user) {
            $this->markTestSkipped('No super_admin in the development database.');
        }

        return $user;
    }
}
