<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeacherProfessionalInfoWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public ?Teacher $record = null;

    protected function getTeacher(): ?Teacher
    {
        if ($this->record) {
            return $this->record;
        }
        return Auth::user()?->teacher;
    }

    public static function canView(): bool
    {
        return Auth::user()?->can('View:TeacherProfessionalInfoWidget');
    }

    protected function getStats(): array
    {
        // Don't show if simple user
        if (! Auth::user()?->isTeacher() && ! $this->record) {
            return [];
        }

        $teacher = $this->getTeacher();

        if (! $teacher) {
            return [];
        }

        $joiningDate = $teacher->joining_date ? $teacher->joining_date->format('M d, Y') : 'N/A';

        $yearsRaw = $teacher->joining_date ? $teacher->joining_date->diffInDays(now()) / 365 : 0;
        $serviceYears = $teacher->joining_date ? number_format($yearsRaw, 1) . ' Years' : 'N/A';

        return [
            Stat::make('Department', $teacher->department?->name ?? 'N/A')
                ->description('My Department')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Designation', $teacher->designation?->name ?? 'N/A')
                ->description('Current Position')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Service Length', $serviceYears)
                ->description('Joined: ' . $joiningDate)
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
        ];
    }
}
