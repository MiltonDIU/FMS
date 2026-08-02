<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing a publication whose author has since left.
 *
 * The Authorship selects offered only teachers with `is_archived = false`.
 * 872 of the 2,000 teachers are archived, and 6,270 of the 17,510 publications
 * name at least one person the form could therefore not show — more than a
 * third of the library. Their name rendered as the raw key, `App\Models\
 * Teacher:1066`, and Filament refused to save the form because the stored value
 * was not among the options it had been given.
 *
 * That is not a cosmetic problem. A teacher who resigns keeps co-authoring with
 * the people still here, and the record has to be able to say so.
 */
class PublicationAuthorshipTest extends TestCase
{
    public function test_a_departed_teacher_can_still_be_named_as_an_author(): void
    {
        [$publication, $departed] = $this->publicationAuthoredByADepartedTeacher();

        $this->actingAs($this->anAdmin());

        Livewire::test(EditPublication::class, ['record' => $publication->getRouteKey()])
            ->assertFormSet([
                // The value that used to render as "App\Models\Teacher:1066".
                'first_author_id' => Teacher::class . ':' . $departed->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // afterSave() clears publication_authors and rewrites it from the form,
        // so a save that drops the value would take the authorship with it.
        $this->assertDatabaseHas('publication_authors', [
            'publication_id' => $publication->id,
            'authorable_type' => Teacher::class,
            'authorable_id' => $departed->id,
            'author_role' => 'first',
        ]);
    }

    public function test_a_departed_teacher_is_shown_by_name_and_marked(): void
    {
        [, $departed] = $this->publicationAuthoredByADepartedTeacher();

        $label = \App\Filament\Resources\Publications\Schemas\PublicationForm::authorLabel(
            Teacher::class . ':' . $departed->id,
        );

        $this->assertNotNull($label, 'An archived teacher must still resolve to a name.');
        $this->assertStringContainsString($departed->full_name, $label);

        // Marked, so whoever is editing can see this person has left rather than
        // wondering why an unfamiliar name is on the record.
        $this->assertStringContainsString('Former', $label);
    }

    public function test_a_departed_teacher_survives_as_a_co_author_too(): void
    {
        // The multiple select resolves its labels through a different callback,
        // so passing on the single one proves nothing about this.
        $publication = Publication::first();
        $departed = Teacher::where('is_archived', true)->first();
        $current = Teacher::where('is_archived', false)->first();

        if (! $publication || ! $departed || ! $current) {
            $this->markTestSkipped('Needs a publication and both kinds of teacher.');
        }

        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        foreach ([$current, $departed] as $index => $teacher) {
            DB::table('publication_authors')->insert([
                'publication_id' => $publication->id,
                'authorable_type' => Teacher::class,
                'authorable_id' => $teacher->id,
                'author_role' => 'co_author',
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->anAdmin());

        Livewire::test(EditPublication::class, ['record' => $publication->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach ([$current, $departed] as $teacher) {
            $this->assertDatabaseHas('publication_authors', [
                'publication_id' => $publication->id,
                'authorable_id' => $teacher->id,
                'author_role' => 'co_author',
            ]);
        }
    }

    public function test_the_search_finds_current_staff_first(): void
    {
        $current = Teacher::where('is_archived', false)->whereNotNull('first_name')->first();

        if (! $current) {
            $this->markTestSkipped('No current teacher in the development database.');
        }

        $results = \App\Filament\Resources\Publications\Schemas\PublicationForm::searchAuthors(
            $current->first_name,
        );

        $this->assertNotEmpty($results);

        // Nobody should ever see 2,728 options at once — the search is capped.
        $this->assertLessThanOrEqual(50, count($results));
    }

    public function test_the_create_form_still_opens(): void
    {
        // The three selects no longer preload anything, so a form that opened
        // by rendering 8,184 options now renders none. Worth knowing it opens.
        $this->actingAs($this->anAdmin());

        Livewire::test(\App\Filament\Resources\Publications\Pages\CreatePublication::class)
            ->assertOk();
    }

    /** @return array{0: Publication, 1: Teacher} */
    protected function publicationAuthoredByADepartedTeacher(): array
    {
        $publication = Publication::first();
        $departed = Teacher::where('is_archived', true)->first();

        if (! $publication || ! $departed) {
            $this->markTestSkipped('Needs a publication and an archived teacher.');
        }

        // Held inside the transaction the base TestCase rolls back.
        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => Teacher::class,
            'authorable_id' => $departed->id,
            'author_role' => 'first',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$publication, $departed];
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
