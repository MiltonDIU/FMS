<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Setting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Fetch all faculties
        $faculties = Faculty::orderBy('sort_order', 'asc')->get();

        // Get selected faculty from query (can be id or short_name)
        $selectedFacultyVal = request()->query('faculty');
        $selectedFaculty = null;

        if ($selectedFacultyVal) {
            $selectedFaculty = $faculties->first(function ($f) use ($selectedFacultyVal) {
                return $f->id == $selectedFacultyVal 
                    || strtolower($f->short_name) === strtolower($selectedFacultyVal);
            });
        }
        
        if (!$selectedFaculty && $faculties->isNotEmpty()) {
            $selectedFaculty = $faculties->first();
        }

        // Fetch departments for the selected faculty
        $departments = collect();
        if ($selectedFaculty) {
            $departments = $selectedFaculty->departments()
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        return view("frontend.themes.{$activeTheme}.home", compact('faculties', 'selectedFaculty', 'departments'));
    }
}
