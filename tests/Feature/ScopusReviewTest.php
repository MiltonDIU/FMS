<?php

namespace Tests\Feature;

use App\Filament\Pages\ScopusReview;
use App\Jobs\BuildScopusReviewFile;
use App\Models\Author;
use App\Models\Publication;
use App\Models\ScopusImport;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Scopus\AffiliationMatcher;
use App\Services\Scopus\MatchingOptions;
use App\Services\Scopus\RecordResolver;
use App\Services\Scopus\ScopusAnalysis;
use App\Services\Scopus\ScopusFileReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

/**
 * Reading a Scopus export and saying what it means, without changing anything.
 *
 * Three things decide whether the workbook is worth a reviewer's time.
 *
 * **Is it ours.** The university's name appears 27 ways in one export —
 * "Daffodill", "Daffodils", "Univeristy", lower-cased — while "Daffodil Smart
 * City" is the campus address and "Daffodil Polytechnic Institute" is a
 * different institution altogether. A plain substring test gets two of those
 * three wrong.
 *
 * **Which author.** Scopus writes "Hossain, Mohammad Reyad" where we hold
 * "Mohammad Reyad Hossain": same person, same words, different order. And
 * "Al-Amin, Md." matches 21 of our teachers, which has to come back as
 * ambiguous rather than as a confident wrong answer.
 *
 * **Nothing is written.** The whole value of this step is that it can be run
 * on a whim.
 */
class ScopusReviewTest extends TestCase
{
    // ── Is this affiliation ours ─────────────────────────────────────────

    public function test_it_recognises_the_university_however_it_is_spelt(): void
    {
        $matcher = app(AffiliationMatcher::class);

        foreach ([
            'Daffodil International University',
            'Daffodil International University (DIU)',
            'Daffodill International University',
            'Daffodils International University',
            'Daffodil International Univeristy',
            'Daffodil International Univ.',
            'daffodil International University',
            'Daffodil International university',
        ] as $spelling) {
            $this->assertTrue(
                $matcher->isOurs('Rahman M., Department of CSE, ' . $spelling . ', Dhaka, Bangladesh'),
                $spelling . ' should be recognised as ours.',
            );
        }
    }

    public function test_the_campus_address_alone_is_not_the_university(): void
    {
        // 589 lines say this. It is where the campus is, not who employed anyone.
        $this->assertFalse(
            app(AffiliationMatcher::class)->isOurs('Rahman M., Daffodil Smart City, Birulia, Dhaka, Bangladesh'),
        );
    }

    public function test_the_sister_institutions_are_not_counted_as_ours(): void
    {
        $matcher = app(AffiliationMatcher::class);

        $this->assertFalse($matcher->isOurs('Karim A., Daffodil Polytechnic Institute, Dhaka, Bangladesh'));
        $this->assertFalse($matcher->isOurs('Karim A., Daffodil Institute of IT, Dhaka, Bangladesh'));
    }

    // ── Faculty and department ───────────────────────────────────────────

    public function test_a_known_department_brings_its_own_faculty(): void
    {
        $department = \App\Models\Department::with('faculty')->whereHas('faculty')->first();

        if (! $department) {
            $this->markTestSkipped('No department with a faculty.');
        }

        $resolved = app(AffiliationMatcher::class)->resolve(
            'Rahman M., Department of ' . $department->name . ', Daffodil International University, Dhaka',
        );

        $this->assertNotNull($resolved['department']);
        $this->assertSame($department->id, $resolved['department']->id);

        // The faculty comes from our own tables, never from what Scopus wrote —
        // otherwise the file could contradict our own structure.
        $this->assertSame($department->faculty->id, $resolved['faculty']->id);
    }

    public function test_a_faculty_without_a_known_department_leaves_the_department_empty(): void
    {
        $faculty = \App\Models\Faculty::first();

        if (! $faculty) {
            $this->markTestSkipped('No faculty.');
        }

        $resolved = app(AffiliationMatcher::class)->resolve(
            'Rahman M., Faculty of ' . $faculty->name . ', Daffodil International University, Dhaka',
        );

        $this->assertNotNull($resolved['faculty'], 'The faculty should have been recognised.');
        $this->assertNull($resolved['department'], 'The department is for the reviewer to fill in.');
    }

    // ── Which person ─────────────────────────────────────────────────────

    public function test_a_reordered_name_still_finds_the_teacher(): void
    {
        $teacher = Teacher::whereNotNull('first_name')->whereNotNull('last_name')->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher.');
        }

