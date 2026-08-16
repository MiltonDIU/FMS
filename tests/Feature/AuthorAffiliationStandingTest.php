<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorType;
use Tests\TestCase;

/**
 * Telling an outsider from one of ours, in the authors table.
 *
 * Every row in it looked the same — 7,347 guest authors with placeholder
 * addresses — so a co-author at Universiti Malaysia Pahang and one of our own
 * teachers under a misspelt name were indistinguishable, and the only way to
 * guess was the name, which is exactly how they got mixed up to begin with.
 */
class AuthorAffiliationStandingTest extends TestCase
{
    protected array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $id) {
            Author::withTrashed()->where('id', $id)->forceDelete();
        }

        parent::tearDown();
    }

    protected function anAuthor(string $name): Author
    {
        $author = Author::create([
            'name' => $name,
            'email' => 'standing-test-' . uniqid() . '@fms.com',
            'author_type_id' => AuthorType::query()->where('name', 'GA')->value('id') ?? 2,
            'is_active' => true,
        ]);

        $this->made[] = $author->id;

        return $author;
    }

    public function test_a_new_author_has_no_standing_until_a_run_says_so(): void
    {
        $author = $this->anAuthor('Nobody Yet');

        // Not false. Nothing has looked, which is a different answer from
        // "an export placed them somewhere else".
        $this->assertNull($author->fresh()->used_our_affiliation);
    }

    public function test_it_records_the_institution_when_the_author_is_not_ours(): void
    {
        $author = $this->anAuthor('Herwan Sulaiman');

        $author->recordAffiliationStanding(false, 'Universiti Malaysia Pahang');

        $author->refresh();

        $this->assertFalse($author->used_our_affiliation);
        $this->assertSame('Universiti Malaysia Pahang', $author->affiliation);
    }

    public function test_writing_under_our_affiliation_once_settles_it(): void
    {
        $author = $this->anAuthor('Maruf Ahmed');

        $author->recordAffiliationStanding(false, 'Southeast University');
        $author->recordAffiliationStanding(true);

        $author->refresh();

        $this->assertTrue($author->used_our_affiliation);

        // Whose address appeared on some other paper is not what the row is for
        // once we know they wrote under ours.
        $this->assertNull($author->affiliation);
    }

    public function test_a_later_paper_elsewhere_does_not_undo_it(): void
    {
        $author = $this->anAuthor('Rabiul Islam');

        $author->recordAffiliationStanding(true);
        $author->recordAffiliationStanding(false, 'BRAC University');

        // "Ever", not "last": somebody who appears once under our name and five
        // times under a former employer's is still ours. The reverse would let
        // whichever paper happened to be processed last decide who they are.
        $this->assertTrue($author->fresh()->used_our_affiliation);
    }

    public function test_the_scopes_separate_the_two_kinds(): void
    {
        $ours = $this->anAuthor('One Of Ours');
        $ours->recordAffiliationStanding(true);

        $outsider = $this->anAuthor('An Outsider');
        $outsider->recordAffiliationStanding(false, 'Somewhere Else');

        $unknown = $this->anAuthor('Never Established');

        $possiblyOurs = Author::query()->possiblyOurs()->pluck('id');
        $neverOurs = Author::query()->neverOurs()->pluck('id');

        $this->assertTrue($possiblyOurs->contains($ours->id));
        $this->assertFalse($possiblyOurs->contains($outsider->id));
        $this->assertFalse($possiblyOurs->contains($unknown->id),
            'Never established is not the same as ours.');

        $this->assertTrue($neverOurs->contains($outsider->id));
        $this->assertFalse($neverOurs->contains($unknown->id),
            'Never established is not the same as not ours.');
    }

    public function test_an_author_already_merged_is_not_offered_again(): void
    {
        $teacherId = \App\Models\Teacher::query()->value('id');

        if (! $teacherId) {
            $this->markTestSkipped('No teacher to merge into.');
        }

        $author = $this->anAuthor('Already Handled');
        $author->recordAffiliationStanding(true);
        $author->update(['merged_into_teacher_id' => $teacherId, 'merged_at' => now()]);

        // The filter exists to find work still to do, and this is done.
        $this->assertFalse(Author::query()->possiblyOurs()->pluck('id')->contains($author->id));
    }
}
