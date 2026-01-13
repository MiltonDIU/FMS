<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class PublicationGrantTypeWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'publicationGrantTypeChart';

    protected static ?string $heading = 'Publication Grant Type Distribution'; // Grant/Funding Source

    protected static ?int $sort = 60;

    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationGrantTypeWidget');
    }

    public function filtersSchema(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([

            \Filament\Forms\Components\Select::make('filter_type')
                ->label('Filter Type')
                ->options([
                    'last_years' => 'Last N Years',
                    'date_range' => 'Custom Date Range',
                ])
                ->default('last_years')
                ->live(),

            \Filament\Forms\Components\Select::make('faculty_id')
                ->label('Faculty')
                ->options(Faculty::where('is_active', true)->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Select Faculty')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),

            \Filament\Forms\Components\Select::make('department_id')
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

            \Filament\Forms\Components\TextInput::make('years_count')
                ->label('Number of Years')
                ->numeric()
                ->minValue(1)
                ->maxValue(50)
                ->default(5)
                ->visible(fn (callable $get) => $get('filter_type') === 'last_years'),

            \Filament\Forms\Components\DatePicker::make('date_start')
                ->label('Start Date')
                ->visible(fn (callable $get) => $get('filter_type') === 'date_range'),

            \Filament\Forms\Components\DatePicker::make('date_end')
                ->label('End Date')
                ->visible(fn (callable $get) => $get('filter_type') === 'date_range'),

            \Filament\Forms\Components\Toggle::make('show_labels')
                ->label('Show Data Labels')
                ->default(true),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $data = $this->getData();
        $showLabels = $this->filters['show_labels'] ?? true;
        $title = $this->filters['title'] ?? 'Grant Type Distribution';

        $labels = [];
        $counts = [];
        $colors = [];

        foreach ($data as $item) {
            $name = $item->grant->name ?? 'Unknown';
            $labels[] = $name;
            $counts[] = $item->count;

            // Color Mapping for correct visual distinction
            $colors[] = match(strtolower(trim($name))) {
                'diu project' => '#0ea5e9', // Sky-500
                'external project' => '#f59e0b', // Amber-500
                'self funded' => '#10b981', // Emerald-500
                'govt. funded', 'government funded' => '#ef4444', // Red-500
                default => '#94a3b8', // Slate-400
            };
        }

        return [
            'chart' => [
                'type' => 'donut', // Using Pie for variation, or Donuts
                'height' => 300,
            ],
            'series' => $counts,
            'labels' => $labels,
            'colors' => $colors,
            'legend' => [
                'labels' => [
                    'fontFamily' => 'inherit',
                ],
                'position' => 'left',
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '60%', // Make it a donut
                        'labels' => [
                            'show' => true,
                            'total' => [
                                'show' => true,
                                'showAlways' => true,
                                'label' => 'Total',
                                'fontFamily' => 'inherit',
                            ],
                        ],
                    ],
                ],
            ],
            'dataLabels' => [
                'enabled' => $showLabels,
                'style' => [
                    'fontFamily' => 'inherit',
                    'fontWeight' => 600,
                ],
            ],
            'tooltip' => [
                'theme' => 'dark',
            ],
            'title' => [
                'text' => $title,
                'align' => 'left',
                'style' => [
                    'fontFamily' => 'inherit',
                    'fontWeight' => 600,
                ],
            ],
        ];
    }

    protected function getData()
    {
        $filterType = $this->filters['filter_type'] ?? 'last_years';
        $facultyId = $this->filters['faculty_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;

        // Ensure department is reset if faculty is cleared
        if (! $facultyId) {
            $departmentId = null;
        }

        $query = Publication::query()
            ->selectRaw('count(*) as count, grant_type_id')
            ->whereNotNull('grant_type_id')
            ->with('grant');

        // Apply faculty/department filters
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        } elseif ($facultyId) {
            $query->whereHas('department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        $currentYear = \Carbon\Carbon::now()->year;

        // Apply filters
        switch ($filterType) {
            case 'last_years':
                $yearsCount = (int)($this->filters['years_count'] ?? 5);
                $minYear = $currentYear - ($yearsCount - 1);
                $query->whereBetween('publication_year', [$minYear, $currentYear]);
                break;

            case 'custom_range':
                $yearFrom = (int) ($this->filters['year_from'] ?? ($currentYear - 4));
                $yearTo = (int) ($this->filters['year_to'] ?? $currentYear);
                if ($yearFrom > $yearTo) { [$yearFrom, $yearTo] = [$yearTo, $yearFrom]; }
                $query->whereBetween('publication_year', [$yearFrom, $yearTo]);
                break;

            case 'date_range':
                $dateStart = $this->filters['date_start'] ?? null;
                $dateEnd = $this->filters['date_end'] ?? null;
                if ($dateStart && $dateEnd) {
                    $query->whereBetween('publication_date', [$dateStart, $dateEnd]);
                }
                break;
        }

        return $query->groupBy('grant_type_id')->get();
    }
}