        // Scopus writes surname first. Same words, different order.
        $asScopusWritesIt = $teacher->last_name . ', ' . trim($teacher->first_name . ' ' . $teacher->middle_name);

        $resolver = app(RecordResolver::class);

        $this->assertSame(
            $resolver->nameKey($teacher->full_name),
            $resolver->nameKey($asScopusWritesIt),
            'A name written surname-first must reduce to the same key.',
        );
    }

    public function test_an_email_beats_a_name(): void
    {
        $user = User::whereNotNull('email')->whereHas('teacher')->first();

        if (! $user) {
            $this->markTestSkipped('No user with a teacher.');
        }

        $resolved = app(RecordResolver::class)
            ->resolveAuthor('Somebody, Entirely Different', $user->email);

        $this->assertSame('teacher', $resolved['kind']);
        $this->assertSame(RecordResolver::CERTAIN, $resolved['confidence']);
        $this->assertSame('email', $resolved['basis']);
    }

    public function test_a_name_shared_by_several_teachers_comes_back_ambiguous(): void
    {
        // Rather than confidently picking one of them, which is how a paper ends
        // up on the wrong person's profile.
        $resolver = app(RecordResolver::class);

        $shared = Teacher::query()
            ->select('first_name', 'last_name', DB::raw('COUNT(*) as n'))
            ->groupBy('first_name', 'last_name')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if (! $shared) {
            $this->markTestSkipped('No two teachers share a name in this database.');
        }

        $resolved = $resolver->resolveAuthor(trim($shared->first_name . ' ' . $shared->last_name));

        $this->assertSame(RecordResolver::AMBIGUOUS, $resolved['confidence']);
        $this->assertGreaterThan(1, $resolved['candidates']);
        $this->assertNull($resolved['teacher'], 'An ambiguous match must not name anybody.');
    }

    public function test_an_author_already_merged_answers_as_the_teacher(): void
    {
        $author = Author::notMerged()->first();
        $teacher = Teacher::first();

        if (! $author || ! $teacher) {
            $this->markTestSkipped('Needs an author and a teacher.');
        }

        // Given a name nobody else carries, so the answer is about the merge
        // rather than about two authors sharing a spelling. Held inside the
        // transaction the base TestCase rolls back.
        $author->forceFill([
            'name' => 'Zzyzx Quorandel Vothgar',
            'merged_into_teacher_id' => $teacher->id,
            'merged_at' => now(),
        ])->save();

        $resolved = app(RecordResolver::class)->resolveAuthor($author->name);

        $this->assertSame('teacher', $resolved['kind']);
        $this->assertSame($teacher->id, $resolved['teacher']->id);
        $this->assertSame('merged-author', $resolved['basis']);
    }

    public function test_a_student_address_is_told_apart_from_a_staff_one(): void
    {
        $resolver = app(RecordResolver::class);

        // DIU writes staff as name.department and students with their admission
        // number. A digit in the local part is the whole rule.
        $this->assertTrue($resolver->looksLikeStudent('murshid15-6122@diu.edu.bd'));
        $this->assertTrue($resolver->looksLikeStudent('hossain22205131143@diu.edu.bd'));

        $this->assertFalse($resolver->looksLikeStudent('ali.cse@diu.edu.bd'));
        $this->assertFalse($resolver->looksLikeStudent('drhasan.swe@diu.edu.bd'));
    }

    // ── The matching rules ───────────────────────────────────────────────

    public function test_a_recorded_scopus_id_settles_it_outright(): void
    {
        $teacher = Teacher::first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher.');
        }

        // Held inside the transaction the base TestCase rolls back.
        $teacher->scopusAuthorIds()->create([
            'scopus_author_id' => '99999999999',
            'source' => \App\Models\ScopusAuthorId::SOURCE_MANUAL,
        ]);

        $resolved = app(RecordResolver::class)->resolveAuthor(
            'Somebody, Entirely Unrelated',
            null,
            null,
            [],
            '99999999999',
        );

        // No name was compared at all: one Scopus profile is one person.
        $this->assertSame('teacher', $resolved['kind']);
        $this->assertSame($teacher->id, $resolved['teacher']->id);
        $this->assertSame(RecordResolver::CERTAIN, $resolved['confidence']);
        $this->assertSame('scopus id', $resolved['basis']);
    }

    public function test_switching_a_rule_off_changes_what_the_run_may_conclude(): void
    {
        $user = User::whereNotNull('email')->whereHas('teacher')->first();

        if (! $user) {
            $this->markTestSkipped('No user with a teacher.');
        }

        $withEmail = new RecordResolver(new MatchingOptions(matchByEmail: true));
        $withoutEmail = new RecordResolver(new MatchingOptions(matchByEmail: false));

        $name = 'Nobody, By That Name At All';

        $this->assertSame('email', $withEmail->resolveAuthor($name, $user->email)['basis']);
        $this->assertSame('none', $withoutEmail->resolveAuthor($name, $user->email)['basis']);
    }

    public function test_the_department_breaks_a_tie_only_when_asked_to(): void
    {
        /*
         * Namesakes who sit in *different* departments — the only shape where
         * the department can settle anything. Taking the first pair that shares
         * a name is not enough: most of them share a department too.
         */
        $candidates = null;

        $shared = Teacher::query()
            ->select('first_name', 'last_name', DB::raw('COUNT(*) as n'))
            ->whereNotNull('department_id')
            ->groupBy('first_name', 'last_name')
            ->havingRaw('COUNT(*) > 1')
            ->limit(40)
            ->get();

        foreach ($shared as $pair) {
            $group = Teacher::where('first_name', $pair->first_name)
                ->where('last_name', $pair->last_name)
                ->whereNotNull('department_id')
                ->get();

            if ($group->pluck('department_id')->unique()->count() === $group->count()) {
                $candidates = $group;

                break;
            }
        }

        if (! $candidates) {
            $this->markTestSkipped('No namesakes in different departments.');
        }

        $name = trim($candidates->first()->first_name . ' ' . $candidates->first()->last_name);
        $departmentId = $candidates->first()->department_id;

        $on = new RecordResolver(new MatchingOptions(useDepartmentTiebreak: true));
        $off = new RecordResolver(new MatchingOptions(useDepartmentTiebreak: false));

        $settled = $on->resolveAuthor($name, null, $departmentId);
        $unsettled = $off->resolveAuthor($name, null, $departmentId);

        $this->assertSame('name + department', $settled['basis']);
        $this->assertSame($departmentId, $settled['teacher']->department_id);

        // With the rule off it stays honestly ambiguous rather than picking one.
        $this->assertSame(RecordResolver::AMBIGUOUS, $unsettled['confidence']);
        $this->assertNull($unsettled['teacher']);
    }

    public function test_the_sister_institutions_are_counted_only_when_asked_for(): void
    {
        $segment = 'Karim A., Daffodil Polytechnic Institute, Dhaka, Bangladesh';

        $this->assertFalse((new AffiliationMatcher(new MatchingOptions))->isOurs($segment));

        $this->assertTrue(
            (new AffiliationMatcher(new MatchingOptions(includeSisterInstitutions: true)))->isOurs($segment),
        );
    }

    public function test_the_rules_a_run_used_are_stored_with_it(): void
    {
        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [['A paper', '2025', 'Rahman, Mahfuz (7)', '7', 'Rahman M., Daffodil International University, Dhaka']],
        );

        $options = new MatchingOptions(useDepartmentTiebreak: false, includeSisterInstitutions: true);

        $summary = app(ScopusAnalysis::class)->run($path, $options)['summary'];

        // Written into the summary, which is what the workbook and the modal
        // read — so a result can still explain itself months later.
        $this->assertFalse($summary['options']['use_department_tiebreak']);
        $this->assertTrue($summary['options']['include_sister_institutions']);
        $this->assertTrue($summary['options']['match_by_scopus_id']);
    }

    public function test_an_ambiguous_person_is_not_counted_as_accounted_for(): void
    {
        // Counting a name that matched several teachers as a matched teacher
        // made the coverage figure identical whether the tie-breakers were on or
        // off, because the very people they settle were already counted settled.
        $shared = Teacher::query()
            ->select('first_name', 'last_name', DB::raw('COUNT(*) as n'))
            ->groupBy('first_name', 'last_name')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if (! $shared) {
            $this->markTestSkipped('No two teachers share a name.');
        }

        $name = $shared->last_name . ', ' . $shared->first_name;

        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [['A paper', '2025', $name . ' (7)', '7', 'X Y., Daffodil International University, Dhaka']],
        );

        $summary = app(ScopusAnalysis::class)->run($path, new MatchingOptions(
            useDepartmentTiebreak: false,
            usePaperAuthorsTiebreak: false,
        ))['summary'];

        $this->assertSame(1, $summary['people']['ambiguous_people']);
        $this->assertSame(0, $summary['people']['teacher'], 'An unnamed match is not a matched teacher.');
        $this->assertSame(0.0, (float) $summary['coverage']['percent_accounted_for']);
    }

    // ── Reading the file ─────────────────────────────────────────────────

    public function test_it_reads_by_column_name_not_position(): void
    {
        // The DoR workbook orders its columns four different ways across ten
        // sheets. Reading by position took Correspondence Address for Year.
        $path = $this->aCsvWith(
            ['Year', 'Authors with affiliations', 'Title'],
            [['2025', 'Rahman M., Daffodil International University, Dhaka', 'A paper about things']],
        );

        $rows = iterator_to_array(app(ScopusFileReader::class)->rows($path));

        $this->assertCount(1, $rows);
        $this->assertSame('A paper about things', $rows[0]['Title']);
        $this->assertSame('2025', $rows[0]['Year']);
    }

    public function test_it_handles_csv_file_with_utf8_bom(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scopus_bom_') . '.csv';
        file_put_contents($path, "\xEF\xBB\xBFTitle,Authors with affiliations\n\"Paper With BOM\",\"Rahman M., Daffodil International University, Dhaka\"\n");

        $rows = iterator_to_array(app(ScopusFileReader::class)->rows($path));

        $this->assertCount(1, $rows);
        $this->assertSame('Paper With BOM', $rows[0]['Title']);

        @unlink($path);
    }

    public function test_a_file_without_the_columns_we_need_is_refused_by_name(): void
    {
        $path = $this->aCsvWith(['Title', 'Year'], [['Something', '2025']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authors with affiliations');

        iterator_to_array(app(ScopusFileReader::class)->rows($path));
    }

    // ── The whole run ────────────────────────────────────────────────────

    public function test_it_finds_the_daffodil_author_among_foreign_co_authors(): void
    {
        $path = $this->aCsvWith(
            ['Title', 'Year', 'DOI', 'EID', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                'Deep learning for something',
                '2025',
                '10.1000/abc',
                '2-s2.0-111',
                'Fu, Xiang (111); Mahmud, Sakil (222); Luo, Zhen (333)',
                '111; 222; 333',
                'Fu X., Jingdezhen Ceramic University, China; '
                    . 'Mahmud S., Department of Textile Engineering, Daffodil International University, Dhaka; '
                    . 'Luo Z., Guangzhou University, China',
            ]],
        );

        $result = app(ScopusAnalysis::class)->run($path);

        $this->assertSame(1, $result['summary']['papers']['total']);

        // One of three, not three of three: the other two are Chinese
        // institutions and belong to nobody here.
        $this->assertSame(1, $result['summary']['people']['total']);

        $person = $result['people']->first();

        $this->assertStringContainsString('Mahmud', $person['name']);
        $this->assertSame('222', $person['scopus_id'], 'The Scopus id must come from the matching position.');
        $this->assertSame(3, substr_count($person['name'] . $result['papers']->first()['all_authors'], ';') + 1);
    }

    public function test_a_paper_we_already_hold_is_reported_as_such(): void
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications.');
        }

        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                $publication->title,
                (string) $publication->publication_year,
                'Rahman, Mahfuz (999)',
                '999',
                'Rahman M., Daffodil International University, Dhaka',
            ]],
        );

        $result = app(ScopusAnalysis::class)->run($path);

        $this->assertSame(1, $result['summary']['papers']['already_here']);
        $this->assertSame(0, $result['summary']['papers']['new']);
        $this->assertSame($publication->id, $result['papers']->first()['publication']->id);
    }

    public function test_the_same_paper_across_several_downloads_is_counted_once(): void
    {
        // The DoR workbook stacks ten cumulative exports, so a paper arrives up
        // to ten times. Counting it ten times would make the report meaningless.
        $row = [
            'Repeated paper title',
            '2025',
            '2-s2.0-999',
            'Rahman, Mahfuz (999)',
            '999',
            'Rahman M., Daffodil International University, Dhaka',
        ];

        $path = $this->aCsvWith(
            ['Title', 'Year', 'EID', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [$row, $row, $row],
        );

        $result = app(ScopusAnalysis::class)->run($path);

        $this->assertSame(1, $result['summary']['papers']['total']);
        $this->assertSame(3, $result['papers']->first()['seen']);
    }

    public function test_a_row_with_no_daffodil_author_is_left_out_and_counted(): void
    {
        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                'Entirely foreign paper',
                '2025',
                'Fu, Xiang (111)',
                '111',
                'Fu X., Jingdezhen Ceramic University, China',
            ]],
        );

        $result = app(ScopusAnalysis::class)->run($path);

        $this->assertSame(0, $result['summary']['papers']['total']);
        $this->assertSame(1, $result['summary']['papers']['rows_without_a_diu_author']);
    }

    public function test_coverage_counts_author_positions_not_papers(): void
    {
        // Two papers, three Daffodil positions between them, two distinct
        // people — one of whom is on both. Counting people would say two,
        // counting papers would say two, and neither is the coverage.
        $teacher = Teacher::whereNotNull('first_name')->whereNotNull('last_name')->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher.');
        }

        $known = $teacher->last_name . ', ' . $teacher->first_name;

        $path = $this->aCsvWith(
            ['Title', 'Year', 'EID', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [
                [
                    'First paper', '2025', '2-s2.0-1',
                    $known . ' (11); Nobody, Absolutely (22)',
                    '11; 22',
                    'A B., Daffodil International University, Dhaka; C D., Daffodil International University, Dhaka',
                ],
                [
                    'Second paper', '2025', '2-s2.0-2',
                    $known . ' (11)',
                    '11',
                    'A B., Daffodil International University, Dhaka',
                ],
            ],
        );

        $coverage = app(ScopusAnalysis::class)->run($path)['summary']['coverage'];

        $this->assertSame(3, $coverage['author_slots'], 'Three author positions across two papers.');
        $this->assertSame(1.5, $coverage['slots_per_paper']);

        // The known teacher holds two of the three positions; the stranger one.
        $this->assertSame(2, $coverage['slots_teacher']);
        $this->assertSame(1, $coverage['slots_unknown']);

        $this->assertSame(1, $coverage['papers_all_authors_known'], 'The second paper is fully known.');
        $this->assertSame(1, $coverage['papers_some_authors_known'], 'The first has one of two.');
        $this->assertSame(0, $coverage['papers_no_authors_known']);

        $this->assertEqualsWithDelta(66.7, $coverage['percent_accounted_for'], 0.1);
    }

    // ── Identifiers, and what our copy says about who wrote it ───────────

    public function test_a_recorded_doi_finds_the_paper_whatever_the_title_says(): void
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications.');
        }

        // Held inside the transaction the base TestCase rolls back.
        $publication->forceFill(['doi' => '10.1000/exactly-this'])->save();

        $path = $this->aCsvWith(
            ['Title', 'Year', 'DOI', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                // Deliberately nothing like the stored title: the point is that
                // the identifier is what found it.
                'A completely different sequence of words',
                '2025',
                '10.1000/EXACTLY-THIS',
                'Rahman, Mahfuz (7)',
                '7',
                'Rahman M., Daffodil International University, Dhaka',
            ]],
        );

        $result = app(ScopusAnalysis::class)->run($path);
        $paper = $result['papers']->first();

        $this->assertNotNull($paper['publication'], 'The DOI should have found it.');
        $this->assertSame($publication->id, $paper['publication']->id);
        $this->assertSame('doi', $paper['match_basis']);
        $this->assertSame(RecordResolver::CERTAIN, $paper['confidence']);
    }

    public function test_switching_identifier_matching_off_falls_back_to_the_title(): void
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications.');
        }

        $publication->forceFill(['doi' => '10.1000/ignored'])->save();

        $path = $this->aCsvWith(
            ['Title', 'Year', 'DOI', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [['Nothing like the stored title', '2025', '10.1000/ignored', 'Rahman, Mahfuz (7)', '7',
                'Rahman M., Daffodil International University, Dhaka']],
        );

        $paper = app(ScopusAnalysis::class)
            ->run($path, new MatchingOptions(matchPublicationsByIdentifier: false))['papers']
            ->first();

        $this->assertNull($paper['publication'], 'With the rule off the DOI must not be used.');
        $this->assertSame('none', $paper['match_basis']);
    }

    public function test_it_says_which_named_authors_our_copy_is_missing(): void
    {
        [$publication, $teacher] = $this->aPublicationCreditingOnly();

        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                $publication->title,
                (string) $publication->publication_year,
                $this->asScopusWritesIt($teacher) . ' (7)',
                '7',
                'X Y., Daffodil International University, Dhaka',
            ]],
        );

        // The teacher is credited nowhere on our copy, so Scopus naming them is
        // a real gap — not a spelling difference.
        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        $paper = app(ScopusAnalysis::class)->run($path)['papers']->first();

        $this->assertSame(\App\Services\Scopus\AuthorshipComparison::NOBODY_CREDITED, $paper['authorship']['status']);
        $this->assertStringContainsString('credit nobody', $paper['authorship']['note']);
    }

    public function test_it_marks_a_paper_whose_first_author_we_disagree_about(): void
    {
        [$publication, $teacher] = $this->aPublicationCreditingOnly();

        $someoneElse = Teacher::where('id', '!=', $teacher->id)
            ->whereNotNull('first_name')->whereNotNull('last_name')->first();

        if (! $someoneElse) {
            $this->markTestSkipped('Needs a second teacher.');
        }

        // Our copy says someone else wrote it first.
        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        DB::table('publication_authors')->insert([
            [
                'publication_id' => $publication->id,
                'authorable_type' => Teacher::class,
                'authorable_id' => $someoneElse->id,
                'author_role' => 'first',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'publication_id' => $publication->id,
                'authorable_type' => Teacher::class,
                'authorable_id' => $teacher->id,
                'author_role' => 'co_author',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                $publication->title,
                (string) $publication->publication_year,
                $this->asScopusWritesIt($teacher) . ' (7)',
                '7',
                'X Y., Daffodil International University, Dhaka',
            ]],
        );

        $paper = app(ScopusAnalysis::class)->run($path)['papers']->first();

        // Scopus lists our co-author first. That is not a detail — first
        // authorship decides the share of the incentive.
        $this->assertSame(
            \App\Services\Scopus\AuthorshipComparison::FIRST_AUTHOR_DIFFERS,
            $paper['authorship']['status'],
        );

        $this->assertStringContainsString($someoneElse->full_name, $paper['authorship']['note']);
        $this->assertNotNull($paper['authorship']['scopus_first_author']);
        $this->assertNotNull($paper['authorship']['our_first_author']);
    }

    public function test_a_name_nobody_could_place_is_offered_candidates(): void
    {
        $teacher = Teacher::whereNotNull('last_name')->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher.');
        }

        // A first name nobody has, sharing a family name with somebody we hold.
        $invented = $teacher->last_name . ', Zzyzxandra';

        $path = $this->aCsvWith(
            ['Title', 'Year', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [['Some paper', '2025', $invented . ' (7)', '7',
                'X Y., Daffodil International University, Dhaka']],
        );

        $person = app(ScopusAnalysis::class)->run($path)['people']->first();

        $this->assertSame('unknown', $person['match']['kind']);

        // Somebody to choose between, rather than a blank cell.
        $this->assertNotEmpty($person['candidates']);
    }

    // ── The job ──────────────────────────────────────────────────────────

    public function test_the_run_writes_a_workbook_and_changes_nothing(): void
    {
        $before = [
            'publications' => Publication::count(),
            'teachers' => Teacher::count(),
            'authors' => Author::count(),
            'pivots' => DB::table('publication_authors')->count(),
        ];

        $path = $this->aCsvWith(
            ['Title', 'Year', 'DOI', 'EID', 'Author full names', 'Author(s) ID', 'Authors with affiliations'],
            [[
                'A brand new paper nobody has',
                '2026',
                '10.1000/xyz',
                '2-s2.0-777',
                'Mahmud, Sakil (222)',
                '222',
                'Mahmud S., Department of Textile Engineering, Daffodil International University, Dhaka',
            ]],
        );

        Storage::disk('local')->put('scopus/test.csv', file_get_contents($path));

        $import = ScopusImport::create([
            'original_filename' => 'test.csv',
            'source_path' => 'scopus/test.csv',
            'status' => ScopusImport::STATUS_UPLOADED,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        app(BuildScopusReviewFile::class, ['import' => $import])
            ->handle(app(ScopusAnalysis::class), app(\App\Services\Scopus\ReviewWorkbook::class));

        $import->refresh();

        $this->assertSame(ScopusImport::STATUS_READY, $import->status, $import->failure_reason ?? '');
        $this->assertNotNull($import->result_path);
        $this->assertTrue(Storage::disk('public')->exists($import->result_path));

        /*
         * Split by what a reviewer has to do with each: nothing, decide who
         * wrote what, or enter the paper. One sheet of 1,553 rows is a sheet
         * nobody finishes.
         */
        $book = IOFactory::load(Storage::disk('public')->path($import->result_path));

        $this->assertSame(
            ['Summary', 'Matched', 'Needs attention', 'Not in our system', 'People'],
            $book->getSheetNames(),
        );
        $this->assertSame(1, $import->stat('papers.total'));
        $this->assertSame(1, $import->stat('papers.new'));

        Storage::disk('public')->delete($import->result_path);
        Storage::disk('local')->delete('scopus/test.csv');

        // The point of this step: it reads, it reports, it touches nothing.
        $this->assertSame($before['publications'], Publication::count());
        $this->assertSame($before['teachers'], Teacher::count());
        $this->assertSame($before['authors'], Author::count());
        $this->assertSame($before['pivots'], DB::table('publication_authors')->count());
    }

    public function test_a_bad_file_fails_the_run_with_a_reason_on_the_record(): void
    {
        Storage::disk('local')->put('scopus/bad.csv', "Title,Year\nSomething,2025\n");

        $import = ScopusImport::create([
            'original_filename' => 'bad.csv',
            'source_path' => 'scopus/bad.csv',
            'status' => ScopusImport::STATUS_UPLOADED,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        app(BuildScopusReviewFile::class, ['import' => $import])
            ->handle(app(ScopusAnalysis::class), app(\App\Services\Scopus\ReviewWorkbook::class));

        $import->refresh();

        $this->assertSame(ScopusImport::STATUS_FAILED, $import->status);

        // Named on the record, not only in the log — whoever uploaded it has to
        // be able to see what was wrong with their file.
        $this->assertStringContainsString('Authors with affiliations', $import->failure_reason);

        Storage::disk('local')->delete('scopus/bad.csv');
    }

    public function test_the_summary_can_be_read_without_downloading_anything(): void
    {
        $import = ScopusImport::create([
            'original_filename' => 'summary.csv',
            'source_path' => 'scopus/summary.csv',
            'status' => ScopusImport::STATUS_READY,
            'result_path' => 'exports/summary.xlsx',
            'summary' => [
                'papers' => ['total' => 1553, 'already_here' => 1518, 'new' => 35,
                    'with_doi' => 1549, 'rows_without_a_diu_author' => 19,
                    'rows_whose_columns_did_not_line_up' => 0],
                'people' => ['total' => 1257, 'teacher' => 447, 'external_author' => 431,
                    'looks_like_student' => 19, 'not_found' => 360, 'certain' => 35,
                    'likely' => 734, 'ambiguous' => 109, 'with_email' => 129, 'with_scopus_id' => 1257],
                'coverage' => ['author_slots' => 2944, 'slots_per_paper' => 1.9,
                    'slots_teacher' => 1231, 'slots_external_author' => 993,
                    'slots_student' => 99, 'slots_unknown' => 621,
                    'slots_accounted_for' => 2224, 'percent_accounted_for' => 75.5,
                    'papers_all_authors_known' => 998, 'papers_some_authors_known' => 310,
                    'papers_no_authors_known' => 245, 'papers_with_a_matched_teacher' => 878],
                'units' => ['faculty_resolved' => 1134, 'department_resolved' => 1111],
            ],
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        $html = view('filament.pages.partials.scopus-summary', ['import' => $import])->render();

        // Tables, not a wall of text — the numbers only mean something beside
        // each other. Includes the two that explain the run: what each match
        // rested on, and which rules it was told to use.
        $this->assertSame(7, substr_count($html, '<table'));

        // The reconciliation the whole panel exists for: people, positions and
        // share on one line.
        $this->assertStringContainsString('75.5%', $html);
        $this->assertStringContainsString('2,944', $html);
        $this->assertStringContainsString('1,231', $html);
        $this->assertStringContainsString('447', $html);

        // And the sentence that stops the two count columns being read as one.
        $this->assertStringContainsString('not comparable', $html);

        /*
         * The styling has to be real CSS, not Tailwind utility classes.
         *
         * This panel has no custom Filament theme — no tailwind.config, and vite
         * builds only the public themes — so a utility class written in a blade
         * file is compiled into no stylesheet at all and renders as nothing.
         * The first version of this view was styled entirely that way and came
         * out unbordered and unpadded, which is invisible to any assertion that
         * only checks the markup is present.
         */
        $this->assertStringContainsString('border: 1px solid var(--fms-line)', $html);
        $this->assertStringContainsString('.dark .fms-stat', $html, 'Dark mode must be handled.');

        $this->assertSame(
            0,
            preg_match_all('/class="[^"]*\b(?:px-\d|py-\d|border-gray-\d|bg-gray-\d|text-gray-\d|tabular-nums)\b/', $html),
            'Tailwind utility classes are never compiled for this panel and would render as nothing.',
        );
    }

    public function test_the_shared_stat_table_escapes_a_label_it_was_handed(): void
    {
        // The partial is generic, so a caller could one day pass something a
        // user typed. Anything that is not deliberately an HtmlString is escaped.
        $html = view('filament.pages.partials.stat-table', [
            'rows' => ['<script>alert(1)</script>' => 5],
        ])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        // An HtmlString still renders as markup, which is how the colour
        // swatches beside the category names get through.
        $marked = view('filament.pages.partials.stat-table', [
            'rows' => [['label' => new \Illuminate\Support\HtmlString('<b>bold</b>'), 5]],
        ])->render();

        $this->assertStringContainsString('<b>bold</b>', $marked);
    }

    // ── Removing a run ───────────────────────────────────────────────────

    public function test_a_super_admin_can_delete_a_run_and_its_files(): void
    {
        Storage::disk('local')->put('scopus/to-delete.csv', "Title,Authors with affiliations\nx,y\n");
        Storage::disk('public')->put('exports/to-delete.xlsx', 'not really a workbook');

        $import = ScopusImport::create([
            'original_filename' => 'to-delete.csv',
            'source_path' => 'scopus/to-delete.csv',
            'result_path' => 'exports/to-delete.xlsx',
            'status' => ScopusImport::STATUS_READY,
            'summary' => ['papers' => ['total' => 1]],
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        $this->actingAs($this->anAdmin());

        Livewire::test(ScopusReview::class)
            ->callTableAction('delete', $import)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('scopus_imports', ['id' => $import->id]);

        // Both files go with it — an orphaned 4.7 MB upload and a 488 KB
        // workbook would otherwise sit on disk with nothing pointing at them.
        $this->assertFalse(Storage::disk('local')->exists('scopus/to-delete.csv'));
        $this->assertFalse(Storage::disk('public')->exists('exports/to-delete.xlsx'));
    }

    public function test_only_a_super_admin_is_offered_the_delete(): void
    {
        $import = ScopusImport::create([
            'original_filename' => 'kept.csv',
            'source_path' => 'scopus/kept.csv',
            'status' => ScopusImport::STATUS_READY,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        $other = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->whereHas('roles')
            ->first();

        if (! $other) {
            $this->markTestSkipped('No non-super-admin with a role.');
        }

        $this->actingAs($other);

        $action = Livewire::test(ScopusReview::class)
            ->instance()
            ->getTable()
            ->getAction('delete')
            ->record($import);

        $this->assertFalse($action->isVisible(), 'Only a super admin removes a run.');
    }

    public function test_a_run_in_progress_cannot_be_deleted(): void
    {
        // Deleting the record out from under a working job leaves it writing to
        // nothing, and no record of why it stopped.
        $import = ScopusImport::create([
            'original_filename' => 'busy.csv',
            'source_path' => 'scopus/busy.csv',
            'status' => ScopusImport::STATUS_PROCESSING,
            'uploaded_by' => $this->anAdmin()->id,
        ]);

        $this->actingAs($this->anAdmin());

        $action = Livewire::test(ScopusReview::class)
            ->instance()
            ->getTable()
            ->getAction('delete')
            ->record($import);

        $this->assertFalse($action->isVisible());
    }

    public function test_the_page_queues_a_run_and_lists_it(): void
    {
        Queue::fake();

        $this->actingAs($this->anAdmin());

        $import = ScopusImport::create([
            'original_filename' => 'listed.csv',
            'source_path' => 'scopus/listed.csv',
            'status' => ScopusImport::STATUS_READY,
            'result_path' => 'exports/whatever.xlsx',
            'summary' => ['papers' => ['total' => 5, 'already_here' => 4, 'new' => 1],
                'people' => ['total' => 3, 'teacher' => 2, 'external_author' => 1, 'not_found' => 0]],
            'uploaded_by' => auth()->id(),
        ]);

        Livewire::test(ScopusReview::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$import]);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $rows
     */
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

    /**
     * A publication and a teacher, with nothing assumed about who is credited.
     *
     * @return array{0: Publication, 1: Teacher}
     */
    protected function aPublicationCreditingOnly(): array
    {
        $publication = Publication::first();
        $teacher = Teacher::whereNotNull('first_name')->whereNotNull('last_name')->first();

        if (! $publication || ! $teacher) {
            $this->markTestSkipped('Needs a publication and a teacher.');
        }

        return [$publication, $teacher];
    }

    /** Surname first, the way Scopus writes a name. */
    protected function asScopusWritesIt(Teacher $teacher): string
    {
        return $teacher->last_name . ', ' . trim($teacher->first_name . ' ' . $teacher->middle_name);
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
