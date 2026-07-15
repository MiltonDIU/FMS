<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\UserAdministrativeRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function show(Request $request, string $faculty_short_name, string $department_code): View
    {

        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Find faculty
        $faculty = Faculty::where('is_active', true)
            ->where(function ($q) use ($faculty_short_name) {
                $q->where('short_name', $faculty_short_name)
                  ->orWhere('id', $faculty_short_name);
            })
            ->firstOrFail();

        // Find department under that faculty
        $department = Department::where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->where(function ($q) use ($department_code) {
                $q->where('code', $department_code)
                  ->orWhere('id', $department_code);
            })
            ->firstOrFail();

        // Get filter designation / administrative role from query
        $designationId = $request->query('designation');
        $adminId = $request->query('admin');


        // Fetch teachers in this department (home department OR department_teacher assignment)
        $deptTeacherIds = Teacher::whereHas('departments', fn ($q) => $q->whereNull('department_teacher.deleted_at')->where('department_teacher.department_id', $department->id))->pluck('id');

        $query = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->where(function ($q) use ($department, $deptTeacherIds) {
                $q->where('teachers.department_id', $department->id)
                    ->orWhereIn('teachers.id', $deptTeacherIds);
            });

        if ($designationId) {
            $query->where('teachers.designation_id', $designationId);
        }

        if ($adminId) {
            $adminTeacherIds = UserAdministrativeRole::query()
                ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                ->where(function ($q) use ($department, $deptTeacherIds) {
                    $q->where('teachers.department_id', $department->id)
                        ->orWhereIn('teachers.id', $deptTeacherIds);
                })
                ->where('administrative_role_user.administrative_role_id', $adminId)
                ->distinct()
                ->pluck('teachers.id');
            $query->whereIn('teachers.id', $adminTeacherIds);
        }

        // Order by designation sort_order, then teacher sort_order
        $teachers = $query->with(['designation', 'department'])
            ->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->paginate(12)
            ->withQueryString();

        // Get designations present in this department for filters
        $designationIds = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->where(function ($q) use ($department, $deptTeacherIds) {
                $q->where('teachers.department_id', $department->id)
                    ->orWhereIn('teachers.id', $deptTeacherIds);
            })
            ->whereNotNull('teachers.designation_id')
            ->distinct()
            ->pluck('teachers.designation_id');

        $designations = Designation::whereIn('id', $designationIds)
            ->orderBy('sort_order', 'asc')
            ->get();

        // Get administrative roles present in this department (via administrative_role_user pivot)
        $adminRoleIds = UserAdministrativeRole::query()
            ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
            ->where(function ($q) use ($department, $deptTeacherIds) {
                $q->where('teachers.department_id', $department->id)
                    ->orWhereIn('teachers.id', $deptTeacherIds);
            })
            ->whereNotNull('administrative_role_user.administrative_role_id')
            ->distinct()
            ->pluck('administrative_role_user.administrative_role_id');

        $adminRoles = \App\Models\AdministrativeRole::whereIn('id', $adminRoleIds)
            ->orderBy('sort_order', 'asc')
            ->get();

        $faculties = Faculty::where('is_active', true)->orderBy('sort_order')->get();

        $totalMembers = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->where(function ($q) use ($department, $deptTeacherIds) {
                $q->where('teachers.department_id', $department->id)
                    ->orWhereIn('teachers.id', $deptTeacherIds);
            })
            ->count();

        return view("frontend.themes.{$activeTheme}.department", compact('faculties', 'faculty', 'department', 'teachers', 'designations', 'adminRoles', 'totalMembers'));
    }
}
