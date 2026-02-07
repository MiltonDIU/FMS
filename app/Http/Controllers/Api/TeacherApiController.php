<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeacherApiController extends Controller
{
    /**
     * Search for a teacher by employee_id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
        ]);

        $employeeId = $request->input('employee_id');

        $teacher = Teacher::query()
            ->with([
                'department',
                'designation',
                'employmentStatus',
                'jobType',
                'gender',
                'bloodGroup',
                'country',
                'religion',
                // 'maritalStatus', // Model does not exist
                'user',
                'educations' => function ($q) {
                    $q->with(['degreeType', 'degreeLevel'])->orderBy('sort_order');
                },
                'publications' => function ($q) {
                    $q->with(['type'])->withPivot(['author_role', 'sort_order', 'incentive_amount']);
                },
                'awards',
                'certifications',
                'trainingExperiences',
                'researchProjects',
                'skills',
                'teachingAreas',
                'memberships',
                'jobExperiences',
                'socialLinks'
            ])
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found or inactive.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $teacher,
        ]);
    }
}
