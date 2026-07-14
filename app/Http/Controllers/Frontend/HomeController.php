<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Setting;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(?string $faculty_short_name = null): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        $faculties = Faculty::withCount(['departments', 'teachers'])
            ->orderBy('sort_order', 'asc')
            ->get();

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

        return view("frontend.themes.{$activeTheme}.home", compact(
            'faculties',
            'selectedFaculty',
        ));
    }
}
