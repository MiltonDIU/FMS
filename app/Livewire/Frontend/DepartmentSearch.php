<?php

namespace App\Livewire\Frontend;

use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    public ?int $departmentId = null;

    public function mount(?int $departmentId = null): void
    {
        $this->departmentId = $departmentId;
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->q = '';
    }

    public function getTeachersProperty()
    {
        $query = Teacher::query()
            ->select('teachers.*')
            ->join('departments', 'departments.id', '=', 'teachers.department_id')
            ->leftJoin('designations', 'designations.id', '=', 'teachers.designation_id')
            ->where('teachers.department_id', $this->departmentId)
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
                    ->orWhere('designations.name', 'like', $like);
            });
        }

        if ($this->designationId) {
            $query->where('teachers.designation_id', $this->designationId);
        }

        if ($this->adminRoleId) {
            $adminTeacherIds = DB::table('administrative_role_user')
                ->join('teachers as t2', 't2.user_id', '=', 'administrative_role_user.user_id')
                ->where('t2.department_id', $this->departmentId)
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
        return view('frontend.themes.theme_diu.livewire.department-search');
    }
}
