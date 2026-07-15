<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function show(string $faculty_short_name, string $department_code, string $teacher_webpage): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

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

        // Load teacher by webpage or employee_id or database id under the department
        // (matches either the teacher's home department or a department_teacher assignment)
        $teacher = Teacher::where(function ($query) use ($department) {
                $query->where('department_id', $department->id)
                    ->orWhereHas('departments', function ($q) use ($department) {
                        $q->whereNull('department_teacher.deleted_at')
                            ->where('department_teacher.department_id', $department->id);
                    });
            })
            ->where(function ($query) use ($teacher_webpage) {
                $query->where('webpage', $teacher_webpage)
                    ->orWhere('employee_id', $teacher_webpage)
                    ->orWhere('id', $teacher_webpage);
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
                'socialLinks.platform',
                'administrativeRoles'
            ])
            ->firstOrFail();

        return view("frontend.themes.{$activeTheme}.profile", compact('activeTheme', 'faculty', 'department', 'teacher'));
    }
}
