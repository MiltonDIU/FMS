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

        // Load teacher by webpage or employee_id or database id with all relations eager loaded
        $teacher = Teacher::where(function ($query) use ($id) {
                $query->where('webpage', $id)
                    ->orWhere('employee_id', $id)
                    ->orWhere('id', $id);
            })
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'designation',
                'department',
                'educations.degreeLevel',
                'educations.degreeType',
                'educations.resultType',
                'publications',
                'trainingExperiences',
                'certifications',
                'skills',
                'teachingAreas',
                'memberships.membershipType',
                'awards',
                'jobExperiences',
                'socialLinks.platform'
            ])
            ->firstOrFail();

        return view("frontend.themes.{$activeTheme}.profile", compact('teacher'));
    }
}
