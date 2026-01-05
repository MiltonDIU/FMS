<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use App\Models\Gender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TeacherStatsOverview extends BaseWidget
{
    protected  ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $stats = [];
        $totalTeachers = Teacher::count();

        // Total teachers stat
        $stats[] = Stat::make('Total Teachers', $totalTeachers)
            ->description('All teachers in the system')
            ->descriptionIcon('heroicon-m-user-group')
            ->color('primary')
            ->chart([7, 12, 15, 18, 22, 25, $totalTeachers]);

        // Dynamic gender stats
        $genders = Gender::where('is_active', true)
            ->withCount('teachers')
            ->orderBy('sort_order')
            ->get();

        $colors = ['success', 'danger', 'warning', 'info', 'gray', 'purple'];
        $icons = [
            'heroicon-m-user',
            'heroicon-m-user-group',
            'heroicon-m-users',
            'heroicon-m-identification',
            'heroicon-m-academic-cap',
        ];

        foreach ($genders as $index => $gender) {
            $count = $gender->teachers_count ?? 0; // Null safe
            $percentage = $totalTeachers > 0
                ? round(($count / $totalTeachers) * 100, 1)
                : 0;

            // Generate chart data for visual effect
            $chartData = [];
            for ($i = 0; $i < 7; $i++) {
                $chartData[] = max(0, $count + rand(-3, 3));
            }

            $stats[] = Stat::make($gender->name . ' Teachers', $count)
                ->description($percentage . '% of total')
                ->descriptionIcon($icons[$index % count($icons)])
                ->color($colors[$index % count($colors)])
                ->chart($chartData);
        }

        return $stats;
    }

    // Remove or comment this if you don't have permission set up yet

    public static function canView(): bool
    {
        return auth()->user()->can('View:SystemOverviewWidget');
    }

}
