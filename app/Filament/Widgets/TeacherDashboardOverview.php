<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardOverview extends Widget
{
    protected  string $view = 'filament.widgets.teacher-dashboard-overview';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public ?\Illuminate\Database\Eloquent\Model $record = null;

    public static function canView(): bool
    {
        $currentUrl = url()->current();
        // Allow teachers to view their own
        if (Auth::user()?->isTeacher()) {
            return true;
        }
        // Allow admins or users with dashboard permission to view standard dashboard
        //return Auth::user()?->can('View:TeacherDashboardOverview') ?? false;
        return str_starts_with($currentUrl, url('/admin/teacher-dashboard/')) ?? false;
    }

    protected function getViewData(): array
    {
        $teacher = $this->record;

        // If no record passed (e.g. direct widget usage on separate page), fallback to auth user's teacher
        if (! $teacher && Auth::user()?->isTeacher()) {
            $teacher = Auth::user()->teacher;
        }

        if ($teacher) {
            $teacher->loadMissing([
                'department',
                'designation',
                'employmentStatus',
                'jobType',
                'gender',
                'bloodGroup',
                'religion',
                'socialLinks.platform',
                'educations.degreeType.level',
            ])
            ->loadCount([
                'publications',
                'awards',
                'certifications',
                'trainingExperiences',
                'researchProjects', // Research Projects
                'teachingAreas',
                'skills',
                'memberships',
            ]);
        }

        return [
            'teacher' => $teacher,
        ];
    }
}
