<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\Setting;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PublicationController extends Controller
{
    public function show(string $faculty_short_name, string $department_code, string $teacher_webpage, string $publication_slug): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Find faculty
        $faculty = Faculty::where('short_name', $faculty_short_name)->firstOrFail();

        // Find department under faculty
        $department = Department::where('code', $department_code)
            ->where('faculty_id', $faculty->id)
            ->firstOrFail();

        // Find teacher under department
        $teacher = Teacher::where('webpage', $teacher_webpage)
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->firstOrFail();

        // Find publication by slug (computed from title as a fallback).
        $publication = $teacher->publications->first(function ($pub) use ($publication_slug) {
            return ($pub->slug ?: Str::slug($pub->title)) === $publication_slug;
        });

        if (!$publication) {
            $publication = $teacher->publications->firstWhere('id', $publication_slug);
        }

        if (!$publication) {
            abort(404);
        }

        // Build citations here (kept out of the view for separation of concerns)
        $authors = trim($teacher->first_name . ' ' . $teacher->last_name);
        $citations = $publication->citations($authors);

        return view("frontend.themes.{$activeTheme}.publication", compact('faculty', 'department', 'teacher', 'publication', 'authors', 'citations'));
    }
}
