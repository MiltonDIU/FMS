<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Models\EmploymentStatus;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtering publications by the employment status of their teacher authors.
 *
 * A publication row carries its own faculty and department, but nothing that
 * says whether the person who wrote it is still at the university. So "how much
 * research have our current teachers produced" could not be asked of this page
 * at all — the answer has to come through the author.
 *
 * 1,128 teachers are here (878 active, 219 on study leave, 31 on leave) and 872
 * have left. The publications split 8,903 / 2,222 / 505 / 6,270 the same way,
 * and those add up past 17,510 on purpose: a paper written by a current teacher
 * and a departed one belongs to both.
 */
class PublicationAuthorStatusFilterTest extends TestCase
{
    public function test_it_returns_only_publications_whose_author_holds_the_status(): void
    {
        [$publication, $teacher, $status] = $this->aPublicationByATeacherWithAKnownStatus();

        $other = EmploymentStatus::where('id', '!=', $status->id)->first();

        $this->actingAs($this->anAdmin());

        Livewire::test(ListPublications::class)
            ->filterTable('author_employment_status', [$status->id])
            ->assertCanSeeTableRecords([$publication])
            // The same paper must drop out under a status its author does not hold.
            ->filterTable('author_employment_status', [$other->id])
            ->assertCanNotSeeTableRecords([$publication]);

        $this->assertNotNull($teacher);
    }

    public function test_several_statuses_can_be_selected_at_once(): void
    {
        [$publication, , $status] = $this->aPublicationByATeacherWithAKnownStatus();

        $other = EmploymentStatus::where('id', '!=', $status->id)->first();

        $this->actingAs($this->anAdmin());

        // The three statuses that mean "still here" are what an admin actually
        // picks, so selecting more than one has to widen the result, not narrow it.
        Livewire::test(ListPublications::class)
            ->filterTable('author_employment_status', [$status->id, $other->id])
            ->assertCanSeeTableRecords([$publication]);
    }

    public function test_an_empty_selection_filters_nothing(): void
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications in the development database.');
        }

        $this->actingAs($this->anAdmin());

        Livewire::test(ListPublications::class)
            ->filterTable('author_employment_status', [])
            ->assertCanSeeTableRecords([$publication]);
    }

    public function test_a_publication_with_no_teacher_author_is_never_matched(): void
    {
        $publication = Publication::whereDoesntHave('teachers')->first();

        if (! $publication) {
            $this->markTestSkipped('Every publication has a teacher author.');
        }

        $this->actingAs($this->anAdmin());

        Livewire::test(ListPublications::class)
            ->filterTable('author_employment_status', EmploymentStatus::pluck('id')->all())
            ->assertCanNotSeeTableRecords([$publication]);
    }

    /** @return array{0: Publication, 1: Teacher, 2: EmploymentStatus} */
    protected function aPublicationByATeacherWithAKnownStatus(): array
    {
        $publication = Publication::first();
        $teacher = Teacher::whereNotNull('employment_status_id')->first();

        if (! $publication || ! $teacher) {
            $this->markTestSkipped('Needs a publication and a teacher with a status.');
        }

        // Held inside the transaction the base TestCase rolls back. Every other
        // author is cleared so the paper matches on this teacher alone.
        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => Teacher::class,
            'authorable_id' => $teacher->id,
            'author_role' => 'first',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$publication, $teacher, $teacher->employmentStatus];
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
