<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeRole;
use App\Models\Designation;
use App\Models\Faculty;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(?string $faculty_short_name = null): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Fetch all faculties with department & teacher counts (avoids per-row queries in the view)
        $faculties = Faculty::withCount(['departments', 'teachers'])
            ->orderBy('sort_order', 'asc')
            ->get();

        // Get selected faculty from route parameter or query (id or short_name)
        $selectedFacultyVal = $faculty_short_name ?? request()->query('faculty');
        $selectedFaculty = null;

        if ($selectedFacultyVal) {
            $selectedFaculty = $faculties->first(function ($f) use ($selectedFacultyVal) {
                return $f->id == $selectedFacultyVal
                    || strtolower($f->short_name) === strtolower($selectedFacultyVal);
            });
        }

        if (! $selectedFaculty && $faculties->isNotEmpty()) {
            $selectedFaculty = $faculties->first();
        }

        // Fetch departments for the selected faculty
        $departments = collect();
        if ($selectedFaculty) {
            $departments = $selectedFaculty->departments()
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        // --- Data previously assembled in the view (moved here for separation of concerns) ---
        $isHome = trim(request()->path(), '/') === '';
        $q = trim((string) request('q'));

        $visibleFaculties = $faculties;
        if ($q) {
            $visibleFaculties = $faculties->filter(fn ($f) => stripos($f->name, $q) !== false
                || stripos($f->description ?? '', $q) !== false
                || stripos($f->short_name, $q) !== false
                || stripos($f->code, $q) !== false
            );
        }

        $teachers = collect();
        $designations = collect();
        $adminRoles = collect();

        if (! $isHome && $selectedFaculty) {
            // Teachers of the selected faculty, filtered by designation / admin role.
            $teachersQ = $selectedFaculty->teachers()
                ->where('teachers.is_active', true)
                ->where('teachers.is_archived', false);

            if ($desig = request('designation')) {
                $teachersQ->where('teachers.designation_id', $desig);
            }

            if ($admin = request('admin')) {
                $adminTeacherIds = DB::table('administrative_role_user')
                    ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                    ->join('departments', 'departments.id', '=', 'teachers.department_id')
                    ->where('departments.faculty_id', $selectedFaculty->id)
                    ->where('administrative_role_user.administrative_role_id', $admin)
                    ->pluck('teachers.id');
                $teachersQ->whereIn('teachers.id', $adminTeacherIds);
            }

            $teachers = $teachersQ->with(['designation', 'department'])
                ->paginate(12)
                ->withQueryString();

            // Distinct designations present in this faculty.
            $designationIds = DB::table('teachers')
                ->join('departments', 'departments.id', '=', 'teachers.department_id')
                ->where('departments.faculty_id', $selectedFaculty->id)
                ->where('teachers.is_active', true)
                ->where('teachers.is_archived', false)
                ->whereNotNull('teachers.designation_id')
                ->distinct()
                ->pluck('teachers.designation_id');
            $designations = Designation::whereIn('id', $designationIds)->orderBy('sort_order')->get();

            // Distinct administrative roles present in this faculty.
            $adminRoleIds = DB::table('administrative_role_user')
                ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                ->join('departments', 'departments.id', '=', 'teachers.department_id')
                ->where('departments.faculty_id', $selectedFaculty->id)
                ->whereNotNull('administrative_role_user.administrative_role_id')
                ->distinct()
                ->pluck('administrative_role_user.administrative_role_id');
            $adminRoles = AdministrativeRole::whereIn('id', $adminRoleIds)->orderBy('sort_order')->get();
        }

        return view("frontend.themes.{$activeTheme}.home", compact(
            'faculties',
            'selectedFaculty',
            'departments',
            'isHome',
            'q',
            'visibleFaculties',
            'teachers',
            'designations',
            'adminRoles',
        ));
    }
}
