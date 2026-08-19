<?php

namespace App\Http\Controllers\Frontend;

use App\Helpers\Theme;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(?string $faculty_short_name = null): View
    {
        $faculties = Faculty::where('is_active', true)
            ->withCount(['departments', 'teachers'])
            ->orderBy('sort_order', 'asc')
            ->get();

        $selectedFaculty = null;

        if ($faculty_short_name) {
            $selectedFaculty = $faculties->first(function ($f) use ($faculty_short_name) {
                return $f->id == $faculty_short_name
                    || strtolower($f->short_name) === strtolower($faculty_short_name);
            });

            /*
             * A slug that matches no faculty is a wrong address, not the home
             * page. This route is the catch-all for every single-segment URL, so
             * without this every typo and every stale link answered 200 with the
             * full directory — the same page served at unlimited addresses, which
             * search engines read as duplicate content and a visitor reads as
             * "the link worked".
             */
            if (! $selectedFaculty) {
                abort(404);
            }
        }

        $departments = collect();
        if ($selectedFaculty) {
            $departments = $selectedFaculty->departments()
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        $totalTeachers   = Teacher::where('is_active', true)->where('is_archived', false)->count();

        /*
         * A department is only reachable when its faculty is published too:
         * DepartmentController resolves the faculty first and answers 404 when
         * it is switched off. One department hangs off "System - Unassigned
         * Faculty", the holding pen for teachers with no department set, and
         * counting it advertised a thirty-first department nobody can open.
         */
        $totalDepartments = Department::where('is_active', true)
            ->whereHas('faculty', fn ($query) => $query->where('is_active', true))
            ->count();

        $totalFaculties  = $faculties->count();
        $totalPublications = Publication::count();

        return view(Theme::view('home'), compact(
            'faculties',
            'selectedFaculty',
            'departments',
            'totalTeachers',
            'totalDepartments',
            'totalFaculties',
            'totalPublications',
        ));
    }
}
