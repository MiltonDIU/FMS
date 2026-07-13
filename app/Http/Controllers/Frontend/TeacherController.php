<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function show(string $id): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Load teacher by employee_id or database id
        $teacher = Teacher::where(function ($query) use ($id) {
                $query->where('employee_id', $id)
                    ->orWhere('id', $id);
            })
            ->where('is_active', true)
            ->where('is_archived', false)
            ->firstOrFail();

        return view("frontend.themes.{$activeTheme}.profile", compact('teacher'));
    }
}
