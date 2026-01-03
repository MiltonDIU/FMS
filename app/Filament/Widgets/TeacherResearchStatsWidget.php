<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Illuminate\Support\Facades\Auth;

class TeacherResearchStatsWidget extends StatsOverviewWidget
{
    public ?Teacher $record = null;
    protected static ?int $sort = 3;
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
        $teacher = $this->getTeacher(); // Updated to use getTeacher method

        if (! $teacher) {
            return [];
        }

        $activeProjects = $teacher->researchProjects()->where('status', 'active')->count();
        $totalFunding = $teacher->researchProjects()->sum('budget');
        $latestProject = $teacher->researchProjects()
            ->orderBy('start_date', 'desc')
            ->first();

        return [
            Stat::make('Active Projects', $activeProjects)
                ->description('Currently ongoing')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('warning'),

            Stat::make('Total Funding', number_format($totalFunding, 0))
                ->description('BDT secured')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Latest Project', $latestProject ? \Str::limit($latestProject->title, 30) : 'None')
                ->description($latestProject ? $latestProject->status : 'No projects')
                ->descriptionIcon('heroicon-m-light-bulb')
                ->color('info'),
        ];
    }
}
