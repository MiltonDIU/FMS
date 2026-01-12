<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\Log;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PublicationTypeChart extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'publicationTypeChart';
    protected static ?string $heading = 'Publications by Type';
    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationTypeChart');
    }

    public function filters(Schema $schema): Schema
    {
        return $this->filtersSchema($schema);
    }

    /**
     * Filament v4 filter schema definition
     * Ekhane amra Schema object return korbo
     */
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
                ->options(
                    Faculty::where('is_active', true)->pluck('name', 'id')
                )
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
                ->visible(fn (Get $get) => $get('filter_type') === 'last_years')
                ->live(),

            DatePicker::make('date_start')
                ->label('Start Date')
                ->visible(fn (Get $get) => $get('filter_type') === 'date_range')
                ->live(),

            DatePicker::make('date_end')
                ->label('End Date')
                ->visible(fn (Get $get) => $get('filter_type') === 'date_range')
                ->live(),

            Toggle::make('show_labels')
                ->label('Show Data Labels')
                ->default(true)
                ->live(),
        ]);
    }

    /**
     * Update chart when filter is submitted
     */
    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $data = $this->getData();
        $showLabels = $this->filters['show_labels'] ?? true;

        $labels = $data->isEmpty() ? ['No Data'] : $data->map(fn($item) => $item->type->name ?? 'Unknown')->toArray();
        $counts = $data->isEmpty() ? [0] : $data->pluck('count')->toArray();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Publications',
                    'data' => $counts,
                ],
            ],
            'xaxis' => [
                'categories' => $labels,
            ],
            'dataLabels' => [
                'enabled' => $showLabels,
            ],
            'colors' => ['#3b82f6'],
        ];
    }

    protected function getData(): Collection
    {
        $filterType = $this->filters['filter_type'] ?? 'last_years';
        $facultyId = $this->filters['faculty_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;

        $query = Publication::query()
            ->with('type')
            ->selectRaw('publication_type_id, COUNT(*) as count')
            ->whereNotNull('publication_type_id');

        if ($facultyId) {
            $query->whereHas('department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($filterType === 'last_years') {
            $yearsCount = (int)($this->filters['years_count'] ?? 5);
            $query->where('publication_year', '>=', Carbon::now()->year - ($yearsCount - 1));
        } elseif ($filterType === 'date_range') {
            $dateStart = $this->filters['date_start'] ?? null;
            $dateEnd = $this->filters['date_end'] ?? null;
            if ($dateStart && $dateEnd) {
                $query->whereBetween('publication_date', [$dateStart, $dateEnd]);
            }
        }

        return $query->groupBy('publication_type_id')->get();
    }
}
