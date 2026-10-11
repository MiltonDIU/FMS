<?php

namespace Tests\Feature;

use App\Filament\Widgets\PublicationAuthorshipRequestsWidget;
use App\Models\Author;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\PublicationAuthorshipAnswered;
use App\Notifications\PublicationAuthorshipRequested;
use App\Support\PublicationAuthorship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A teacher cannot put a colleague's name on a paper without the colleague agreeing.
 *
 * Named by another teacher, a colleague starts as pending: the paper is not on
 * their profile, in their counts or on its own page under their name until
 * they confirm it from their dashboard. A refusal is kept. Administrators,
 * the research team, the teacher themselves and external authors need no
 * answer.
 */
class PublicationAuthorshipConsentTest extends TestCase
{
    protected Teacher $author;     // the teacher adding the paper

    protected Teacher $colleague;  // the teacher they name

    protected Publication $publication;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        [$this->author, $this->colleague] = Teacher::with('user')
            ->whereHas('user')
            ->limit(50)
            ->get()
            ->filter(fn (Teacher $t) => ! PublicationAuthorship::trusts($t->user))
            ->take(2)
            ->values()
            ->all() + [null, null];

        if (! $this->author || ! $this->colleague) {
            $this->markTestSkipped('need two teachers with ordinary accounts');
        }

