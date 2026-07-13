<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PublicDataApiController extends Controller
{
    public function faculties(): JsonResponse
    {
        $faculties = Faculty::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json($faculties);
    }

    public function departments(Request $request): JsonResponse
    {
        $query = Department::where('is_active', true);

        if ($request->has('faculty_id')) {
            $query->where('faculty_id', $request->query('faculty_id'));
        }

        $departments = $query->orderBy('sort_order', 'asc')->get();

        return response()->json($departments);
    }

    public function teachers(Request $request): JsonResponse
    {
        $query = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->with(['designation', 'employmentStatus']);

        if ($request->has('department_code')) {
            $code = $request->query('department_code');
            $query->whereHas('departments', function ($q) use ($code) {
                $q->where('code', $code);
            })->orWhereHas('department', function ($q) use ($code) {
                $q->where('code', $code);
            });
        }

        $teachers = $query->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->get();

        return response()->json($teachers);
    }

    public function teacherDetails(string $webpage): JsonResponse
    {
        $teacher = Teacher::where('webpage', $webpage)
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

        return response()->json($teacher);
    }
}
