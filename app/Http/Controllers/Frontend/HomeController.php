<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Fetch some active teachers
        $teachers = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->limit(12)
            ->get();

        return view("frontend.themes.{$activeTheme}.home", compact('teachers'));
    }
}
