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
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Carbon\Carbon;

class PublicationYearWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'publicationYearChart';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Publications by Year';

    /**
     * Filter Schema
     */
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationYearWidget');
    }
    public function filtersSchema(Schema $schema): Schema
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
                ->visible(fn (callable $get) => $get('filter_type') === 'last_years'),

            DatePicker::make('date_start')
                ->label('Start Date')
                ->visible(fn (callable $get) => $get('filter_type') === 'date_range'),

            DatePicker::make('date_end')
                ->label('End Date')
                ->visible(fn (callable $get) => $get('filter_type') === 'date_range'),

            Select::make('group_by')
                ->label('Group By')
                ->options([
                    'yearly' => 'Yearly',
                    'monthly' => 'Monthly',
                ])
                ->default('yearly')
                ->live(),

            Toggle::make('show_labels')
                ->label('Show Data Labels')
                ->default(true),
        ]);
    }

    /**
     * Update chart when filter is submitted
     */
    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    /**
     * Chart options
     */
    protected function getOptions(): array
    {
        $data = $this->getData();
        $showLabels = $this->filters['show_labels'] ?? true;
        $title = $this->filters['title'] ?? 'Publications by Year';

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
                'toolbar' => [
                    'show' => true,
                ],
                'animations' => [
                    'enabled' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Publications Count',
                    'data' => $data['counts'],
                ],
            ],
            'xaxis' => [
                'categories' => $data['years'],
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
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
            'colors' => ['#3b82f6'],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 4,
                    'horizontal' => false,
                    'columnWidth' => '55%',
                ],
            ],
            'dataLabels' => [
                'enabled' => $showLabels,
                'style' => [
                    'fontFamily' => 'inherit',
                    'fontSize' => '12px',
                ],
            ],
            'grid' => [
                'borderColor' => '#e5e7eb',
            ],
            'states' => [
                'hover' => [
                    'filter' => [
                        'type' => 'darken',
                        'value' => 0.15,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get publication data with filter logic
     */
    protected function getData(): array
    {
        $filterType = $this->filters['filter_type'] ?? 'last_years';
        $groupBy = $this->filters['group_by'] ?? 'yearly';
        $facultyId = $this->filters['faculty_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;

        $query = Publication::query();

        // Apply faculty/department filters
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        } elseif ($facultyId) {
            $query->whereHas('department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        // Variables to track the start/end bounds for gap filling
        $startBound = null;
        $endBound = null;
        $currentYear = \Carbon\Carbon::now()->year;

        // 1. Apply Filters & Determine Bounds
        switch ($filterType) {
            case 'last_years':
                $yearsCount = (int)($this->filters['years_count'] ?? 5);
                $minYear = $currentYear - ($yearsCount - 1);

                $query->whereBetween('publication_year', [$minYear, $currentYear]);

                $startBound = Carbon::create($minYear, 1, 1);
                $endBound = Carbon::create($currentYear, 12, 31);
                break;

            case 'custom_range':
                $yearFrom = (int) ($this->filters['year_from'] ?? ($currentYear - 4));
                $yearTo = (int) ($this->filters['year_to'] ?? $currentYear);

                // Swap if inverted
                if ($yearFrom > $yearTo) { [$yearFrom, $yearTo] = [$yearTo, $yearFrom]; }

                $query->whereBetween('publication_year', [$yearFrom, $yearTo]);

                $startBound = Carbon::create($yearFrom, 1, 1);
                $endBound = Carbon::create($yearTo, 12, 31);
                break;

            case 'date_range':
                $dateStart = $this->filters['date_start'] ?? null;
                $dateEnd = $this->filters['date_end'] ?? null;

                if ($dateStart && $dateEnd) {
                    $query->whereBetween('publication_date', [$dateStart, $dateEnd]);

                    $startBound = Carbon::parse($dateStart);
                    $endBound = Carbon::parse($dateEnd);
                } else {
                    // Fallback if dates not picked yet
                    $startBound = Carbon::create($currentYear, 1, 1);
                    $endBound = Carbon::create($currentYear, 12, 31);
                }
                break;
        }

        // 2. Grouping & Formatting
        $years = [];
        $counts = [];

        if ($groupBy === 'monthly') {
            // For monthly, we MUST rely on publication_date
            $query->whereNotNull('publication_date');

            $data = $query
                ->selectRaw("DATE_FORMAT(publication_date, '%Y-%m') as period, COUNT(*) as count")
                ->groupBy('period')
                ->pluck('count', 'period')
                ->toArray();

            // Fill Gaps (Monthly)
            // If date range is wide (e.g. 5 years), monthly bars might be too many (60 bars).
            // But we render what is asked.
            $current = $startBound->copy()->startOfMonth();
            $end = $endBound->copy()->endOfMonth();

            while ($current <= $end) {
                $key = $current->format('Y-m');
                // Label format: "Jan 2024"
                $years[] = $current->format('M Y');
                $counts[] = $data[$key] ?? 0;

                $current->addMonth();
            }

        } else {
            // Yearly Grouping
            // We rely on publication_year column which is safer than date column for old data
            // If date_range filter was used, the query already filtered by date.
            // We just group the results by year.

            $data = $query
                ->selectRaw('publication_year, COUNT(*) as count')
                ->groupBy('publication_year')
                ->pluck('count', 'publication_year')
                ->toArray();

            // Fill Gaps (Yearly)
            $minY = $startBound->year;
            $maxY = $endBound->year;

            for ($y = $minY; $y <= $maxY; $y++) {
                $years[] = (string) $y;
                $counts[] = $data[$y] ?? 0;
            }
        }

        return [
            'years' => $years,
            'counts' => $counts,
        ];
    }
}
