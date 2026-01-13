<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class PublicationAuthorStatsWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'publicationAuthorStatsWidget';

    protected static ?string $heading = 'Publications by Author Count';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';
    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationAuthorStatsWidget');
    }

    protected function getOptions(): array
    {
        $data = $this->getData();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Publications',
                    'data' => array_values($data),
                ],
            ],
            'xaxis' => [
                'categories' => array_keys($data),
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#6366f1'],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 4,
                    'horizontal' => false,
                    'distributed' => true,
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
            ],
            'legend' => [
                'show' => false,
            ],
        ];
    }

    protected function getData(): array
    {
        $filterType = $this->filters['filter_type'] ?? 'last_years';
        $facultyId = $this->filters['faculty_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;

        // Ensure department is reset if faculty is cleared
        if (! $facultyId) {
            $departmentId = null;
        }

        $query = Publication::query();

        // Apply faculty/department filters
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        } elseif ($facultyId) {
            $query->whereHas('department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        $currentYear = \Carbon\Carbon::now()->year;

        // Apply date filters
        switch ($filterType) {
            case 'last_years':
                $yearsCount = (int)($this->filters['years_count'] ?? 5);
                $minYear = $currentYear - ($yearsCount - 1);
                $query->whereBetween('publication_year', [$minYear, $currentYear]);
                break;

            case 'date_range':
                $dateStart = $this->filters['date_start'] ?? null;
                $dateEnd = $this->filters['date_end'] ?? null;
                if ($dateStart && $dateEnd) {
                    $query->whereBetween('publication_date', [$dateStart, $dateEnd]);
                }
                break;
        }

        // Fetch aggregation
        // We need to count authors for each publication and group them
        $publications = $query->withCount('teachers')->get();

        $stats = [
            '1 Author' => 0,
            '2 Authors' => 0,
            '3 Authors' => 0,
            '4 Authors' => 0,
            '5 Authors' => 0,
            '6 Authors' => 0,
            '7 Authors' => 0,
            '8 Authors' => 0,
            '9 Authors' => 0,
            '10+ Authors' => 0,
        ];

        foreach ($publications as $pub) {
            $count = $pub->teachers_count;
            if ($count >= 10) {
                $stats['10+ Authors']++;
            } elseif ($count > 0) {
                $countKey = $count . ($count === 1 ? ' Author' : ' Authors');
                if (isset($stats[$countKey])) {
                    $stats[$countKey]++;
                }
            }
        }

        return $stats;
    }

    public function filtersSchema(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([

            Select::make('filter_type')
                ->label('Filter Type')
                ->options([
                    'last_years' => 'Last N Years',
                    'date_range' => 'Custom Date Range',
                ])
                ->default('last_years')
                ->live(),

            Select::make('faculty_id')
                ->label('Faculty')
                ->options(Faculty::where('is_active', true)->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Select Faculty')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),

            Select::make('department_id')
                ->label('Department')
                ->placeholder('Select Department')
                ->options(function (Get $get): array {
                    $facultyId = $get('faculty_id');
                    if (! filled($facultyId)) {
                        return [];
                    }
                    return Department::where('faculty_id', $facultyId)
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->disabled(fn (Get $get): bool => ! filled($get('faculty_id')))
                ->live(),

            TextInput::make('years_count')
                ->label('Number of Years')
                ->numeric()
                ->minValue(1)
                ->maxValue(50)
                ->default(5)
                ->visible(fn (Get $get) => $get('filter_type') === 'last_years'),

            DatePicker::make('date_start')
                ->label('Start Date')
                ->visible(fn (Get $get) => $get('filter_type') === 'date_range'),

            DatePicker::make('date_end')
                ->label('End Date')
                ->visible(fn (Get $get) => $get('filter_type') === 'date_range'),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }
}
