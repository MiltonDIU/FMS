<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use App\Models\PublicationType;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicationOverview extends Widget
{
    protected  string $view = 'filament.widgets.publication-overview';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    // Filters
    public ?string $facultyFilter = 'all';
    public ?string $departmentFilter = 'all';
    public ?string $typeFilter = 'all';
    public ?string $yearFilter = 'all';
    public ?string $grantFilter = 'all';
    public ?string $linkageFilter = 'all';
    public ?string $quartileFilter = 'all';
    public ?string $collaborationFilter = 'all';

    public ?string $fromDate = null;
    public ?string $toDate = null;

    public ?string $sortBy = 'date';
    public ?string $sortDirection = 'desc';
    public int $limit = 10;

    public function mount(): void
    {
    }

    public function updatedFacultyFilter(): void
    {
        $this->departmentFilter = 'all';
    }

    public static function canView(): bool
    {
        return auth()->user()->can('View:PublicationOverview');
    }

    protected function getViewData(): array
    {
        // Base Query
        $query = Publication::query()
            ->with(['department', 'faculty', 'type', 'quartile'])
            ->when(true, function ($q) {
                // Listing all publications by default
            });

        $this->applyFilters($query);

        // Fetch Data for List
        $publications = $query->clone()
            ->when($this->sortBy === 'date', fn($q) => $q->orderBy('publication_date', $this->sortDirection))
            ->when($this->sortBy === 'impact_factor', fn($q) => $q->orderBy('impact_factor', $this->sortDirection))
            ->when($this->sortBy === 'citescore', fn($q) => $q->orderBy('citescore', $this->sortDirection))
            ->limit($this->limit)
            ->get();

        // Stats
        $summary = $this->calculateSummaryStats();
        $typeStats = $this->getTypeStats();
        $topJournals = $this->getTopJournals();

        return [
            'publications' => $publications,
            'summary' => $summary,
            'typeStats' => $typeStats,
            'topJournals' => $topJournals,
            'faculties' => $this->getFaculties(),
            'departments' => $this->getDepartments(),
            'types' => $this->getTypes(),
            'years' => $this->getYears(),
            'grants' => $this->getGrants(),
            'linkages' => $this->getLinkages(),
            'quartiles' => $this->getQuartiles(),
            'collaborations' => $this->getCollaborations(),
            'sortOptions' => $this->getSortOptions(),
        ];
    }

    protected function applyFilters($query): void
    {
        if ($this->fromDate) {
            $query->whereDate('publication_date', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $query->whereDate('publication_date', '<=', $this->toDate);
        }

        if ($this->facultyFilter !== 'all') {
            $query->whereHas('department', function ($q) {
                $q->where('faculty_id', $this->facultyFilter);
            });
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('publication_type_id', $this->typeFilter);
        }

        if ($this->yearFilter !== 'all') {
            $query->where('publication_year', $this->yearFilter);
        }

        if ($this->grantFilter !== 'all') {
            $query->where('grant_type_id', $this->grantFilter);
        }

        if ($this->linkageFilter !== 'all') {
            $query->where('publication_linkage_id', $this->linkageFilter);
        }

        if ($this->quartileFilter !== 'all') {
            $query->where('publication_quartile_id', $this->quartileFilter);
        }

        if ($this->collaborationFilter !== 'all') {
            $query->where('research_collaboration_id', $this->collaborationFilter);
        }
    }

    protected function calculateSummaryStats(): array
    {
        $query = Publication::query();
        $this->applyFilters($query);

        $count = $query->count();
        $avgImpact = $query->avg('impact_factor') ?? 0;
        $avgCites = $query->avg('citescore') ?? 0;
        $featured = $query->where('is_featured', true)->count();
        $studentInv = $query->where('student_involvement', true)->count();

        return [
            'total_publications' => $count,
            'avg_impact_factor' => number_format($avgImpact, 2),
            'avg_citescore' => number_format($avgCites, 2),
            'total_featured' => $featured,
            'student_involvement' => $studentInv,
        ];
    }

    protected function getTypeStats(): array
    {
        $types = PublicationType::where('is_active', true)->orderBy('sort_order')->get();

        $query = Publication::query();
        $this->applyFilters($query);
        $counts = $query->select('publication_type_id', DB::raw('count(*) as count'))
            ->groupBy('publication_type_id')
            ->pluck('count', 'publication_type_id');

        return $types->map(function ($type) use ($counts) {
            return [
                'label' => $type->name,
                'value' => $counts->get($type->id, 0),
            ];
        })->toArray();
    }

    protected function getTopJournals(): array
    {
        $query = Publication::query();
        $this->applyFilters($query);

        return $query->select('journal_name', DB::raw('count(*) as count'))
            ->whereNotNull('journal_name')
            ->groupBy('journal_name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->toArray();
    }

    protected function getFaculties(): array
    {
        return DB::table('faculties')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Faculties', 'all')
            ->toArray();
    }

    protected function getDepartments(): array
    {
        $query = DB::table('departments')->where('is_active', true);
        if ($this->facultyFilter !== 'all') {
            $query->where('faculty_id', $this->facultyFilter);
        }
        return $query->orderBy('name')->pluck('name', 'id')->prepend('All Departments', 'all')->toArray();
    }

    protected function getTypes(): array
    {
        return PublicationType::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->prepend('All Types', 'all')
            ->toArray();
    }

    protected function getYears(): array
    {
        return Publication::select('publication_year')
            ->distinct()
            ->orderByDesc('publication_year')
            ->pluck('publication_year', 'publication_year')
            ->prepend('All Years', 'all')
            ->toArray();
    }

    protected function getGrants(): array
    {
        return DB::table('grant_types')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Grants', 'all')
            ->toArray();
    }

    protected function getLinkages(): array
    {
        return DB::table('publication_linkages')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Linkages', 'all')
            ->toArray();
    }

    protected function getQuartiles(): array
    {
        return DB::table('publication_quartiles')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Quartiles', 'all')
            ->toArray();
    }

    protected function getCollaborations(): array
    {
        return DB::table('research_collaborations')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Collaborations', 'all')
            ->toArray();
    }

    protected function getSortOptions(): array
    {
        return [
            'date' => 'Publication Date',
            'impact_factor' => 'Impact Factor',
            'citescore' => 'CiteScore',
        ];
    }
}
