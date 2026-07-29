<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PublicationIncentives\Pages\ListPublicationIncentives;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PublicationIncentiveStatsOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListPublicationIncentives::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }
        return $user->can('ViewAny:PublicationIncentive') || $user->can('View:PublicationIncentiveStatsOverview');
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery()->reorder();

        $paidQuery = (clone $query)->where('publication_incentives.status', 'paid');
        $approvedQuery = (clone $query)->where('publication_incentives.status', 'approved');
        $pendingQuery = (clone $query)->where('publication_incentives.status', 'pending');

        $paid = (float) $paidQuery->sum('publication_incentives.total_amount');
        $approved = (float) $approvedQuery->sum('publication_incentives.total_amount');
        $pending = (float) $pendingQuery->sum('publication_incentives.total_amount');
        $grandTotal = (float) (clone $query)->sum('publication_incentives.total_amount');

        $paidCount = $paidQuery->count();
        $approvedCount = $approvedQuery->count();
        $pendingCount = $pendingQuery->count();
        $totalCount = (clone $query)->count();

        return [
            Stat::make('Total Paid Amount', '৳' . number_format($paid, 2))
                ->description("{$paidCount} incentives paid")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Approved (Unpaid)', '৳' . number_format($approved, 2))
                ->description("{$approvedCount} approved, awaiting payment")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Pending Approval', '৳' . number_format($pending, 2))
                ->description("{$pendingCount} waiting for approval")
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Total Incentive Claimed', '৳' . number_format($grandTotal, 2))
                ->description("Across {$totalCount} total incentives")
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),
        ];
    }
}
