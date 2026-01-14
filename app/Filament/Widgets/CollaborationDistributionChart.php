<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CollaborationDistributionChart extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'collaborationDistributionChart';
    protected static ?string $heading = 'Publications by Collaboration';
    protected static ?int $sort = 3;

    public function filters(Schema $schema): Schema
    {
        return $this->filtersSchema($schema);
    }
    public static function canView(): bool
    {
        return auth()->user()->can('View:CollaborationDistributionChart');
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
                    if (! filled($facultyId)) return [];
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
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $data = $this->getData();

        // Map collaboration name (relation) or fallback
        // Since we grouped by research_collaboration_id, we need to load names
        // But get() returns items with fields.
        // We eagerly load 'collaboration' or join.

        $categories = $data->pluck('collaboration_name')->toArray();
        $counts = $data->pluck('total')->toArray();

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
                'categories' => $categories,
            ],
            'colors' => ['#8b5cf6'], // Violet
        ];
    }

    protected function getData(): Collection
    {
        $filterType = $this->filters['filter_type'] ?? 'last_years';
        $facultyId = $this->filters['faculty_id'] ?? null;
        $departmentId = $this->filters['department_id'] ?? null;

        $query = Publication::query()
            ->join('research_collaborations', 'publications.research_collaboration_id', '=', 'research_collaborations.id')
            ->selectRaw('research_collaborations.name as collaboration_name, COUNT(*) as total');

        if ($facultyId) {
            $query->whereHas('department', fn ($d) => $d->where('faculty_id', $facultyId));
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

        return $query->groupBy('research_collaborations.name')
                     ->orderByDesc('total')
                     ->get();
    }
}
