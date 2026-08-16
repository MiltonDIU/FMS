<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * The `scopus_id` column on teachers and authors.
 *
 * One identifier, on the person's own row, for showing in a list and searching
 * by. `scopus_author_ids` remains the record of every identifier a person has —
 * 55 teachers hold more than one — and this column is kept in step with it
 * rather than written to independently.
 */
class ScopusIdColumnTest extends TestCase
{
    public function test_binding_an_identifier_fills_the_column(): void
    {
        $teacher = Teacher::whereNull('scopus_id')->firstOrFail();

        ScopusAuthorId::create([
            'scopus_author_id' => '55500000001',
            'authorable_type' => Teacher::class,
            'authorable_id' => $teacher->id,
            'source' => ScopusAuthorId::SOURCE_MANUAL,
        ]);

        $this->assertSame('55500000001', $teacher->fresh()->scopus_id);
    }

    public function test_a_second_identifier_does_not_displace_the_first(): void
    {
        $teacher = Teacher::whereNull('scopus_id')->firstOrFail();

        foreach (['55500000002', '55500000003'] as $identifier) {
            ScopusAuthorId::create([
                'scopus_author_id' => $identifier,
                'authorable_type' => Teacher::class,
                'authorable_id' => $teacher->id,
                'source' => ScopusAuthorId::SOURCE_MANUAL,
            ]);
        }

        $this->assertSame(
            '55500000002',
            $teacher->fresh()->scopus_id,
            'The profile a teacher is listed under should not change every time an export turns up another spelling.',
        );
        $this->assertSame(2, $teacher->scopusAuthorIds()->count());
    }

    public function test_removing_the_one_on_the_column_hands_the_place_to_whichever_is_left(): void
    {
        $teacher = Teacher::whereNull('scopus_id')->firstOrFail();

        foreach (['55500000004', '55500000005'] as $identifier) {
            ScopusAuthorId::create([
                'scopus_author_id' => $identifier,
                'authorable_type' => Teacher::class,
                'authorable_id' => $teacher->id,
                'source' => ScopusAuthorId::SOURCE_MANUAL,
            ]);
        }

        ScopusAuthorId::where('scopus_author_id', '55500000004')->firstOrFail()->delete();

        $this->assertSame('55500000005', $teacher->fresh()->scopus_id);

        ScopusAuthorId::where('scopus_author_id', '55500000005')->firstOrFail()->delete();

        $this->assertNull($teacher->fresh()->scopus_id);
    }

    public function test_an_external_author_carries_the_column_too(): void
    {
        $author = Author::createExternal('Quirin Nobodyhere');

        ScopusAuthorId::create([
            'scopus_author_id' => '55500000006',
            'authorable_type' => Author::class,
            'authorable_id' => $author->id,
            'source' => ScopusAuthorId::SOURCE_REVIEW,
        ]);

        $this->assertSame('55500000006', $author->fresh()->scopus_id);
    }

    /** Everyone already bound was given the column when it was added. */
    public function test_the_column_agrees_with_the_identifiers_table(): void
    {
        $disagreeing = Teacher::query()
            ->whereNotNull('scopus_id')
            ->whereDoesntHave('scopusAuthorIds', fn ($q) => $q->whereColumn('scopus_author_id', 'teachers.scopus_id'))
            ->count();

        $this->assertSame(0, $disagreeing, 'A teacher is listed under an identifier that is not recorded as theirs.');

        $unfilled = Teacher::query()
            ->whereNull('scopus_id')
            ->whereHas('scopusAuthorIds')
            ->count();

        $this->assertSame(0, $unfilled, 'A teacher holds a Scopus identifier but the column is empty.');
    }
}
