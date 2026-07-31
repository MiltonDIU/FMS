<?php

namespace App\Http\Controllers\Frontend;

use App\Helpers\Theme;
use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Teacher;
use App\Services\DepartmentContacts;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function show(Request $request, string $faculty_short_name, string $department_code): View
    {

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

        /*
         * The page is rendered by the DepartmentSearch Livewire component, which
         * builds its own teacher list, filters and faculty navigation — it has
         * to, since typing in the search box re-renders it without a round trip
         * through here.
         *
         * This action used to build all of that as well: a paginated teacher
         * query, the designation and administrative-role filter lists, and every
         * faculty. None of it was read by any theme's department view, and it
         * cost around twenty queries on every page load. Only the count survives,
         * because the Schema.org payload quotes it.
         */
        $totalMembers = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->where(fn ($q) => $q
                ->where('teachers.department_id', $department->id)
                ->orWhereIn('teachers.id', fn ($sub) => $sub
                    ->select('teacher_id')
                    ->from('department_teacher')
                    ->whereNull('deleted_at')
                    ->where('department_id', $department->id)))
            ->count();

        return view(Theme::view('department'), compact('faculty', 'department', 'totalMembers'));
    }

    public function contact(Request $request, string $faculty_short_name, string $department_code): View
    {
        // Resolve the department (same lookup as the department page)
        $faculty = Faculty::where('is_active', true)
            ->where(function ($q) use ($faculty_short_name) {
                $q->where('short_name', $faculty_short_name)
                  ->orWhere('id', $faculty_short_name);
            })
            ->firstOrFail();

        $department = Department::where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->where(function ($q) use ($department_code) {
                $q->where('code', $department_code)
                  ->orWhere('id', $department_code);
            })
            ->firstOrFail();

        // Contacts come from the university backend, behind a cache, so this page
        // and the department listing can both show them without either paying for
        // a live call. See App\Services\DepartmentContacts.
        $contacts = DepartmentContacts::for($department);

        $sections = $contacts['sections'];
        $apiError = $contacts['error'];
        $blocks = DepartmentContacts::BLOCKS;

        return view(Theme::view('contact'), compact(
            'faculty', 'department', 'sections', 'blocks', 'apiError'
        ));
    }
}
