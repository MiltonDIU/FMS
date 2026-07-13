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

        // Get filter designation from query
        $designationId = $request->query('designation');

        // Fetch teachers in this department
        $query = Teacher::where('teachers.department_id', $department->id)
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->with(['designation', 'employmentStatus']);

        if ($designationId) {
            $query->where('teachers.designation_id', $designationId);
        }

        // Order by designation sort_order, then teacher sort_order
        $teachers = $query->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->get();

        // Get designations present in this department for filters
        $designationIds = Teacher::where('department_id', $department->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->distinct()
            ->pluck('designation_id');

        $designations = Designation::whereIn('id', $designationIds)
            ->orderBy('sort_order', 'asc')
            ->get();

        return view("frontend.themes.{$activeTheme}.department", compact('faculty', 'department', 'teachers', 'designations'));
    }
}
