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
        }

        $departments = collect();
        if ($selectedFaculty) {
            $departments = $selectedFaculty->departments()
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        return view("frontend.themes.{$activeTheme}.home", compact(
            'faculties',
            'selectedFaculty',
            'departments',
        ));
    }
}
