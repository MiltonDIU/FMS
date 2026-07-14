<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacultyResource;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\AdministrativeRole;
use App\Models\Designation;
use App\Models\Teacher;
use App\Http\Resources\TeacherCardResource;
use App\Http\Resources\TeacherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FrontendApiController extends Controller
{
    public function faculties(): AnonymousResourceCollection
    {
        $faculties = Faculty::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return FacultyResource::collection($faculties)->additional([
            'title' => 'Academic Faculties',
            'total' => $faculties->count(),
        ]);
    }

    public function departments(Request $request): AnonymousResourceCollection
    {
        $query = Department::where('is_active', true);

        $facultyCode = $request->query('faculty_code') ?? $request->query('code');

        if ($facultyCode) {
            $query->whereHas('faculty', function ($q) use ($facultyCode) {
                $q->where('code', $facultyCode);
            });
        }

        $departments = $query->orderBy('sort_order', 'asc')->get();

        return DepartmentResource::collection($departments);
    }

    public function department(string $code): AnonymousResourceCollection
    {
        $departments = Department::where('is_active', true)
            ->whereHas('faculty', function ($q) use ($code) {
                $q->whereRaw('LOWER(code) = ?', [strtolower($code)])
                  ->orWhereRaw('LOWER(short_name) = ?', [strtolower($code)]);
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        return DepartmentResource::collection($departments);
    }

    public function facultyTeachers(string $code): AnonymousResourceCollection
    {
        $faculty = Faculty::whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->orWhereRaw('LOWER(short_name) = ?', [strtolower($code)])
            ->firstOrFail();

        $teachers = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereHas('department', function ($q) use ($faculty) {
                $q->where('faculty_id', $faculty->id)
                  ->where('is_active', true);
            })
            ->with([
                'designation',
                'department',
                'user.administrativeRoles' => function ($q) use ($faculty) {
                    $q->where('administrative_role_user.is_active', true)
                      ->where(function ($sub) {
                          $sub->whereNull('administrative_role_user.end_date')
                              ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString());
                      })
                      ->where('administrative_role_user.faculty_id', $faculty->id);
                },
            ])
            ->get()
            ->sortBy(function ($teacher) {
                $desigSort = $teacher->designation ? (int) $teacher->designation->sort_order : 9999;
                $teacherSort = (int) ($teacher->sort_order ?? 9999);
                return [$desigSort, $teacherSort];
            })
            ->values();

        return TeacherCardResource::collection($teachers)->additional([
            'title'   => $faculty->name . ' — All Scholars',
            'faculty' => $faculty->code,
            'total'   => $teachers->count(),
        ]);
    }

    public function administrativeRole(string $dept): \Illuminate\Http\JsonResponse
    {
        $department = Department::whereRaw('LOWER(short_name) = ?', [strtolower($dept)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($dept)])
            ->first();

        if (!$department) {
            return response()->json([]);
        }

        $departmentId = $department->id;
        $facultyId = $department->faculty_id;

        $roles = AdministrativeRole::where('is_active', true)
            ->whereHas('users', function ($q) use ($departmentId, $facultyId) {
                $q->where('administrative_role_user.is_active', true)
                  ->where(function ($sub) {
                      $sub->whereNull('administrative_role_user.end_date')
                          ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString());
                  })
                  ->where(function ($sub) use ($departmentId, $facultyId) {
                      $sub->where('administrative_role_user.department_id', $departmentId);
                      if ($facultyId) {
                          $sub->orWhere('administrative_role_user.faculty_id', $facultyId);
                      }
                  });
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json($roles);
    }

    public function designation(string $dept): \Illuminate\Http\JsonResponse
    {
        $department = Department::whereRaw('LOWER(short_name) = ?', [strtolower($dept)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($dept)])
            ->first();

        if (!$department) {
            return response()->json([]);
        }

        $departmentId = $department->id;

        $designations = Designation::where('is_active', true)
            ->whereHas('teachers', function ($q) use ($departmentId) {
                $q->where('teachers.is_active', true)
                  ->where('teachers.is_archived', false)
                  ->where(function ($sub) use ($departmentId) {
                      $sub->where('teachers.department_id', $departmentId)
                          ->orWhereHas('departments', function ($sq) use ($departmentId) {
                              $sq->where('departments.id', $departmentId);
                          });
                  });
            })
            ->withCount(['teachers' => function ($q) use ($departmentId) {
                $q->where('teachers.is_active', true)
                  ->where('teachers.is_archived', false)
                  ->where(function ($sub) use ($departmentId) {
                      $sub->where('teachers.department_id', $departmentId)
                          ->orWhereHas('departments', function ($sq) use ($departmentId) {
                              $sq->where('departments.id', $departmentId);
                          });
                  });
            }])
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json($designations);
    }

    public function departmentTeachers(string $dept, Request $request): AnonymousResourceCollection
    {
        $department = Department::whereRaw('LOWER(short_name) = ?', [strtolower($dept)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($dept)])
            ->first();

        if (!$department) {
            return TeacherCardResource::collection(collect());
        }

        $departmentId = $department->id;
        $facultyId = $department->faculty_id;

        $query = Teacher::where('teachers.is_active', true)
            ->where('teachers.is_archived', false)
            ->with([
                'designation',
                'department',
                'user.administrativeRoles' => fn($q) => $q
                    ->where('administrative_role_user.is_active', true)
                    ->where(fn($sq) => $sq
                        ->whereNull('administrative_role_user.end_date')
                        ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString())
                    )
                    ->where(function ($sq) use ($departmentId, $facultyId) {
                        $sq->where('administrative_role_user.department_id', $departmentId);
                        if ($facultyId) {
                            $sq->orWhere(function ($fq) use ($facultyId) {
                                $fq->whereNull('administrative_role_user.department_id')
                                   ->where('administrative_role_user.faculty_id', $facultyId);
                            });
                        }
                    })
            ]);

        // Filter teachers belonging to the department (direct or pivot)
        $query->where(function ($q) use ($departmentId) {
            $q->where('teachers.department_id', $departmentId)
              ->orWhereHas('departments', function ($sq) use ($departmentId) {
                  $sq->where('departments.id', $departmentId);
              });
        });

        // Filter by designation if provided
        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->query('designation_id'));
        }

        // Filter by administrative role if provided
        if ($request->filled('administrative_role_id')) {
            $roleId = $request->query('administrative_role_id');
            $query->whereHas('user.administrativeRoles', function ($q) use ($roleId, $departmentId, $facultyId) {
                $q->where('administrative_roles.id', $roleId)
                  ->where('administrative_role_user.is_active', true)
                  ->where(function ($sub) {
                      $sub->whereNull('administrative_role_user.end_date')
                          ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString());
                  })
                  ->where(function ($sub) use ($departmentId, $facultyId) {
                      $sub->where('administrative_role_user.department_id', $departmentId);
                      if ($facultyId) {
                          $sub->orWhere(function ($sq) use ($facultyId) {
                              $sq->whereNull('administrative_role_user.department_id')
                                 ->where('administrative_role_user.faculty_id', $facultyId);
                          });
                      }
                  });
            });
        }

        $teachers = $query->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->select('teachers.*')
            ->orderBy('designations.sort_order', 'asc')
            ->orderBy('teachers.sort_order', 'asc')
            ->get();

        $sortedTeachers = $teachers->sort(function ($a, $b) use ($departmentId, $facultyId) {
            // Get min administrative role sort order for $a
            $aMinAdminSort = PHP_INT_MAX;
            if ($a->user && $a->user->relationLoaded('administrativeRoles')) {
                foreach ($a->user->administrativeRoles as $role) {
                    $isActive = (bool) $role->pivot->is_active;
                    $notEnded = is_null($role->pivot->end_date) || \Carbon\Carbon::parse($role->pivot->end_date)->isFuture();
                    if ($isActive && $notEnded) {
                        $deptMatch = $role->pivot->department_id == $departmentId;
                        $facMatch = is_null($role->pivot->department_id) && $facultyId && $role->pivot->faculty_id == $facultyId;
                        if ($deptMatch || $facMatch) {
                            $aMinAdminSort = min($aMinAdminSort, (int) ($role->sort_order ?? 9999));
                        }
                    }
                }
            }

            // Get min administrative role sort order for $b
            $bMinAdminSort = PHP_INT_MAX;
            if ($b->user && $b->user->relationLoaded('administrativeRoles')) {
                foreach ($b->user->administrativeRoles as $role) {
                    $isActive = (bool) $role->pivot->is_active;
                    $notEnded = is_null($role->pivot->end_date) || \Carbon\Carbon::parse($role->pivot->end_date)->isFuture();
                    if ($isActive && $notEnded) {
                        $deptMatch = $role->pivot->department_id == $departmentId;
                        $facMatch = is_null($role->pivot->department_id) && $facultyId && $role->pivot->faculty_id == $facultyId;
                        if ($deptMatch || $facMatch) {
                            $bMinAdminSort = min($bMinAdminSort, (int) ($role->sort_order ?? 9999));
                        }
                    }
                }
            }

            // If their admin sort orders are different, order by admin sort order
            if ($aMinAdminSort !== $bMinAdminSort) {
                return $aMinAdminSort <=> $bMinAdminSort;
            }

            // Fallback to designation sort_order
            $aDesigSort = $a->designation ? (int) $a->designation->sort_order : 9999;
            $bDesigSort = $b->designation ? (int) $b->designation->sort_order : 9999;
            if ($aDesigSort !== $bDesigSort) {
                return $aDesigSort <=> $bDesigSort;
            }

            // Fallback to teacher sort_order
            return ($a->sort_order ?? 9999) <=> ($b->sort_order ?? 9999);
        })->values();

        return TeacherCardResource::collection($sortedTeachers);
    }

    public function teacherProfile(string $dept, string $webpage): TeacherResource
    {
        $department = Department::whereRaw('LOWER(code) = ?', [strtolower($dept)])
            ->orWhereRaw('LOWER(short_name) = ?', [strtolower($dept)])
            ->firstOrFail();

        $teacher = Teacher::where('webpage', $webpage)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function ($q) use ($department) {
                $q->where('department_id', $department->id)
                  ->orWhereHas('departments', function ($sq) use ($department) {
                      $sq->where('departments.id', $department->id);
                  });
            })
            ->with([
                'designation',
                'department',
                'gender',
                'bloodGroup',
                'religion',
                'country',
                'employmentStatus',
                'jobType',
                'educations.degreeType',
                'educations.resultType',
                'educations.country',
                'educations.educationalInstitution',
                'educations.majorRelation',
                'publications.type',
                'publications.linkage',
                'publications.quartile',
                'publications.grant',
                'publications.collaboration',
                'trainingExperiences.organizationRelation',
                'trainingExperiences.countryRelation',
                'certifications.issuingAuthorityOrganizationRelation',
                'skills',
                'teachingAreas',
                'memberships.membershipOrganization',
                'memberships.membershipType',
                'awards.awardingBodyOrganizationRelation',
                'jobExperiences.positionRelation',
                'jobExperiences.organizationRelation',
                'jobExperiences.countryRelation',
                'socialLinks.platform',
                'researchProjects.fundingAgencyOrganizationRelation',
                'user.administrativeRoles' => fn($q) => $q->where('administrative_role_user.is_active', true)
                    ->where(fn($sq) => $sq->whereNull('administrative_role_user.end_date')
                        ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString()))
            ])
            ->firstOrFail();

        return new TeacherResource($teacher);
    }

    public function teachersCount(): \Illuminate\Http\JsonResponse
    {
        $count = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }
}
