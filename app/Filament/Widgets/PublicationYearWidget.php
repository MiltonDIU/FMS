<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PublicationYearWidget extends ChartWidget
{
    protected  ?string $heading = 'Publications by Year';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Publication::query()
            ->select('publication_year', DB::raw('count(*) as count'))
            ->whereNotNull('publication_year')
            ->groupBy('publication_year')
            ->orderBy('publication_year')
            ->pluck('count', 'publication_year')
            ->toArray();

        $currentYear = (int) date('Y');

        $yearsWithData = array_keys($data);
        if (empty($yearsWithData)) {
            $minYear = $currentYear - 4;
            $maxYear = $currentYear;
        } else {
            $minYear = min(min($yearsWithData), $currentYear - 4);
            $maxYear = max(max($yearsWithData), $currentYear);
        }

        $years = [];
        $counts = [];

        for ($year = $minYear; $year <= $maxYear; $year++) {
            $years[] = (string) $year;
            $counts[] = $data[$year] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Publications',
                    'data' => $counts,
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $years,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
