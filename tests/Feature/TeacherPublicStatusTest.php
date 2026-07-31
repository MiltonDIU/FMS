<?php

namespace Tests\Feature;

use App\Models\EmploymentStatus;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * The directory shows teachers who are on leave alongside everyone else, so
 * without a marker the page quietly asserts something false: that the person is
 * at their desk. Roughly a fifth of the visible teachers are on study or
 * ordinary leave, so this is not an edge case.
 *
 * These guard the rule itself and the fact that all four themes render it —
 * a per-theme copy is exactly what would rot.
 */
class TeacherPublicStatusTest extends TestCase
{
    protected function teacherWithStatus(string $slug): Teacher
    {
        $status = EmploymentStatus::where('slug', $slug)->first();

        if (! $status) {
            $this->markTestSkipped("No employment status '{$slug}' seeded.");
        }

        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->with('department.faculty', 'designation')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('No visible teacher in the development database.');
        }

        // Held in the transaction the base TestCase rolls back.
        $teacher->employment_status_id = $status->id;
        $teacher->save();

        return $teacher->fresh(['employmentStatus', 'department.faculty', 'designation']);
    }

    public function test_an_ordinary_active_teacher_says_nothing(): void
    {
        $this->assertNull($this->teacherWithStatus('active')->public_status);
    }

    public function test_a_teacher_on_leave_carries_a_label(): void
    {
        $status = $this->teacherWithStatus('study-leave')->public_status;

        $this->assertNotNull($status);
        $this->assertSame('Study Leave', $status['label']);
        $this->assertSame('info', $status['tone']);
    }

    public function test_a_teacher_with_no_status_recorded_says_nothing(): void
    {
        $teacher = $this->teacherWithStatus('active');
        $teacher->employment_status_id = null;
        $teacher->save();

        $this->assertNull($teacher->fresh()->public_status);
    }

    public function test_every_theme_card_shows_the_label_and_only_when_earned(): void
    {
        $themes = ['theme_default', 'theme_diu', 'theme_modern', 'theme_portrait'];

        foreach (['study-leave' => true, 'active' => false] as $slug => $shouldShow) {
            $teacher = $this->teacherWithStatus($slug);

            foreach ($themes as $theme) {
                $html = view("frontend.themes.{$theme}.partials.teacher_card", [
                    'teacher' => $teacher,
                    'faculty' => $teacher->department->faculty,
                    'department' => $teacher->department,
                ])->render();

                $shouldShow
                    ? $this->assertStringContainsString('Study Leave', $html, "{$theme} card hides the leave label")
                    : $this->assertStringNotContainsString('Study Leave', $html, "{$theme} card labels an active teacher");
            }
        }
    }
}
