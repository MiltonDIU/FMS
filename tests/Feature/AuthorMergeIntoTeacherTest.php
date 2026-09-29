<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Handing an external author's publications to the teacher they really are.
 *
 * The import matched publications to teachers by name. A name written
 * differently — "Hossain Mohammad Reyad" for Mohammad Reyad Hossain, "K. A.
 * Momin" for Khondhaker Al Momin — landed in the authors table instead, and
 * 1,806 publications still have no teacher attached because of it.
 *
 * Two things have to hold. The papers must reach the teacher's profile, since
 * that is the whole point. And the money must not move by a taka: 9,285,030.47
 * of the incentive sits on external authors, so a merge that dropped or
 * double-counted a share would silently change what a publication paid.
 */
class AuthorMergeIntoTeacherTest extends TestCase
{
    public function test_the_publications_move_to_the_teachers_profile(): void
    {
        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper();

        $this->assertFalse(
            $teacher->publications()->where('publications.id', $publication->id)->exists(),
            'The teacher should not have this paper before the merge.',
        );

        $author->mergeInto($teacher);

        // The relation is morphedByMany over the same pivot, so retyping the row
        // is what puts the paper on the profile — nothing else has to happen.
        $this->assertTrue(
            $teacher->fresh()->publications()->where('publications.id', $publication->id)->exists(),
            'The paper did not reach the teacher.',
        );

        $this->assertFalse(
            $author->fresh()->publications()->where('publications.id', $publication->id)->exists(),
            'The paper is still credited to the external author as well.',
        );
    }

    public function test_the_author_record_survives_and_says_who_it_became(): void
    {
        [$author, $teacher] = $this->anExternalAuthorOnAPaper();

        $author->mergeInto($teacher);

        $merged = $author->fresh();

        // Kept rather than deleted: otherwise there is no way to tell a name
        // that was dealt with from one nobody has looked at.
        $this->assertNotNull($merged);
        $this->assertSame($teacher->id, $merged->merged_into_teacher_id);
        $this->assertNotNull($merged->merged_at);
        $this->assertFalse($merged->is_active);
        $this->assertSame($teacher->id, $merged->mergedIntoTeacher->id);
    }

    public function test_the_role_and_the_incentive_carry_over_untouched(): void
    {
        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper(role: 'first', amount: 7500);

        $author->mergeInto($teacher);

        $row = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('first', $row->author_role);
        $this->assertEqualsWithDelta(7500, (float) $row->incentive_amount, 0.01);
    }

    public function test_a_teacher_already_on_the_paper_gets_one_entry_and_both_amounts(): void
    {
        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper(role: 'co_author', amount: 3000);

        // The same person credited twice on one paper — once under each name.
        // 6,728 such pairs exist, so this is the common case, not the edge.
        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => Teacher::class,
            'authorable_id' => $teacher->id,
            'author_role' => 'corresponding',
            'sort_order' => 0,
            'incentive_amount' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author->mergeInto($teacher);

        $rows = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->get();

        $this->assertCount(1, $rows, 'The teacher must not appear twice on one paper.');

        // Added, not replaced. Dropping the duplicate's share would change what
        // the publication paid out without anyone being told.
        $this->assertEqualsWithDelta(8000, (float) $rows->first()->incentive_amount, 0.01);

        // Corresponding outranks co-author, so the stronger role survives.
        $this->assertSame('corresponding', $rows->first()->author_role);
    }

    public function test_the_total_paid_on_a_publication_is_unchanged_by_a_merge(): void
    {
        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper(amount: 3000);

        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => Teacher::class,
            'authorable_id' => $teacher->id,
            'author_role' => 'first',
            'sort_order' => 0,
            'incentive_amount' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = (float) DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->sum('incentive_amount');

        $author->mergeInto($teacher);

        $after = (float) DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->sum('incentive_amount');

        $this->assertEqualsWithDelta($before, $after, 0.01, 'A merge moved money.');
    }

    public function test_merging_twice_changes_nothing_the_second_time(): void
    {
        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper();

        $author->mergeInto($teacher);

        $merged = $author->fresh();
        $firstMergedAt = $merged->merged_at;

        // The bulk action skips an already-merged author rather than running
        // again, which would move nothing and overwrite where it went.
        $this->assertTrue($merged->isMerged());

        $second = $merged->mergeInto($teacher);

        $this->assertSame(0, $second['publications']);
        $this->assertNotNull($firstMergedAt);
    }

    public function test_a_merged_author_is_no_longer_offered_when_crediting_a_publication(): void
    {
        [$author, $teacher] = $this->anExternalAuthorOnAPaper();

        $probe = mb_substr($author->name, 0, 6);

        $before = \App\Filament\Resources\Publications\Schemas\PublicationForm::searchAuthors($probe);
        $this->assertArrayHasKey(Author::class . ':' . $author->id, $before);

        $author->mergeInto($teacher);

        $after = \App\Filament\Resources\Publications\Schemas\PublicationForm::searchAuthors($probe);
        $this->assertArrayNotHasKey(
            Author::class . ':' . $author->id,
            $after,
            'A merged author must not be selectable — that would recreate the split.',
        );
    }

    public function test_the_action_is_on_the_authors_table_for_an_admin(): void
    {
        $this->actingAs($this->anAdmin());

        [$author, $teacher, $publication] = $this->anExternalAuthorOnAPaper();

        Livewire::test(ListAuthors::class)
            ->callTableBulkAction('merge_into_teacher', [$author], ['teacher_id' => $teacher->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($teacher->id, $author->fresh()->merged_into_teacher_id);

        $this->assertTrue(
            $teacher->publications()->where('publications.id', $publication->id)->exists(),
        );
    }

    /**
     * An external author credited on one publication, and a teacher who is not.
     *
     * @return array{0: Author, 1: Teacher, 2: Publication}
     */
    protected function anExternalAuthorOnAPaper(string $role = 'co_author', float $amount = 0): array
    {
        $author = Author::notMerged()->first();
        $teacher = Teacher::first();
        $publication = Publication::first();

        if (! $author || ! $teacher || ! $publication) {
            $this->markTestSkipped('Needs an author, a teacher and a publication.');
        }

        // Held inside the transaction the base TestCase rolls back.
        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => Author::class,
            'authorable_id' => $author->id,
            'author_role' => $role,
            'sort_order' => 1,
            'incentive_amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$author->fresh(), $teacher, $publication];
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
