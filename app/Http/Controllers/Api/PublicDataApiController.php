<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Teacher;
use App\Http\Resources\FacultyResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\TeacherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicDataApiController extends Controller
{
    public function faculties(): AnonymousResourceCollection
    {
        $faculties = Faculty::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return FacultyResource::collection($faculties);
    }

    public function departments(Request $request): AnonymousResourceCollection
    {
        $query = Department::where('is_active', true);

        if ($request->has('faculty_id')) {
            $query->where('faculty_id', $request->query('faculty_id'));
        }

        $departments = $query->orderBy('sort_order', 'asc')->get();

        return DepartmentResource::collection($departments);
    }

    public function teachers(Request $request): AnonymousResourceCollection
    {
        $query = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->with([
                'designation',
                'employmentStatus',
                'department',
                'user.administrativeRoles' => fn($q) => $q->where('administrative_role_user.is_active', true)
                    ->where(fn($sq) => $sq->whereNull('administrative_role_user.end_date')
                        ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString()))
            ]);

        if ($request->has('department_code')) {
            $code = $request->query('department_code');
            $dept = Department::where('code', $code)->first();
            
            if ($dept) {
                $departmentId = $dept->id;
                $facultyId = $dept->faculty_id;

                $query->where(function ($q) use ($departmentId, $facultyId) {
                    $q->where('department_id', $departmentId)
                      ->orWhereHas('departments', function ($sq) use ($departmentId) {
                          $sq->where('departments.id', $departmentId);
                      })
                      ->orWhereHas('user.administrativeRoles', function ($sq) use ($departmentId) {
                          $sq->where('administrative_role_user.department_id', $departmentId)
                             ->where('administrative_role_user.is_active', true);
                      });

                    if ($facultyId) {
                        $q->orWhereHas('user.administrativeRoles', function ($sq) use ($facultyId) {
                            $sq->where('administrative_role_user.faculty_id', $facultyId)
                               ->where('administrative_role_user.is_active', true);
                        });
                    }
                });
            }
        }

        $teachers = $query->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->get();

        return TeacherResource::collection($teachers);
    }

    public function teacherDetails(string $webpage): TeacherResource
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
                'socialLinks.platform',
                'user.administrativeRoles' => fn($q) => $q->where('administrative_role_user.is_active', true)
                    ->where(fn($sq) => $sq->whereNull('administrative_role_user.end_date')
                        ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString()))
            ])
            ->firstOrFail();

        return new TeacherResource($teacher);
    }

    public function designations(): \Illuminate\Http\JsonResponse
    {
        $designations = \App\Models\Designation::orderBy('sort_order', 'asc')->get();
        return response()->json($designations);
    }

    public function administrativeRoles(): \Illuminate\Http\JsonResponse
    {
        $roles = \App\Models\AdministrativeRole::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
        return response()->json($roles);
    }

    public function departmentDirectory(string $code): \Illuminate\Http\JsonResponse
    {
        $dept = \App\Models\Department::where('code', $code)->first();
        if (!$dept) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $departmentId = $dept->id;
        $facultyId = $dept->faculty_id;
        $facultyName = $dept->faculty ? $dept->faculty->name : '';

        request()->query->set('department_code', $code);

        $teachersQuery = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->with([
                'designation',
                'employmentStatus',
                'department',
                'user.administrativeRoles' => fn($q) => $q->where('administrative_role_user.is_active', true)
                    ->where(fn($sq) => $sq->whereNull('administrative_role_user.end_date')
                        ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString()))
            ]);

        $teachersQuery->where(function ($q) use ($departmentId, $facultyId) {
            $q->where('department_id', $departmentId)
              ->orWhereHas('departments', function ($sq) use ($departmentId) {
                  $sq->where('departments.id', $departmentId);
              })
              ->orWhereHas('user.administrativeRoles', function ($sq) use ($departmentId) {
                  $sq->where('administrative_role_user.department_id', $departmentId)
                     ->where('administrative_role_user.is_active', true);
              });

            if ($facultyId) {
                $q->orWhereHas('user.administrativeRoles', function ($sq) use ($facultyId) {
                    $sq->where('administrative_role_user.faculty_id', $facultyId)
                       ->where('administrative_role_user.is_active', true);
                });
            }
        });

        $teachers = $teachersQuery->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->get();

        $formattedTeachers = TeacherResource::collection($teachers)->toArray(request());

        $rolesMap = [];
        foreach ($formattedTeachers as $t) {
            if (!empty($t['administrative_roles'])) {
                foreach ($t['administrative_roles'] as $role) {
                    $roleId = $role['id'];
                    if (!isset($rolesMap[$roleId])) {
                        $rolesMap[$roleId] = [
                            'id' => $roleId,
                            'name' => $role['name'],
                            'short_name' => $role['short_name'],
                            'sort_order' => $role['sort_order'] ?? 0,
                            'count' => 0
                        ];
                    }
                    $rolesMap[$roleId]['count']++;
                }
            }
        }
        $formattedRoles = array_values($rolesMap);
        usort($formattedRoles, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $designationsMap = [];
        foreach ($formattedTeachers as $t) {
            $hasAdminRole = !empty($t['administrative_roles']);
            if (!$hasAdminRole && !empty($t['academicDesignation'])) {
                $desigName = $t['academicDesignation'];
                if (!isset($designationsMap[$desigName])) {
                    $designationsMap[$desigName] = [
                        'name' => $desigName,
                        'sort_order' => $t['designation_sort_order'] ?? 9999,
                        'count' => 0
                    ];
                }
                $designationsMap[$desigName]['count']++;
            }
        }
        $formattedDesignations = array_values($designationsMap);
        usort($formattedDesignations, fn($a, $b) => ($a['sort_order'] ?? 9999) <=> ($b['sort_order'] ?? 9999));

        return response()->json([
            'department' => [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
                'faculty_name' => $facultyName
            ],
            'teachers' => $formattedTeachers,
            'administrative_roles' => $formattedRoles,
            'designations' => $formattedDesignations
        ]);
    }
}
