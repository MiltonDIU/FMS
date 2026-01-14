<?php

namespace App\Filament\Widgets;

use App\Models\PublicationIncentive;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PublicationIncentiveStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationIncentiveStatsOverview');
    }
    protected function getStats(): array
    {
        $query = PublicationIncentive::query();

        // Apply Table Filters from Request
        $filters = request()->input('tableFilters', []);

        if (!empty($filters['faculty_department']['faculty_id'])) {
            $facultyId = $filters['faculty_department']['faculty_id'];
            $query->whereHas('publication.department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        if (!empty($filters['faculty_department']['department_id'])) {
            $departmentId = $filters['faculty_department']['department_id'];
            $query->whereHas('publication', fn ($q) => $q->where('department_id', $departmentId));
        }

        if (!empty($filters['publication_date']['date_from'])) {
            $date = $filters['publication_date']['date_from'];
            $query->whereHas('publication', fn ($q) => $q->whereDate('publication_date', '>=', $date));
        }

        if (!empty($filters['publication_date']['date_until'])) {
            $date = $filters['publication_date']['date_until'];
            $query->whereHas('publication', fn ($q) => $q->whereDate('publication_date', '<=', $date));
        }

        $paid = (clone $query)->where('status', 'paid')->sum('total_amount');
        $approved = (clone $query)->where('status', 'approved')->sum('total_amount');
        $pending = (clone $query)->where('status', 'pending')->sum('total_amount');

        // Count Logic

        $pubQuery = \App\Models\Publication::query();
        if (!empty($filters['faculty_department']['faculty_id'])) {
            $pubQuery->whereHas('department', fn ($q) => $q->where('faculty_id', $filters['faculty_department']['faculty_id']));
        }
        if (!empty($filters['faculty_department']['department_id'])) {
            $pubQuery->where('department_id', $filters['faculty_department']['department_id']);
        }
        // Date filter on Publication Date
        if (!empty($filters['publication_date']['date_from'])) {
            $pubQuery->whereDate('publication_date', '>=', $filters['publication_date']['date_from']);
        }
        if (!empty($filters['publication_date']['date_until'])) {
            $pubQuery->whereDate('publication_date', '<=', $filters['publication_date']['date_until']);
        }

        $totalPubs = $pubQuery->count();
        $incentivizedPubs = (clone $query)->distinct('publication_id')->count();

        $percentage = $totalPubs > 0 ? round(($incentivizedPubs / $totalPubs) * 100, 1) : 0;

        return [
            Stat::make('Total Paid Amount', number_format($paid, 2))
                ->description('Incentives explicitly marked as paid')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Approved Amount', number_format($approved, 2))
                ->description('Approved but not yet paid')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Total Pending Amount', number_format($pending, 2))
                ->description('Waiting for approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Incentive Coverage', $percentage . '%')
                ->description('Publications with incentives')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('primary'),
        ];
    }
}
