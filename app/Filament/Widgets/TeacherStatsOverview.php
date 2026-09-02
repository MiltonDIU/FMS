<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use App\Models\Gender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

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

        // Total profile views stat
        $totalViews = Teacher::where('is_active', true)->sum('views_count');
        $stats[] = Stat::make('Total Profile Views', number_format($totalViews))
            ->description('Across all active profiles')
            ->descriptionIcon('heroicon-m-eye')
            ->color('info');

        return $stats;
    }

    /**
     * Gated on its own permission, not SystemOverviewWidget's.
     *
     * This checked View:SystemOverviewWidget, which is not a permission that
     * exists — so the gate was closed against everyone, super admins included,
     * and the widget was invisible until the check was commented out. That left
     * it ungated instead: the teacher role carries View:Dashboard, so all two
     * thousand of them could read faculty-wide headcounts, the gender split and
     * total profile views from the dashboard.
     *
     * View:TeacherStatsOverview does exist and is held by super_admin. Anyone
     * else who should see it can be granted it from the role screen.
     */
    public static function canView(): bool
    {
        return Auth::user()?->can('View:TeacherStatsOverview') ?? false;
    }
}
