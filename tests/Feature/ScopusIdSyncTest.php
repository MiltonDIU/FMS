<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorType;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * The scopus_id column on teachers and authors, kept in step with the table
 * that actually records the identifiers.
 *
 * Both columns sat empty through an import of 793 papers. The mirroring worked
 * — it always had — but nothing was ever creating a row for it to mirror: the
 * importers take the identifier out of the author list, and by then the
 * analysis has already stripped it off the names.
 */
class ScopusIdSyncTest extends TestCase
{
    protected array $authors = [];

    protected array $bound = [];

    protected function tearDown(): void
    {
        ScopusAuthorId::whereIn('scopus_author_id', $this->bound)->delete();

        foreach ($this->authors as $id) {
            Author::withTrashed()->where('id', $id)->forceDelete();
        }

        parent::tearDown();
    }

    protected function anAuthor(string $name): Author
    {
        $author = Author::create([
            'name' => $name,
            'email' => 'sync-test-' . uniqid() . '@fms.com',
            'author_type_id' => AuthorType::query()->where('name', 'GA')->value('id') ?? 2,
            'is_active' => true,
        ]);

        $this->authors[] = $author->id;

        return $author;
    }

    protected function anId(): string
    {
        // Outside the range a real export uses, so nothing here can collide
        // with the 5,682 identifiers already recorded.
        $id = '99' . random_int(10000000, 99999999);

        $this->bound[] = $id;

        return $id;
    }

    public function test_binding_an_identifier_fills_the_authors_column(): void
    {
        $author = $this->anAuthor('Needs An Id');
        $id = $this->anId();

        $this->assertTrue(ScopusAuthorId::bindTo($author, $id));

        $this->assertSame($id, $author->fresh()->scopus_id);
    }

    public function test_binding_an_identifier_fills_the_teachers_column(): void
    {
        $teacher = Teacher::query()->whereNull('scopus_id')->first();

        if (! $teacher) {
            $this->markTestSkipped('Every teacher already carries an identifier.');
        }

        $id = $this->anId();

        $this->assertTrue(ScopusAuthorId::bindTo($teacher, $id));
        $this->assertSame($id, $teacher->fresh()->scopus_id);

        // A model delete, not a mass one — see the test below for why that
        // distinction is load-bearing.
        ScopusAuthorId::where('scopus_author_id', $id)->first()->delete();

        $this->assertNull($teacher->fresh()->scopus_id);
    }

    public function test_a_mass_delete_leaves_the_column_behind(): void
    {
        $author = $this->anAuthor('Deleted In Bulk');
        $id = $this->anId();

        ScopusAuthorId::bindTo($author, $id);
        $this->assertSame($id, $author->fresh()->scopus_id);

        // The mirroring hangs off Eloquent's deleted event, and a query-builder
        // delete does not fire model events at all. Recorded rather than fixed,
        // because it cannot be fixed at the model: anything clearing these rows
        // in bulk has to delete them as models, or the columns go stale.
        ScopusAuthorId::where('scopus_author_id', $id)->delete();

        $this->assertSame($id, $author->fresh()->scopus_id,
            'A mass delete bypasses the sync — this documents it, it is not an endorsement.');

        // Which is what a caller doing it properly gets instead.
        $author->forceFill(['scopus_id' => null])->save();
        $this->assertNull($author->fresh()->scopus_id);
    }

    public function test_a_second_identifier_does_not_displace_the_first(): void
    {
        $author = $this->anAuthor('Two Profiles');
        $first = $this->anId();
        $second = $this->anId();

        ScopusAuthorId::bindTo($author, $first);
        ScopusAuthorId::bindTo($author, $second);

        // Scopus splits one person across profiles, so several is normal. The
        // column carries one of them and must not change under the teacher each
        // time an export turns up another spelling of their name.
        $this->assertSame($first, $author->fresh()->scopus_id);
        $this->assertSame(2, $author->scopusAuthorIds()->count());
    }

    public function test_an_identifier_someone_else_holds_is_not_taken(): void
    {
        $first = $this->anAuthor('Got There First');
        $second = $this->anAuthor('Wants It Too');
        $id = $this->anId();

        $this->assertTrue(ScopusAuthorId::bindTo($first, $id));

        // Unique by design: one identifier names one profile. A blind create
        // here is an exception waiting for the first export that disagrees with
        // a binding a reviewer already made.
        $this->assertFalse(ScopusAuthorId::bindTo($second, $id));

        $this->assertSame($id, $first->fresh()->scopus_id);
        $this->assertNull($second->fresh()->scopus_id);
        $this->assertTrue(ScopusAuthorId::ownerOf($id)->is($first));
    }

    public function test_removing_the_held_identifier_hands_the_column_over(): void
    {
        $author = $this->anAuthor('Losing One');
        $first = $this->anId();
        $second = $this->anId();

        ScopusAuthorId::bindTo($author, $first);
        ScopusAuthorId::bindTo($author, $second);

        ScopusAuthorId::where('scopus_author_id', $first)->first()->delete();

        $this->assertSame($second, $author->fresh()->scopus_id);
    }

    public function test_nothing_is_bound_without_an_identifier(): void
    {
        $author = $this->anAuthor('No Id At All');

        // "If someone does not have one, that is fine" — an author with no
        // identifier is the normal case, not a failure.
        $this->assertFalse(ScopusAuthorId::bindTo($author, null));
        $this->assertFalse(ScopusAuthorId::bindTo($author, ''));
        $this->assertFalse(ScopusAuthorId::bindTo($author, '   '));

        $this->assertNull($author->fresh()->scopus_id);
        $this->assertSame(0, $author->scopusAuthorIds()->count());
    }
}
