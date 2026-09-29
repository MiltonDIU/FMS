<?php

namespace Tests\Feature;

use App\Models\EmploymentStatus;
use App\Models\Teacher;
use App\Models\TeacherServicePeriod;
use App\Support\TeacherServicePeriods;
use Tests\TestCase;

/**
 * A teacher who leaves and comes back keeps one profile.
 *
 * The form holds the current appointment only — joining date, leaving date,
 * status. The periods keep the earlier ones: leaving closes the open period,
 * returning opens a new one and leaves the old one as it was.
 */
class TeacherServicePeriodsTest extends TestCase
{
    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = Teacher::whereNotNull('department_id')->firstOrFail();

        // A clean history for this teacher, inside the rolled-back test.
        TeacherServicePeriod::where('teacher_id', $this->teacher->id)->delete();
    }

    public function test_leaving_closes_the_open_period_and_returning_opens_a_new_one(): void
    {
        $this->set('active', '2015-01-10', null);

        $this->assertSame(1, $this->periods()->count());
        $this->assertFalse($this->periods()->first()->ended);

        $this->set('resigned', '2015-01-10', '2020-06-30');

        $first = $this->periods()->first();
        $this->assertSame(1, $this->periods()->count());
        $this->assertTrue($first->ended);
        $this->assertSame('2020-06-30', $first->left_on->toDateString());

        // Back, under the same employee ID, with a new joining date.
        $this->set('active', '2023-02-01', null);

        $periods = $this->periods();
        $this->assertSame(2, $periods->count());
        $this->assertSame('2015-01-10', $periods[0]->joined_on->toDateString());
        $this->assertSame('2020-06-30', $periods[0]->left_on->toDateString());
        $this->assertTrue($periods[0]->ended);
        $this->assertSame('2023-02-01', $periods[1]->joined_on->toDateString());
        $this->assertFalse($periods[1]->ended);
    }

    public function test_correcting_the_joining_date_moves_the_open_period_rather_than_adding_one(): void
    {
        $this->set('active', '2015-01-10', null);
        $this->set('active', '2015-02-10', null);

        $this->assertSame(1, $this->periods()->count());
        $this->assertSame('2015-02-10', $this->periods()->first()->joined_on->toDateString());
    }

    public function test_a_later_correction_to_the_leaving_date_goes_to_the_period_that_ended(): void
    {
        $this->set('active', '2015-01-10', null);
        $this->set('retired', '2015-01-10', '2024-12-31');
        $this->set('retired', '2015-01-10', '2025-01-15');

        $this->assertSame(1, $this->periods()->count());
        $this->assertSame('2025-01-15', $this->periods()->first()->left_on->toDateString());
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->set('active', '2015-01-10', null);
        TeacherServicePeriods::sync($this->teacher->fresh());
        TeacherServicePeriods::sync($this->teacher->fresh());

        $this->assertSame(1, $this->periods()->count());
    }

    public function test_only_the_statuses_that_end_an_appointment_close_one(): void
    {
        foreach (['resigned', 'retired', 'terminated', 'archived'] as $slug) {
            $this->assertTrue(TeacherServicePeriods::isEndingStatus($this->statusId($slug)), $slug);
        }

        foreach (['active', 'on-leave', 'study-leave', 'deputation', 'suspended'] as $slug) {
            $this->assertFalse(TeacherServicePeriods::isEndingStatus($this->statusId($slug)), $slug);
        }
    }

    protected function set(string $status, ?string $joined, ?string $left): void
    {
        $this->teacher->forceFill([
            'employment_status_id' => $this->statusId($status),
            'joining_date' => $joined,
            'leaving_date' => $left,
        ])->saveQuietly();

        TeacherServicePeriods::sync($this->teacher->fresh());
    }

    protected function periods()
    {
        return TeacherServicePeriod::where('teacher_id', $this->teacher->id)->orderBy('id')->get();
    }

    protected function statusId(string $slug): int
    {
        $id = EmploymentStatus::withoutGlobalScopes()->where('slug', $slug)->value('id');

        if (! $id) {
            $this->markTestSkipped("no \"{$slug}\" employment status");
        }

        return (int) $id;
    }
}
