<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\PublicationType;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;
use App\Models\GrantType;
use App\Models\ResearchCollaboration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;

class PublicationStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    // Filter properties
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $facultyId = null;
    public ?int $departmentId = null;
    public ?int $publicationTypeId = null;
    public ?int $publicationLinkageId = null;
    public ?int $publicationQuartileId = null;
    public ?int $grantTypeId = null;
    public ?int $researchCollaborationId = null;

    protected function getStats(): array
    {
        $query = $this->getFilteredQuery();

        $totalPublications = $query->count();
        $featuredPublications = (clone $query)->where('is_featured', true)->count();
        $withStudents = (clone $query)->where('student_involvement', true)->count();

        $avgImpactFactor = (clone $query)->whereNotNull('impact_factor')->avg('impact_factor');
        $avgCitescore = (clone $query)->whereNotNull('citescore')->avg('citescore');
        $avgHIndex = (clone $query)->whereNotNull('h_index')->avg('h_index');

        return [
            Stat::make('Total Publications', $totalPublications)
                ->description($this->getDateRangeDescription())
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart($this->getMonthlyTrend()),

            Stat::make('Featured Publications', $featuredPublications)
                ->description(round(($featuredPublications / max($totalPublications, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('With Student Involvement', $withStudents)
                ->description(round(($withStudents / max($totalPublications, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Avg Impact Factor', $avgImpactFactor ? round($avgImpactFactor, 2) : 'N/A')
                ->description('Average across selected')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),

            Stat::make('Avg CiteScore', $avgCitescore ? round($avgCitescore, 2) : 'N/A')
                ->description('Citation metrics average')
                ->descriptionIcon('heroicon-m-bookmark')
                ->color('primary'),

            Stat::make('Avg H-Index', $avgHIndex ? round($avgHIndex, 2) : 'N/A')
                ->description('H-Index average')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('danger'),
        ];
    }

    protected function getFilteredQuery()
    {
        $query = Publication::query();

        // Date range filter
        if ($this->startDate) {
            $query->where('publication_date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->where('publication_date', '<=', $this->endDate);
        }

        // Faculty filter
        if ($this->facultyId) {
            $query->where('faculty_id', $this->facultyId);
        }

        // Department filter
        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }

        // Publication Type filter
        if ($this->publicationTypeId) {
            $query->where('publication_type_id', $this->publicationTypeId);
        }

        // Publication Linkage filter
        if ($this->publicationLinkageId) {
            $query->where('publication_linkage_id', $this->publicationLinkageId);
        }

        // Publication Quartile filter
        if ($this->publicationQuartileId) {
            $query->where('publication_quartile_id', $this->publicationQuartileId);
        }

        // Grant Type filter
        if ($this->grantTypeId) {
            $query->where('grant_type_id', $this->grantTypeId);
        }

        // Research Collaboration filter
        if ($this->researchCollaborationId) {
            $query->where('research_collaboration_id', $this->researchCollaborationId);
        }

        return $query;
    }

    protected function getMonthlyTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = $this->getFilteredQuery()
                ->whereYear('publication_date', $month->year)
                ->whereMonth('publication_date', $month->month)
                ->count();
            $data[] = $count;
        }
        return $data;
    }

    protected function getDateRangeDescription(): string
    {
        if ($this->startDate && $this->endDate) {
            return 'From ' . \Carbon\Carbon::parse($this->startDate)->format('M d, Y') .
                ' to ' . \Carbon\Carbon::parse($this->endDate)->format('M d, Y');
        } elseif ($this->startDate) {
            return 'From ' . \Carbon\Carbon::parse($this->startDate)->format('M d, Y');
        } elseif ($this->endDate) {
            return 'Until ' . \Carbon\Carbon::parse($this->endDate)->format('M d, Y');
        }
        return 'All time publications';
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Filters')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            DatePicker::make('startDate')
                                ->label('Start Date')
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),

                            DatePicker::make('endDate')
                                ->label('End Date')
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),

                            Select::make('facultyId')
                                ->label('Faculty')
                                ->options(Faculty::pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(function ($state) {
                                    $this->departmentId = null; // Reset department when faculty changes
                                    $this->updateChartData();
                                }),

                            Select::make('departmentId')
                                ->label('Department')
                                ->options(function () {
                                    if ($this->facultyId) {
                                        return Department::where('faculty_id', $this->facultyId)
                                            ->pluck('name', 'id');
                                    }
                                    return Department::pluck('name', 'id');
                                })
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),
                        ]),

                    Grid::make(4)
                        ->schema([
                            Select::make('publicationTypeId')
                                ->label('Publication Type')
                                ->options(PublicationType::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),

                            Select::make('publicationLinkageId')
                                ->label('Publication Linkage')
                                ->options(PublicationLinkage::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),

                            Select::make('publicationQuartileId')
                                ->label('Quartile')
                                ->options(PublicationQuartile::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),

                            Select::make('grantTypeId')
                                ->label('Grant Type')
                                ->options(GrantType::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),
                        ]),

                    \Filament\Schemas\Components\Grid::make(4)
                        ->schema([
                            Select::make('researchCollaborationId')
                                ->label('Research Collaboration')
                                ->options(ResearchCollaboration::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(fn () => $this->updateChartData()),
                        ]),
                ])
                ->collapsible()
                ->collapsed(false),
        ];
    }

    public function updateChartData(): void
    {
        // This triggers the widget to refresh
        $this->dispatch('updateChartData');
    }

    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationStatsOverview');
    }
}
