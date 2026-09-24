<?php

namespace App\Livewire\Frontend;

use App\Helpers\Theme;
use App\Models\AdministrativeRole;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Teacher;
use App\Models\UserAdministrativeRole;
use App\Services\DepartmentContacts;
use App\Support\TeacherDirectoryOrder;
use App\Support\TeacherSearchTerm;
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

    /**
     * Which face of the department is showing: its people, or its office
     * contacts. Both are real URLs (department.show / department.contact) rather
     * than component state, so the two views can be linked and the back button
     * behaves. Only themes that render a switch ever pass anything but the
     * default.
     */
    public string $view = 'teachers';

    /**
     * Teachers assigned to this department through the department_teacher pivot,
     * as opposed to those whose home department it is.
     *
     * Memoised because five different code paths need it on a single render —
     * the member count, the designation filter, the role filter and both halves
     * of the teacher list — and it was running as five separate queries, the
     * most expensive repeated statement on the page. Protected, so Livewire does
     * not carry it between requests: it is a per-render cache, not state.
     *
     * @var array<int, int>|null
     */
    protected ?array $assignedTeacherIds = null;

    /** @return array<int, int> */
    protected function assignedTeacherIds(): array
    {
        if ($this->assignedTeacherIds !== null) {
            return $this->assignedTeacherIds;
        }

        if (! $this->departmentId) {
            return $this->assignedTeacherIds = [];
        }

        return $this->assignedTeacherIds = DB::table('department_teacher')
            ->whereNull('deleted_at')
            ->where('department_id', $this->departmentId)
            ->pluck('teacher_id')
            ->all();
    }

    public function mount(?int $departmentId = null, string $view = 'teachers'): void
    {
        $this->departmentId = $departmentId;
        $this->department = $departmentId ? Department::find($departmentId) : null;
        $this->view = $view === 'contact' ? 'contact' : 'teachers';
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
        $assignedIds = $this->assignedTeacherIds();

        return Teacher::published()
            ->where(function ($q) use ($dept, $assignedIds) {
                $q->where('teachers.department_id', $dept->id)
                    ->orWhereIn('teachers.id', $assignedIds);
            })
            ->count();
    }

    /**
     * The Dean / Head / office contacts for the department being viewed.
     *
     * Only themes whose department view actually renders this ever pay for it —
     * a computed property is not evaluated until something asks. The service
     * caches, so the repeated renders Livewire does while someone types in the
     * search box cost nothing.
     */
    public function getContactsProperty(): array
    {
        if (! $this->department || $this->view !== 'contact') {
            return ['sections' => [], 'error' => null];
        }

        return DepartmentContacts::for($this->department);
    }

    /**
     * The sidebar's faculty and department navigation.
     *
     * These were queried from inside the Blade view, which meant every theme
     * repeated the same two statements and re-ran them on each Livewire render.
     * As computed properties they resolve once per request and the four themes
     * share one definition.
     */
    public function getFacultyListProperty()
    {
        return \App\Models\Faculty::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getDepartmentListProperty()
    {
        $faculty = $this->department?->faculty;

        if (! $faculty) {
            return collect();
        }

        return $faculty->departments()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
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
        $assignedIds = $deptId ? $this->assignedTeacherIds() : [];

        $ids = Teacher::query()
            ->when($deptId, fn ($q) => $q->where(fn ($q2) => $q2->where('teachers.department_id', $deptId)->orWhereIn('teachers.id', $assignedIds)))
            ->when($facId, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereIn('teachers.department_id', fn ($sub) => $sub->select('id')->from('departments')->where('faculty_id', $facId))
                ->orWhereIn('teachers.id', fn ($sub) => $sub->select('teacher_id')->from('department_teacher')
                    ->whereNull('department_teacher.deleted_at')
                    ->whereIn('department_id', fn ($s2) => $s2->select('id')->from('departments')->where('faculty_id', $facId)))))
            ->published()
            ->whereNotNull('teachers.designation_id')
            ->distinct()
            ->pluck('teachers.designation_id');

        // Ranks only. "Adjunct Faculty" is not a grade, and the teachers under
        // it are shown by their job type, so a chip carrying its name would
        // filter to a group whose cards all say something else.
        return Designation::ranks()->whereIn('id', $ids)->orderBy('sort_order')->get();
    }

    public function getVisibleAdminRolesProperty()
    {
        $deptId = (! $this->all && $this->departmentId) ? (int) $this->departmentId : null;
        $facId = $deptId ? (int) $this->department->faculty_id : null;
        $assignedIds = $deptId ? $this->assignedTeacherIds() : [];

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

        $assignedIds = $deptId ? $this->assignedTeacherIds() : [];

        $query = Teacher::query()
            ->select('teachers.*')
            ->selectRaw("EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.is_active = 1 AND aru.deleted_at IS NULL) as has_admin_role")
            ->selectRaw("(SELECT MIN(admin_roles.sort_order) FROM administrative_role_user aru JOIN administrative_roles admin_roles ON admin_roles.id = aru.administrative_role_id WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.is_active = 1 AND aru.deleted_at IS NULL) as admin_role_sort")
            ->selectRaw("(SELECT MIN(aru.sort_order) FROM administrative_role_user aru JOIN administrative_roles admin_roles ON admin_roles.id = aru.administrative_role_id WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.is_active = 1 AND aru.deleted_at IS NULL AND admin_roles.sort_order = (SELECT MIN(ar2.sort_order) FROM administrative_role_user aru2 JOIN administrative_roles ar2 ON ar2.id = aru2.administrative_role_id WHERE aru2.user_id = teachers.user_id AND ({$adminScope}) AND aru2.is_active = 1 AND aru2.deleted_at IS NULL)) as admin_user_sort")
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->leftJoin('designations', 'designations.id', '=', 'teachers.designation_id')
            // Joined for the ordering, not for the search — see the sort on the
            // two listing properties below.
            ->leftJoin('job_types', 'job_types.id', '=', 'teachers.job_type_id');

        if ($deptId) {
            $query->where(fn ($q) => $q
                ->where('teachers.department_id', $this->departmentId)
                ->orWhereIn('teachers.id', $assignedIds)
                ->orWhereHas('administrativeRoles', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('administrative_role_user.department_id', $this->departmentId)
                    ->orWhere(fn ($q4) => $q4->whereNull('administrative_role_user.department_id')->where('administrative_role_user.faculty_id', $facId))
                )));
        }

        $query->published();

        // The same call the directory search makes. This page used to match
        // fewer fields than that one — no faculty name, no faculty short name —
        // for no reason anybody chose; it was simply a copy that fell behind.
        TeacherSearchTerm::apply($query, $this->q);

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

        $listing = $query
            ->whereRaw("EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.is_active = 1 AND aru.deleted_at IS NULL)")
            // jobType because designation_title falls back to it for the rows
            // that are not ranks; without it the card asks per teacher.
            ->with(['designation', 'jobType', 'department.faculty', 'teachingAreas',
                'administrativeRoles.administrativeRole', 'administrativeRoles.faculty', 'administrativeRoles.department', 'employmentStatus', 'user'])
            // publications_count instead of loading every paper to call count()
            // on it: three of the four themes print the number on each card,
            // which fetched a teacher's whole bibliography per card.
            ->withCount('publications');

        return TeacherDirectoryOrder::apply($listing, administrativeFirst: true)->get();
    }

    public function getTeachersProperty()
    {
        [$query, $adminScope] = $this->getBaseTeachersQuery();

        $listing = $query
            ->whereRaw("NOT EXISTS (SELECT 1 FROM administrative_role_user aru WHERE aru.user_id = teachers.user_id AND ({$adminScope}) AND aru.is_active = 1 AND aru.deleted_at IS NULL)")
            // jobType because designation_title falls back to it for the rows
            // that are not ranks; without it the card asks per teacher.
            ->with(['designation', 'jobType', 'department.faculty', 'teachingAreas',
                'administrativeRoles.administrativeRole', 'administrativeRoles.faculty', 'administrativeRoles.department', 'employmentStatus', 'user'])
            // publications_count instead of loading every paper to call count()
            // on it: three of the four themes print the number on each card,
            // which fetched a teacher's whole bibliography per card.
            ->withCount('publications');

        return TeacherDirectoryOrder::apply($listing)->paginate(12);
    }

    public function render(): View
    {
        // Theme::view() picks the active theme and falls back on its own
        // when that theme has been deleted, so this no longer hardcodes
        // one particular theme as everyone else's safety net.
        return view(Theme::view('livewire.department-search'));
    }
}
