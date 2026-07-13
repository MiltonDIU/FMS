<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function show(Request $request, string $id): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Find department by code or ID
        $department = Department::where('code', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        // Get filter designation from query
        $designationId = $request->query('designation');

        // Fetch teachers in this department
        $query = Teacher::where('department_id', $department->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['designation', 'employmentStatus']);

        if ($designationId) {
            $query->where('designation_id', $designationId);
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

        return view("frontend.themes.{$activeTheme}.department", compact('department', 'teachers', 'designations'));
    }
}