        $this->publication = Publication::firstOrFail();
        DB::table('publication_authors')->where('publication_id', $this->publication->id)->delete();
    }

    /**
     * The two accounts the problem was reported with — Muntachir Razzak adding
     * Mohammed Masum Iqbal — for the tests that save the whole My Profile
     * form. That form validates every row, and many imported profiles still
     * carry a row it rejects (an education with no degree type, a job with no
     * start date), which would stop the save before it reached what is tested.
     */
    protected function useTheReportedTeachers(): void
    {
        $this->author = Teacher::with('user')->find(2125) ?? $this->markTestSkipped('teacher 2125 is not in this database');
        $this->colleague = Teacher::with('user')->find(52) ?? $this->markTestSkipped('teacher 52 is not in this database');

        // A paper of the author's own, so it is on their profile form.
        $this->publication = $this->author->publications()->first() ?? $this->markTestSkipped('teacher 2125 has no publication');
        DB::table('publication_authors')->where('publication_id', $this->publication->id)->delete();
    }

    /** The paper as the author's own profile form would save it. */
    protected function saveAsAuthor(array $coAuthors): void
    {
        PublicationAuthorship::write($this->publication, [
            'first_author_id' => Teacher::class . ':' . $this->author->id,
            'co_author_ids' => $coAuthors,
        ], $this->author, $this->author->user);
    }

    protected function statusOf(Teacher $teacher): array
    {
        return DB::table('publication_authors')
            ->where('publication_id', $this->publication->id)
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->pluck('confirmation_status')
            ->all();
    }

    public function test_existing_authorships_were_all_left_confirmed(): void
    {
        $this->assertSame(0, DB::table('publication_authors')->where('confirmation_status', '!=', PublicationAuthorship::CONFIRMED)->count());
    }

    public function test_a_colleague_named_by_a_teacher_waits_and_is_not_credited_until_they_confirm(): void
    {
        $external = Author::firstOrFail();

        $this->saveAsAuthor([
            Teacher::class . ':' . $this->colleague->id,
            Author::class . ':' . $external->id,
        ]);

        $this->assertSame([PublicationAuthorship::CONFIRMED], $this->statusOf($this->author));
        $this->assertSame([PublicationAuthorship::PENDING], $this->statusOf($this->colleague));
        $this->assertSame(PublicationAuthorship::CONFIRMED, DB::table('publication_authors')
            ->where('publication_id', $this->publication->id)->where('authorable_type', Author::class)->value('confirmation_status'));

        // Not theirs yet — not on their profile, and not under their name on the paper.
        $this->assertFalse($this->colleague->publications()->whereKey($this->publication->id)->exists());
        $this->assertTrue($this->author->publications()->whereKey($this->publication->id)->exists());
        $this->assertNotContains($this->colleague->id, $this->publication->teachers()->pluck('teachers.id')->all());

        Notification::assertSentTo($this->colleague->user, PublicationAuthorshipRequested::class);
        Notification::assertNotSentTo($this->author->user, PublicationAuthorshipRequested::class);

        // Saving again keeps them waiting, and does not ask a second time.
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);
        $this->assertSame([PublicationAuthorship::PENDING], $this->statusOf($this->colleague));
        Notification::assertSentToTimes($this->colleague->user, PublicationAuthorshipRequested::class, 1);
    }

    public function test_confirming_puts_the_paper_on_their_profile_and_tells_whoever_added_them(): void
    {
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);

        PublicationAuthorship::answer($this->publication, $this->colleague, true);

        $this->assertSame([PublicationAuthorship::CONFIRMED], $this->statusOf($this->colleague));
        $this->assertTrue($this->colleague->publications()->whereKey($this->publication->id)->exists());
        Notification::assertSentTo($this->author->user, PublicationAuthorshipAnswered::class, fn ($n) => $n->accepted);
    }

    public function test_a_refusal_is_kept_through_later_saves_and_cannot_be_undone_by_naming_them_again(): void
    {
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);

        PublicationAuthorship::answer($this->publication, $this->colleague, false);
        Notification::assertSentTo($this->author->user, PublicationAuthorshipAnswered::class, fn ($n) => ! $n->accepted);

        // The form does not show them any more, so a save leaves them out…
        $this->saveAsAuthor([]);
        $this->assertSame([PublicationAuthorship::DECLINED], $this->statusOf($this->colleague));

        // …and naming them again does not turn the "no" back into a request.
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);
        $this->assertSame([PublicationAuthorship::DECLINED], $this->statusOf($this->colleague));
        $this->assertFalse($this->colleague->publications()->whereKey($this->publication->id)->exists());
        Notification::assertSentToTimes($this->colleague->user, PublicationAuthorshipRequested::class, 1);
    }

    public function test_an_administrator_naming_a_teacher_needs_no_answer(): void
    {
        $admin = User::role('super_admin')->firstOrFail();

        PublicationAuthorship::sync($this->publication, [
            'co_author_ids' => [Teacher::class . ':' . $this->colleague->id],
        ], actor: $admin);

        $this->assertSame([PublicationAuthorship::CONFIRMED], $this->statusOf($this->colleague));
        Notification::assertNothingSentTo($this->colleague->user);
    }

    public function test_a_co_author_listed_twice_is_written_once(): void
    {
        $key = Teacher::class . ':' . $this->colleague->id;

        $this->saveAsAuthor([$key, $key]);

        $this->assertCount(1, $this->statusOf($this->colleague));
    }

    /**
     * The case that was reported: a teacher adds a co-author to a paper already
     * on their profile, changes nothing else, and saves from My Profile. The
     * page said "submitted successfully" while the change was judged to be no
     * change at all and thrown away, because the author fields were skipped
     * when looking for differences.
     */
    public function test_adding_only_a_co_author_from_my_profile_is_saved_and_asks_the_colleague(): void
    {
        $this->useTheReportedTeachers();
        $this->saveAsAuthor([]);
        $this->assertSame([], $this->statusOf($this->colleague));

        $this->actingAs($this->author->user);

        $page = Livewire::test(\App\Filament\Pages\MyProfile::class);

        $key = collect($page->get('data.publications'))
            ->search(fn ($row) => (int) ($row['id'] ?? 0) === $this->publication->id);

        $this->assertNotFalse($key, 'the paper is on the teacher\'s own profile form');

        $coAuthors = $page->get("data.publications.{$key}.co_author_ids") ?? [];

        $page->set("data.publications.{$key}.co_author_ids", [...$coAuthors, Teacher::class . ':' . $this->colleague->id])
            ->call('save')
            ->assertNotified('Profile update submitted successfully');

        $this->assertSame([PublicationAuthorship::PENDING], $this->statusOf($this->colleague));
        Notification::assertSentTo($this->colleague->user, PublicationAuthorshipRequested::class);
    }

    public function test_saving_my_profile_with_nothing_changed_says_so(): void
    {
        $this->useTheReportedTeachers();
        $this->saveAsAuthor([]);
        $this->actingAs($this->author->user);

        Livewire::test(\App\Filament\Pages\MyProfile::class)
            ->call('save')
            ->assertNotified('No changes to save');
    }

    /**
     * "Assigned to Me" keeps the record after the answer: who added the
     * teacher, when, and what they said — and only for papers somebody else
     * put them on.
     */
    public function test_assigned_to_me_keeps_who_added_the_teacher_when_and_their_answer(): void
    {
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);

        $row = \App\Models\PublicationAuthor::where('publication_id', $this->publication->id)
            ->where('authorable_id', $this->colleague->id)->firstOrFail();
        $this->assertSame($this->author->user->id, (int) $row->added_by);

        $this->actingAs($this->colleague->user);

        Livewire::test(\App\Filament\Pages\AssignedPublications::class)
            ->assertCanSeeTableRecords([$row])
            ->assertSee($this->author->full_name)
            ->assertSee('Pending')
            ->callTableAction('decline', $row)
            ->assertCanSeeTableRecords([$row])
            ->assertSee('Declined');

        $this->assertNotNull($row->fresh()->answered_at);
    }

    public function test_assigned_to_me_leaves_out_the_teachers_own_papers(): void
    {
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);

        $own = \App\Models\PublicationAuthor::where('publication_id', $this->publication->id)
            ->where('authorable_id', $this->author->id)->firstOrFail();

        $this->actingAs($this->author->user);

        Livewire::test(\App\Filament\Pages\AssignedPublications::class)
            ->assertCanNotSeeTableRecords([$own]);
    }

    public function test_the_dashboard_lists_what_is_waiting_and_confirms_it(): void
    {
        $this->saveAsAuthor([Teacher::class . ':' . $this->colleague->id]);

        $this->actingAs($this->colleague->user);
        $this->assertTrue(PublicationAuthorshipRequestsWidget::canView());

        Livewire::test(PublicationAuthorshipRequestsWidget::class)
            ->assertSee($this->author->full_name)
            ->callTableAction('confirm', $this->publication);

        $this->assertSame([PublicationAuthorship::CONFIRMED], $this->statusOf($this->colleague));
        $this->assertFalse(PublicationAuthorshipRequestsWidget::canView(), 'nothing left to answer, so the widget goes away');
    }
}
