<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function show(Request $request, string $faculty_short_name, string $department_code): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Find faculty
        $faculty = Faculty::where('short_name', $faculty_short_name)
            ->orWhere('id', $faculty_short_name)
            ->firstOrFail();

        // Find department under that faculty
        $department = Department::where('faculty_id', $faculty->id)
            ->where(function ($q) use ($department_code) {
                $q->where('code', $department_code)
                  ->orWhere('id', $department_code);
            })
            ->firstOrFail();

        // Get filter designation / administrative role from query
        $designationId = $request->query('designation');
        $adminId = $request->query('admin');

        // Fetch teachers in this department
        $query = Teacher::where('teachers.department_id', $department->id)
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false);

        if ($designationId) {
            $query->where('teachers.designation_id', $designationId);
        }

        if ($adminId) {
            $adminTeacherIds = \DB::table('administrative_role_user')
                ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                ->where('teachers.department_id', $department->id)
                ->where('administrative_role_user.administrative_role_id', $adminId)
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
        $designationIds = Teacher::where('department_id', $department->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('designation_id')
            ->distinct()
            ->pluck('designation_id');

        $designations = Designation::whereIn('id', $designationIds)
            ->orderBy('sort_order', 'asc')
            ->get();

        // Get administrative roles present in this department (via administrative_role_user pivot)
        $adminRoleIds = \DB::table('administrative_role_user')
            ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
            ->where('teachers.department_id', $department->id)
            ->whereNotNull('administrative_role_user.administrative_role_id')
            ->distinct()
            ->pluck('administrative_role_user.administrative_role_id');

        $adminRoles = \App\Models\AdministrativeRole::whereIn('id', $adminRoleIds)
            ->orderBy('sort_order', 'asc')
            ->get();

        $faculties = Faculty::orderBy('sort_order')->get();

        return view("frontend.themes.{$activeTheme}.department", compact('faculties', 'faculty', 'department', 'teachers', 'designations', 'adminRoles'));
    }
}
