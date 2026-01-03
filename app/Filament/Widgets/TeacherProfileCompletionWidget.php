<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Illuminate\Support\Facades\Auth;

class TeacherProfileCompletionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    public ?Teacher $record = null;

    protected function getTeacher(): ?Teacher
    {
        if ($this->record) {
            return $this->record;
        }
        return Auth::user()?->teacher;
    }

    /**
     * Visible only to Teachers
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['teacher', 'super_admin']) || Auth::user()?->can('view_teacher_dashboard');
    }

    protected function getStats(): array
    {
        $teacher = $this->getTeacher();

        if (! $teacher) {
            return [];
        }

        // Calculate profile completion percentage
        $requiredFields = [
            'first_name', 'last_name', 'phone', 'department_id',
            'designation_id', 'employee_id', 'joining_date', 'bio'
        ];

        $completed = 0;
        foreach ($requiredFields as $field) {
            if (!empty($teacher->$field)) {
                $completed++;
            }
        }

        $percentage = round(($completed / count($requiredFields)) * 100);
        $color = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');

        return [
            Stat::make('Profile Completion', $percentage . '%')
                ->description($completed . ' of ' . count($requiredFields) . ' required fields complete')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($color)
                ->chart([$percentage, 100 - $percentage]),
        ];
    }
}
