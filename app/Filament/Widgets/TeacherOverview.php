<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherOverview extends Widget
{
    protected  string $view = 'filament.widgets.teacher-overview';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    // Filter properties
    public ?string $facultyFilter = 'all';
    public ?string $departmentFilter = 'all';
    public ?string $genderFilter = 'all';
    public ?string $designationFilter = 'all';
    public ?string $employmentStatusFilter = 'all';
    public ?string $jobTypeFilter = 'all';
    
    // Date Range (Joining Date)
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public ?string $sortBy = 'publications';
    public ?string $sortDirection = 'desc';
    public int $limit = 10;

    public function mount(): void
    {
        // Default to all time (null dates)
    }

    public function updatedFacultyFilter(): void
    {
        // When faculty changes, reset department filter to all
        $this->departmentFilter = 'all';
    }

    public static function canView(): bool
    {
        return auth()->user()->can('View:TeacherOverview');
    }

    protected function getViewData(): array
    {
        // Base query for teachers
        $teachersQuery = Teacher::query()
            ->with(['department', 'designation', 'employmentStatus'])
            ->active(); 

        // Apply filters
        $this->applyFilters($teachersQuery);

        // Get teacher statistics with relationships
        $teacherStats = $teachersQuery
            ->select([
                'teachers.id',
                'teachers.first_name',
                'teachers.middle_name',
                'teachers.last_name',
                'teachers.employee_id',
                'teachers.joining_date',
                'teachers.department_id',
                'teachers.designation_id',
                'teachers.employment_status_id',
                'teachers.is_public',
                'teachers.photo'
            ])
            ->withCount([
                'publications',
                'educations',
                'awards',
                'certifications',
                'trainingExperiences',
                'teachingAreas',
                'skills',
                'memberships'
            ])
            ->when($this->sortBy === 'publications', function ($query) {
                return $query->orderBy('publications_count', $this->sortDirection);
            })
            ->when($this->sortBy === 'awards', function ($query) {
                return $query->orderBy('awards_count', $this->sortDirection);
            })
            ->when($this->sortBy === 'certifications', function ($query) {
                return $query->orderBy('certifications_count', $this->sortDirection);
            })
            ->when($this->sortBy === 'experience', function ($query) {
                return $query->orderBy('joining_date', $this->sortDirection === 'desc' ? 'asc' : 'desc');
            })
            ->limit($this->limit)
            ->get();

        // Calculate summary statistics
        $summary = $this->calculateSummaryStats();

        // Get detailed employment status stats
        $statusStats = $this->getDetailedStatusStats();

        // Get dynamic reported degree stats
        $reportedDegreeStats = $this->getReportedDegreeStats();

        // Get top performers
        $topPublishers = $this->getTopPerformers('publications', 5);
        $topAwardWinners = $this->getTopPerformers('awards', 5);

        return [
            'teacherStats' => $teacherStats,
            'summary' => $summary,
            'statusStats' => $statusStats,
            'reportedDegreeStats' => $reportedDegreeStats,
            'topPublishers' => $topPublishers,
            'topAwardWinners' => $topAwardWinners,
            'faculties' => $this->getFaculties(),
            'departments' => $this->getDepartments(), 
            'genders' => $this->getGenders(),
            'designations' => $this->getDesignations(),
            'employmentStatuses' => $this->getEmploymentStatuses(),
            'jobTypes' => $this->getJobTypes(),
            'sortOptions' => $this->getSortOptions(),
        ];
    }

    protected function applyFilters($query): void
    {
        // Joining Date Filter
        if ($this->fromDate) {
            $query->whereDate('joining_date', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $query->whereDate('joining_date', '<=', $this->toDate);
        }

        // Faculty Filter
        if ($this->facultyFilter !== 'all') {
            $query->whereHas('department', function ($q) {
                $q->where('faculty_id', $this->facultyFilter);
            });
        }

        // Department Filter
        if ($this->departmentFilter !== 'all') {
            $query->where('department_id', $this->departmentFilter);
        }

        // Gender Filter
        if ($this->genderFilter !== 'all') {
            $query->where('gender_id', $this->genderFilter);
        }

        // Designation Filter
        if ($this->designationFilter !== 'all') {
            $query->where('designation_id', $this->designationFilter);
        }

        // Employment Status Filter
        if ($this->employmentStatusFilter !== 'all') {
            $query->where('employment_status_id', $this->employmentStatusFilter);
        }

        // Job Type Filter
        if ($this->jobTypeFilter !== 'all') {
            $query->where('job_type_id', $this->jobTypeFilter);
        }
    }


    protected function calculateSummaryStats(): array
    {
        $query = Teacher::query()->active();
        $this->applyFilters($query); // This modifies $query in place

        $totalTeachers = $query->count();
        $activeTeachers = (clone $query)->where('is_active', true)->count();

        // Helper to count related records efficiently using subquery
        $getRelatedCount = function($relatedTable, $foreignKey = 'teacher_id') use ($query) {
             return DB::table($relatedTable)
                ->whereIn($foreignKey, $query->select('teachers.id'))
                ->when(Schema::hasColumn($relatedTable, 'deleted_at'), function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();
        };

        // Special handling for Publications (Many-to-Many Polymorphic)
        // We count entries in the pivot table 'publication_authors'
        // where authorable_type is Teacher and authorable_id is in our filtered teachers
        $publicationsCount = DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->whereIn('authorable_id', $query->select('teachers.id'))
            ->count();
        
        // Standard HasMany relationships
        $awardsCount = $getRelatedCount('awards');
        $certificationsCount = $getRelatedCount('certifications');
        $trainingCount = $getRelatedCount('training_experiences'); 
        
        // Admin roles are now on User model, not Teacher
        // Count teachers whose users have active administrative roles
        $adminRolesCount = (clone $query)
            ->whereHas('user.administrativeRoles', function($q) {
                $q->where('administrative_role_user.is_active', true)
                  ->whereNull('administrative_role_user.end_date');
            })
            ->count();

        $avgPublications = $totalTeachers > 0 ? round($publicationsCount / $totalTeachers, 1) : 0;
        
        return [
            'total_teachers' => $totalTeachers,
            'active_teachers' => $activeTeachers,
            'total_publications' => $publicationsCount,
            'total_awards' => $awardsCount,
            'total_certifications' => $certificationsCount,
            'total_training' => $trainingCount,
            'total_admin_roles' => $adminRolesCount,
            'avg_publications' => $avgPublications,
            'profile_completion_rate' => 0,
        ];
    }
    
    protected function getReportedDegreeStats(): array
    {
        // 1. Get ALL degree levels that should be reported
        $reportableLevels = DB::table('degree_levels')
            ->where('is_report', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->select('id', 'name')
            ->get();

        // 2. Prepare the base query for education counts
        $baseQuery = DB::table('educations')
            ->join('degree_types', 'educations.degree_type_id', '=', 'degree_types.id')
            ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
            ->whereNull('educations.deleted_at')
            ->where('teachers.is_archived', false);

        // Apply filters to the COUNT query
        if ($this->fromDate) {
            $baseQuery->whereDate('teachers.joining_date', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $baseQuery->whereDate('teachers.joining_date', '<=', $this->toDate);
        }
        if ($this->facultyFilter !== 'all') {
            $baseQuery->join('departments', 'teachers.department_id', '=', 'departments.id')
                  ->where('departments.faculty_id', $this->facultyFilter);
        }
        if ($this->departmentFilter !== 'all') {
            $baseQuery->where('teachers.department_id', $this->departmentFilter);
        }
        if ($this->genderFilter !== 'all') {
            $baseQuery->where('teachers.gender_id', $this->genderFilter);
        }
        if ($this->designationFilter !== 'all') {
            $baseQuery->where('teachers.designation_id', $this->designationFilter);
        }

        // Get counts grouped by degree level
        $counts = $baseQuery
            ->select('degree_types.degree_level_id', DB::raw('COUNT(*) as count'))
            ->groupBy('degree_types.degree_level_id')
            ->pluck('count', 'degree_level_id'); // Key: Level ID, Value: Count

        // 3. Map result to ensure EVERY level is present
        return $reportableLevels->map(function ($level) use ($counts) {
            return [
                'label' => $level->name,
                'value' => $counts->get($level->id, 0), // Default to 0 if not found
            ];
        })->toArray();
    }

    protected function getDetailedStatusStats(): array
    {
        $query = Teacher::query()->active();
        $this->applyFilters($query);

        return $query
            ->join('employment_statuses', 'teachers.employment_status_id', '=', 'employment_statuses.id')
            ->select('employment_statuses.name as status_name', DB::raw('COUNT(*) as count'))
            ->groupBy('employment_statuses.name', 'employment_statuses.id')
            ->pluck('count', 'status_name')
            ->toArray();
    }

    protected function getTopPerformers(string $metric, int $limit): array
    {
        $query = Teacher::query()->active();
        $this->applyFilters($query);
        
        $countColumn = $metric . '_count';
        
        return $query->withCount($metric)
            ->having($countColumn, '>', 0)
            ->orderBy($countColumn, 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($teacher) use ($countColumn) {
                return [
                    'name' => $teacher->full_name,
                    'count' => $teacher->$countColumn,
                    'photo' => $teacher->photo,
                    'rank' => $teacher->designation->name ?? '',
                ];
            })
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
        $query = DB::table('departments')
            ->where('is_active', true);
            
        if ($this->facultyFilter !== 'all') {
            $query->where('faculty_id', $this->facultyFilter);
        }

        return $query->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('All Departments', 'all')
            ->toArray();
    }

    protected function getGenders(): array
    {
        return DB::table('genders')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->prepend('All Genders', 'all')
            ->toArray();
    }

    protected function getDesignations(): array
    {
        return DB::table('designations')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->prepend('All Designations', 'all')
            ->toArray();
    }

    protected function getSortOptions(): array
    {
        return [
            'publications' => 'Publications',
            'awards' => 'Awards',
            'certifications' => 'Certifications',
            'experience' => 'Experience (Joining Date)',
        ];
    }

    protected function getEmploymentStatuses(): array
    {
        return DB::table('employment_statuses')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->prepend('All Statuses', 'all')
            ->toArray();
    }

    protected function getJobTypes(): array
    {
        return DB::table('job_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->prepend('All Job Types', 'all')
            ->toArray();
    }
}
