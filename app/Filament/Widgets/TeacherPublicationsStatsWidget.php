<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Illuminate\Support\Facades\Auth;

class TeacherPublicationsStatsWidget extends StatsOverviewWidget
{
    public ?Teacher $record = null;
    protected static ?int $sort = 5;
    /**
     * Visible only to Teachers
     */
    public static function canView(): bool
    {
        // Updated canView logic
        return Auth::user()?->hasRole(['teacher', 'super_admin']) || Auth::user()?->can('view_teacher_dashboard');
    }

    // Added getTeacher method
    protected function getTeacher(): ?Teacher
    {
        if ($this->record) {
            return $this->record;
        }
        return Auth::user()?->teacher;
    }

    protected function getStats(): array
    {
        $teacher = $this->getTeacher(); // Used the new getTeacher method

        if (! $teacher) {
            return [];
        }

        // Corrected the line for totalPubs
        $totalPubs = $teacher->publications()->count();
        $thisYearPubs = $teacher->publications()->whereYear('publication_date', now()->year)->count();
        $latestPub = $teacher->publications()
            ->orderBy('publication_date', 'desc')
            ->first();

        return [
            Stat::make('Total Publications', $totalPubs)
                ->description('All time')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('primary'),

            Stat::make('This Year', $thisYearPubs)
                ->description(now()->year . ' publications')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('Latest Publication', $latestPub ? \Str::limit($latestPub->title, 30) : 'None')
                ->description($latestPub ? $latestPub->publication_year : 'No publications')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),
        ];
    }
}
