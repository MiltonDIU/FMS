<?php

namespace App\Livewire\Frontend;

use App\Models\AdministrativeRole;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\UserAdministrativeRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DepartmentSearch extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $q = '';

    #[Url(as: 'designation')]
    public ?string $designationId = null;

    #[Url(as: 'admin')]
    public ?string $adminRoleId = null;

    #[Url(as: 'all')]
    public bool $all = false;

    public ?int $departmentId = null;

    public ?Department $department = null;

    public function mount(?int $departmentId = null): void
    {
        $this->departmentId = $departmentId;
        $this->department = $departmentId ? Department::find($departmentId) : null;
        $this->all = Request::query('all', false) ? true : false;
        $this->designationId = Request::query('designation', $this->designationId);
        $this->adminRoleId = Request::query('admin', $this->adminRoleId);
    }

    public function getTotalMembersProperty(): int
    {
        if (! $this->department) {
            return 0;
        }

        $dept = $this->department;
        $deptTeacherIds = Teacher::whereHas('departments', fn ($q) => $q->whereNull('department_teacher.deleted_at')->where('department_teacher.department_id', $dept->id))->pluck('id');

        return Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->where(function ($q) use ($dept, $deptTeacherIds) {
                $q->where('teachers.department_id', $dept->id)
                    ->orWhereIn('teachers.id', $deptTeacherIds);
            })
            ->count();
    }

    public function toggleAll(): void
    {
        $this->all = ! $this->all;
        $this->resetPage();
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->q = '';
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

    public function getVisibleDesignationsProperty()
    {
        $deptId = (! $this->all && $this->departmentId) ? (int) $this->departmentId : null;
        $facId = $deptId ? (int) $this->department->faculty_id : null;
        $assignedIds = $deptId
            ? Teacher::whereHas('departments', fn ($q) => $q->whereNull('department_teacher.deleted_at')->where('department_teacher.department_id', $deptId))->pluck('id')->all()
            : [];

        $ids = Teacher::query()
            ->when($deptId, fn ($q) => $q->where(fn ($q2) => $q2->where('teachers.department_id', $deptId)->orWhereIn('teachers.id', $assignedIds)))
            ->when($facId, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereIn('teachers.department_id', fn ($sub) => $sub->select('id')->from('departments')->where('faculty_id', $facId))
                ->orWhereIn('teachers.id', fn ($sub) => $sub->select('teacher_id')->from('department_teacher')
                    ->whereNull('department_teacher.deleted_at')
                    ->whereIn('department_id', fn ($s2) => $s2->select('id')->from('departments')->where('faculty_id', $facId)))))
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->whereNotNull('teachers.designation_id')
            ->distinct()
            ->pluck('teachers.designation_id');

        return Designation::whereIn('id', $ids)->orderBy('sort_order')->get();
    }

    public function getVisibleAdminRolesProperty()
    {
        $deptId = (! $this->all && $this->departmentId) ? (int) $this->departmentId : null;
        $facId = $deptId ? (int) $this->department->faculty_id : null;
        $assignedIds = $deptId
            ? Teacher::whereHas('departments', fn ($q) => $q->whereNull('department_teacher.deleted_at')->where('department_teacher.department_id', $deptId))->pluck('id')->all()
            : [];

        $ids = UserAdministrativeRole::query()
            ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
            ->when($deptId, fn ($q) => $q->where(fn ($q2) => $q2->where('teachers.department_id', $deptId)->orWhereIn('teachers.id', $assignedIds)))
            ->when($facId, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereIn('teachers.department_id', fn ($sub) => $sub->select('id')->from('departments')->where('faculty_id', $facId))
                ->orWhereIn('teachers.id', fn ($sub) => $sub->select('teacher_id')->from('department_teacher')
                    ->whereNull('department_teacher.deleted_at')
                    ->whereIn('department_id', fn ($s2) => $s2->select('id')->from('departments')->where('faculty_id', $facId)))))
            ->whereNotNull('administrative_role_user.administrative_role_id')
            ->distinct()
            ->pluck('administrative_role_user.administrative_role_id');

        return AdministrativeRole::whereIn('id', $ids)->orderBy('sort_order')->get();
    }

    private function getBaseTeachersQuery()
    {
        $deptId = (! $this->all && $this->departmentId) ? (int) $this->departmentId : null;
        $facId = $deptId ? (int) Department::where('id', $this->departmentId)->value('faculty_id') : null;

        $scopeParts = [];
        if ($deptId) {
            $scopeParts[] = 'aru.department_id = ' . $deptId;
        }
        if ($facId) {
            $facCond = 'aru.faculty_id = ' . $facId;
            if ($deptId) {
                $facCond = '(aru.department_id IS NULL AND ' . $facCond . ')';
            }
            $scopeParts[] = $facCond;
        }
        $adminScope = $scopeParts ? '(' . implode(' OR ', $scopeParts) . ')' : '1=1';

        $assignedIds = $deptId
            ? Teacher::whereHas('departments', fn ($q) => $q->whereNull('department_teacher.deleted_at')->where('department_teacher.department_id', $this->departmentId))->pluck('id')
            : collect();

        $query = Teacher::query()
            ->select('teachers.*')
            ->selectRaw("EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.deleted_at IS NULL) as has_admin_role")
            ->selectRaw("(SELECT MIN(admin_roles.sort_order) FROM administrative_role_user aru JOIN administrative_roles admin_roles ON admin_roles.id = aru.administrative_role_id WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.deleted_at IS NULL) as admin_role_sort")
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->leftJoin('designations', 'designations.id', '=', 'teachers.designation_id');

        if ($deptId) {
            $query->where(fn ($q) => $q
                ->where('teachers.department_id', $this->departmentId)
                ->orWhereIn('teachers.id', $assignedIds)
                ->orWhereHas('administrativeRoles', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('administrative_role_user.department_id', $this->departmentId)
                    ->orWhere(fn ($q4) => $q4->whereNull('administrative_role_user.department_id')->where('administrative_role_user.faculty_id', $facId))
                )));
        }

        $query->where('teachers.is_active', true)
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
                    ->orWhere('designations.name', 'like', $like);
            });
        }

        if ($this->designationId) {
            $query->where('teachers.designation_id', $this->designationId);
        }

        if ($this->adminRoleId) {
            $adminTeacherIds = UserAdministrativeRole::query()
                ->join('teachers as t2', 't2.user_id', '=', 'administrative_role_user.user_id')
                ->join('department_teacher as dt', 'dt.teacher_id', '=', 't2.id')
                ->whereNull('dt.deleted_at')
                ->where('dt.department_id', $this->departmentId)
                ->where('administrative_role_user.administrative_role_id', $this->adminRoleId)
                ->distinct()
                ->pluck('t2.id');
            $query->whereIn('teachers.id', $adminTeacherIds);
        }

        return [$query, $adminScope];
    }

    public function getAdminTeachersProperty()
    {
        [$query, $adminScope] = $this->getBaseTeachersQuery();

        return $query
            ->whereRaw("EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.deleted_at IS NULL)")
            ->with(['designation', 'department.faculty', 'teachingAreas', 'administrativeRoles'])
            ->orderBy('admin_role_sort')
            ->orderBy('designations.sort_order')
            ->orderBy('teachers.sort_order')
            ->orderBy('teachers.first_name')
            ->get();
    }

    public function getTeachersProperty()
    {
        [$query, $adminScope] = $this->getBaseTeachersQuery();

        return $query
            ->whereRaw("NOT EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.deleted_at IS NULL)")
            ->with(['designation', 'department.faculty', 'teachingAreas', 'administrativeRoles'])
            ->orderBy('designations.sort_order')
            ->orderBy('teachers.sort_order')
            ->orderBy('teachers.first_name')
            ->paginate(12);
    }

    public function render(): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');
        $view = "frontend.themes.{$activeTheme}.livewire.department-search";

        if (! view()->exists($view)) {
            $view = 'frontend.themes.theme_diu.livewire.department-search';
        }

        return view($view);
    }
}
