<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class PublicationLinkageChart extends ApexChartWidget
{
    protected static ?string $chartId = 'publicationLinkageChart';

    protected static ?string $heading = 'Publication Indexing Status'; // Updated heading based on user's data description

    protected static ?int $sort = 5;
    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationLinkageChart');
    }
    use \Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

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

            \Filament\Forms\Components\TextInput::make('year_from')
                ->label('From Year')
                ->numeric()
                ->minValue(1900)
                ->maxValue(\Carbon\Carbon::now()->year)
                ->visible(fn (callable $get) => $get('filter_type') === 'custom_range'),

            \Filament\Forms\Components\TextInput::make('year_to')
                ->label('To Year')
                ->numeric()
                ->minValue(1900)
                ->maxValue(\Carbon\Carbon::now()->year)
                ->visible(fn (callable $get) => $get('filter_type') === 'custom_range'),

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
        // Group publications by linkage (scopus, wos, etc) and count

        $labels = [];
        $counts = [];
        $colors = [];

        foreach ($data as $item) {
            $name = $item->linkage->name ?? 'Unknown';
            $labels[] = $name;
            $counts[] = $item->count;

            // Suggested Color Mapping based on generic branding/conventions
            $colors[] = match(strtolower(trim($name))) {
                'scopus' => '#ea580c', // Orange-600
                'web of science' => '#0891b2', // Cyan-600
                'scopus and wos', 'scopus & wos' => '#7c3aed', // Violet-600 (Mix)
                'ugc listed' => '#16a34a', // Green-600
                'not indexed' => '#94a3b8', // Slate-400
                default => '#64748b', // Slate-500
            };
        }

        return [
            'chart' => [
                'type' => 'donut',
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
                        'size' => '60%',
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
            'stroke' => [
                'show' => false,
            ],
            'tooltip' => [
                'theme' => 'dark',
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
            ->selectRaw('count(*) as count, publication_linkage_id')
            ->whereNotNull('publication_linkage_id')
            ->with('linkage');

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

        return $query->groupBy('publication_linkage_id')->get();
    }
}
