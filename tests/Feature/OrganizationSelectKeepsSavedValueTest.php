<?php

namespace Tests\Feature;

use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Teacher;
use App\Models\TrainingExperience;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An organisation a row already holds stays selectable, whatever its country.
 *
 * The institution, employer and training-centre selects narrow their list to
 * the row's country. Imported rows often hold the organiser's country on the
 * organisation and the venue's on the row, and the saved organisation was
 * filtered out: it showed as a bare id and the select rejected it, so the
 * profile could not be saved at all.
 */
class OrganizationSelectKeepsSavedValueTest extends TestCase
{
    public function test_a_training_row_whose_organisation_is_from_another_country_still_saves(): void
    {
        $this->actingAs(User::role('super_admin')->firstOrFail());

        // A profile with no other imported-row problems, so the only thing that
        // could stop the save is the row this test adds. Teacher 52 is the one
        // this was found on: two of its training rows are in exactly this state.
        $teacher = Teacher::find(52) ?? $this->markTestSkipped('teacher 52 is not in this database');
        $bangladesh = Country::where('slug', 'bangladesh')->firstOrFail();
        $elsewhere = Country::where('id', '!=', $bangladesh->id)->firstOrFail();

        $organiser = Organization::create([
            'name' => 'Overseas Organiser ' . uniqid(),
            'country_id' => $elsewhere->id,
            'is_training_center' => true,
            'is_active' => true,
        ]);

        $row = TrainingExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'Course held at home, run from abroad',
            'organization_id' => $organiser->id,
            'organization' => $organiser->name,
            'country_id' => $bangladesh->id,
            'sort_order' => 0,
        ]);

        Livewire::test(EditTeacher::class, ['record' => $teacher->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($organiser->id, (int) $row->fresh()->organization_id);
    }

    public function test_the_country_still_narrows_what_can_newly_be_picked(): void
    {
        $bangladesh = Country::where('slug', 'bangladesh')->firstOrFail();
        $elsewhere = Country::where('id', '!=', $bangladesh->id)->firstOrFail();

        $abroad = Organization::create([
            'name' => 'Abroad Centre ' . uniqid(),
            'country_id' => $elsewhere->id,
            'is_training_center' => true,
            'is_active' => true,
        ]);

        $options = fn ($current) => (new class extends \App\Filament\Resources\Teachers\Schemas\TeacherForm
        {
            public static function options($query, $get)
            {
                return static::organizationOptions($query, $get, 'is_training_center', 'organization_id');
            }
        })::options(
            Organization::query(),
            new class($bangladesh->id, $current) extends \Filament\Schemas\Components\Utilities\Get
            {
                public function __construct(private $country, private $current) {}

                public function __invoke(string | \Filament\Schemas\Components\Component $path = '', bool $isAbsolute = false): mixed
                {
                    return $path === 'country_id' ? $this->country : $this->current;
                }
            },
        )->pluck('organizations.id');

        $this->assertNotContains($abroad->id, $options(null), 'an organisation from another country is not offered for a new pick');
        $this->assertContains($abroad->id, $options($abroad->id), 'the organisation the row already holds is kept');
    }
}
