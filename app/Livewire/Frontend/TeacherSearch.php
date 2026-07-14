<?php

namespace App\Livewire\Frontend;

use App\Models\AdministrativeRole;
use App\Models\Designation;
use App\Models\Faculty;
use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TeacherSearch extends Component
{
    use WithPagination;

    public string $q = '';

    public ?string $facultyId = null;

    public ?string $departmentId = null;

    public ?string $designationId = null;

    public ?string $adminRoleId = null;

    public function mount(?string $selectedFacultyId = null): void
    {
        $this->q = (string) Request::query('q', $this->q);
        $this->facultyId = Request::query('faculty', $selectedFacultyId ?? $this->facultyId);
        $this->departmentId = Request::query('department', $this->departmentId);
        $this->designationId = Request::query('designation', $this->designationId);
        $this->adminRoleId = Request::query('admin', $this->adminRoleId);
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->q = '';
    }

    public function setFaculty($id): void
    {
        $this->facultyId = $this->facultyId == $id ? null : $id;
        $this->departmentId = null;
        $this->resetPage();
    }

    public function setDepartment($id): void
    {
        $this->departmentId = $this->departmentId == $id ? null : $id;
        $this->resetPage();
    }

    public function setDesignation($id): void
    {
        $this->designationId = $this->designationId == $id ? null : $id;
        $this->resetPage();
    }

    public function setAdmin($id): void
    {
        $this->adminRoleId = $this->adminRoleId == $id ? null : $id;
        $this->resetPage();
    }

    public function getFacultiesProperty()
    {
        return Faculty::withCount(['departments', 'teachers'])
            ->orderBy('sort_order')
            ->get();
    }

    public function getSelectedFacultyProperty()
    {
        if (! $this->facultyId) {
            return null;
        }

        return $this->faculties->firstWhere('id', $this->facultyId);
    }

    public function getDepartmentsProperty()
    {
        $faculty = $this->selectedFaculty;

        return $faculty ? $faculty->departments()->orderBy('sort_order')->get() : collect();
    }

    public function getVisibleDesignationsProperty()
    {
        $ids = Teacher::query()
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->when($this->facultyId, fn ($q) => $q->where('departments.faculty_id', $this->facultyId))
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->whereNotNull('teachers.designation_id')
            ->distinct()
            ->pluck('teachers.designation_id');

        return Designation::whereIn('id', $ids)->orderBy('sort_order')->get();
    }

    public function getVisibleAdminRolesProperty()
    {
        $ids = DB::table('administrative_role_user')
            ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->when($this->facultyId, fn ($q) => $q->where('departments.faculty_id', $this->facultyId))
            ->whereNotNull('administrative_role_user.administrative_role_id')
            ->distinct()
            ->pluck('administrative_role_user.administrative_role_id');

        return AdministrativeRole::whereIn('id', $ids)->orderBy('sort_order')->get();
    }

    public function getTeachersProperty()
    {
        $query = Teacher::query()
            ->select('teachers.*')
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->join('faculties', 'faculties.id', '=', 'departments.faculty_id')
            ->leftJoin('designations', 'designations.id', '=', 'teachers.designation_id')
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false);

        $q = trim($this->q);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qb) use ($like) {
                $qb->where('teachers.first_name', 'like', $like)
                    ->orWhere('teachers.middle_name', 'like', $like)
                    ->orWhere('teachers.last_name', 'like', $like)
                    ->orWhere('teachers.secondary_email', 'like', $like)
                    ->orWhere('teachers.employee_id', 'like', $like)
                    ->orWhere('departments.name', 'like', $like)
                    ->orWhere('departments.code', 'like', $like)
                    ->orWhere('faculties.name', 'like', $like)
                    ->orWhere('faculties.short_name', 'like', $like)
                    ->orWhere('designations.name', 'like', $like);
            });
        }

        if ($this->facultyId) {
            $query->where('faculties.id', $this->facultyId);
        }

        if ($this->departmentId) {
            $query->where('departments.id', $this->departmentId);
        }

        if ($this->designationId) {
            $query->where('teachers.designation_id', $this->designationId);
        }

        if ($this->adminRoleId) {
            $adminTeacherIds = DB::table('administrative_role_user')
                ->join('teachers as t2', 't2.user_id', '=', 'administrative_role_user.user_id')
                ->join('departments as d2', 'd2.id', '=', 't2.department_id')
                ->when($this->facultyId, fn ($q) => $q->where('d2.faculty_id', $this->facultyId))
                ->where('administrative_role_user.administrative_role_id', $this->adminRoleId)
                ->pluck('t2.id');
            $query->whereIn('teachers.id', $adminTeacherIds);
        }

        return $query
            ->with(['designation', 'department.faculty'])
            ->orderBy('teachers.first_name')
            ->paginate(12);
    }

    public function render(): View
    {
        return view('frontend.themes.theme_diu.livewire.teacher-search');
    }
}
