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
use Illuminate\Http\JsonResponse;
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

    /**
     * Search and list teachers with filters: employee_id, email, name, designation, department, faculty, q.
     */
    public function searchTeachers(Request $request): JsonResponse
    {
        $query = Teacher::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'designation',
                'department.faculty',
                'departments.faculty',
                'user',
                'gender',
                'bloodGroup',
                'country',
                'religion',
                'employmentStatus',
                'jobType',
            ]);

        // Filter by employee_id
        if ($request->filled('employee_id')) {
            $empId = trim($request->input('employee_id'));
            $query->where('employee_id', 'LIKE', "%{$empId}%");
        }

        // Filter by email
        if ($request->filled('email')) {
            $email = trim($request->input('email'));
            $query->where(function ($q) use ($email) {
                $q->where('secondary_email', 'LIKE', "%{$email}%")
                  ->orWhereHas('user', function ($uq) use ($email) {
                      $uq->where('email', 'LIKE', "%{$email}%");
                  });
            });
        }

        // Filter by name
        if ($request->filled('name')) {
            $name = trim($request->input('name'));
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'LIKE', "%{$name}%")
                  ->orWhere('middle_name', 'LIKE', "%{$name}%")
                  ->orWhere('last_name', 'LIKE', "%{$name}%")
                  ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$name}%"])
                  ->orWhereHas('user', function ($uq) use ($name) {
                      $uq->where('name', 'LIKE', "%{$name}%");
                  });
            });
        }

        // Filter by designation (ID or name/short_name/code)
        if ($request->filled('designation')) {
            $desig = trim($request->input('designation'));
            if (is_numeric($desig)) {
                $query->where('designation_id', $desig);
            } else {
                $query->whereHas('designation', function ($dq) use ($desig) {
                    $dq->where('name', 'LIKE', "%{$desig}%")
                       ->orWhere('short_name', 'LIKE', "%{$desig}%")
                       ->orWhere('code', 'LIKE', "%{$desig}%");
                });
            }
        }

        // Filter by department (ID or name/short_name/code)
        if ($request->filled('department')) {
            $dept = trim($request->input('department'));
            $query->where(function ($q) use ($dept) {
                if (is_numeric($dept)) {
                    $q->where('department_id', $dept)
                      ->orWhereHas('departments', function ($sq) use ($dept) {
                          $sq->where('departments.id', $dept);
                      });
                } else {
                    $q->whereHas('department', function ($dq) use ($dept) {
                        $dq->where('name', 'LIKE', "%{$dept}%")
                           ->orWhere('short_name', 'LIKE', "%{$dept}%")
                           ->orWhere('code', 'LIKE', "%{$dept}%");
                    })->orWhereHas('departments', function ($dq) use ($dept) {
                        $dq->where('departments.name', 'LIKE', "%{$dept}%")
                           ->orWhere('departments.short_name', 'LIKE', "%{$dept}%")
                           ->orWhere('departments.code', 'LIKE', "%{$dept}%");
                    });
                }
            });
        }

        // Filter by faculty (ID or name/short_name/code)
        if ($request->filled('faculty')) {
            $fac = trim($request->input('faculty'));
            $query->where(function ($q) use ($fac) {
                if (is_numeric($fac)) {
                    $q->whereHas('department', function ($dq) use ($fac) {
                        $dq->where('faculty_id', $fac);
                    })->orWhereHas('departments', function ($dq) use ($fac) {
                        $dq->where('faculty_id', $fac);
                    });
                } else {
                    $q->whereHas('department.faculty', function ($fq) use ($fac) {
                        $fq->where('name', 'LIKE', "%{$fac}%")
                           ->orWhere('short_name', 'LIKE', "%{$fac}%")
                           ->orWhere('code', 'LIKE', "%{$fac}%");
                    })->orWhereHas('departments.faculty', function ($fq) use ($fac) {
                        $fq->where('name', 'LIKE', "%{$fac}%")
                           ->orWhere('short_name', 'LIKE', "%{$fac}%")
                           ->orWhere('code', 'LIKE', "%{$fac}%");
                    });
                }
            });
        }

        // General keyword search
        if ($request->filled('q') || $request->filled('search')) {
            $searchTerm = trim($request->input('q') ?? $request->input('search'));

            // Query api_only_db connection if available
            $connName = config('database.connections.api_only_db') ? 'api_only_db' : config('database.default');

            $teachersList = Teacher::on($connName)
                ->with(['designation', 'department.faculty', 'user'])
                ->where(function ($q) use ($searchTerm) {
                    $q->where('employee_id', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('webpage', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('secondary_email', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('middle_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$searchTerm}%"])
                      ->orWhereHas('user', function ($uq) use ($searchTerm) {
                          $uq->where('name', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                      });
                })
                ->limit(20)
                ->get();

            $results = $teachersList->map(function ($teacher) {
                // Check if teacher exists in local main database
                $existsLocally = Teacher::on(config('database.default'))
                    ->where(function ($q) use ($teacher) {
                        if ($teacher->employee_id) {
                            $q->where('employee_id', $teacher->employee_id);
                        }
                        if ($teacher->webpage) {
                            $q->orWhere('webpage', $teacher->webpage);
                        }
                    })->exists();

                return [
                    'id' => $teacher->id,
                    'employee_id' => $teacher->employee_id,
                    'employeeID' => $teacher->employee_id,
                    'webpage' => $teacher->webpage,
                    'full_name' => $teacher->full_name,
                    'name' => $teacher->full_name,
                    'email' => $teacher->user?->email ?? $teacher->secondary_email,
                    'secondary_email' => $teacher->secondary_email,
                    'phone' => $teacher->phone,
                    'photo' => $teacher->photo,
                    'exists_locally' => $existsLocally,
                    'designation' => $teacher->designation ? [
                        'id' => $teacher->designation->id,
                        'name' => $teacher->designation->name,
                    ] : null,
                    'department' => $teacher->department ? [
                        'id' => $teacher->department->id,
                        'name' => $teacher->department->name,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $teachers = $query->orderBy('sort_order', 'asc')->paginate($perPage);

        $transformed = $teachers->getCollection()->map(function ($teacher) {
            return [
                'id' => $teacher->id,
                'employee_id' => $teacher->employee_id,
                'webpage' => $teacher->webpage,
                'full_name' => $teacher->full_name,
                'email' => $teacher->user?->email ?? $teacher->secondary_email,
                'secondary_email' => $teacher->secondary_email,
                'phone' => $teacher->phone,
                'photo' => $teacher->photo,
                'designation' => $teacher->designation ? [
                    'id' => $teacher->designation->id,
                    'name' => $teacher->designation->name,
                    'short_name' => $teacher->designation->short_name,
                ] : null,
                'department' => $teacher->department ? [
                    'id' => $teacher->department->id,
                    'name' => $teacher->department->name,
                    'short_name' => $teacher->department->short_name,
                    'code' => $teacher->department->code,
                    'faculty' => $teacher->department->faculty ? [
                        'id' => $teacher->department->faculty->id,
                        'name' => $teacher->department->faculty->name,
                        'short_name' => $teacher->department->faculty->short_name,
                        'code' => $teacher->department->faculty->code,
                    ] : null,
                ] : null,
                'assigned_departments' => $teacher->departments->map(function ($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                        'short_name' => $dept->short_name,
                        'code' => $dept->code,
                        'faculty' => $dept->faculty ? [
                            'id' => $dept->faculty->id,
                            'name' => $dept->faculty->name,
                            'short_name' => $dept->faculty->short_name,
                            'code' => $dept->faculty->code,
                        ] : null,
                    ];
                }),
                'is_active' => (bool) $teacher->is_active,
                'profile_status' => $teacher->profile_status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
            'pagination' => [
                'total' => $teachers->total(),
                'per_page' => $teachers->perPage(),
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
            ],
        ]);
    }

    /**
     * Get individual profile data by webpage column.
     * http://localhost:8000/profile/{webpage}
     */
    public function profileByWebpage(string $webpage): JsonResponse
    {
        $teacher = Teacher::where('webpage', $webpage)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'user.administrativeRoles',
                'department.faculty',
                'departments.faculty',
                'designation',
                'gender',
                'bloodGroup',
                'country',
                'religion',
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
            ])
            ->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found.',
            ], 404);
        }

        $data = [
            'id' => $teacher->id,
            'user_id' => $teacher->user_id,
            'employee_id' => $teacher->employee_id,
            'webpage' => $teacher->webpage,
            'first_name' => $teacher->first_name,
            'middle_name' => $teacher->middle_name,
            'last_name' => $teacher->last_name,
            'full_name' => $teacher->full_name,
            'email' => $teacher->user?->email ?? $teacher->secondary_email,
            'secondary_email' => $teacher->secondary_email,
            'phone' => $teacher->phone,
            'personal_phone' => $teacher->personal_phone,
            'extension_no' => $teacher->extension_no,
            'date_of_birth' => $teacher->date_of_birth ? ($teacher->date_of_birth instanceof \Carbon\Carbon ? $teacher->date_of_birth->toDateString() : $teacher->date_of_birth) : null,
            'gender' => $teacher->gender ? $teacher->gender->name : null,
            'blood_group' => $teacher->bloodGroup ? $teacher->bloodGroup->name : null,
            'country' => $teacher->country ? $teacher->country->name : null,
            'religion' => $teacher->religion ? $teacher->religion->name : null,
            'present_address' => $teacher->present_address,
            'permanent_address' => $teacher->permanent_address,
            'joining_date' => $teacher->joining_date ? ($teacher->joining_date instanceof \Carbon\Carbon ? $teacher->joining_date->toDateString() : $teacher->joining_date) : null,
            'work_location' => $teacher->work_location,
            'office_room' => $teacher->office_room,
            'photo' => $teacher->photo,
            'bio' => $teacher->bio,
            'research_interest' => $teacher->research_interest,
            'research_interests' => $teacher->research_interests,
            'profile_status' => $teacher->profile_status,
            'is_public' => (bool) $teacher->is_public,
            'is_active' => (bool) $teacher->is_active,
            'login_allowed' => (bool) $teacher->login_allowed,
            'is_archived' => (bool) $teacher->is_archived,
            'sort_order' => $teacher->sort_order,

            'designation' => $teacher->designation ? [
                'id' => $teacher->designation->id,
                'name' => $teacher->designation->name,
                'short_name' => $teacher->designation->short_name,
                'code' => $teacher->designation->code,
                'sort_order' => $teacher->designation->sort_order,
            ] : null,

            'employment_status' => $teacher->employmentStatus ? $teacher->employmentStatus->name : null,
            'job_type' => $teacher->jobType ? $teacher->jobType->name : null,

            // Primary assigned Department & Faculty
            'department' => $teacher->department ? [
                'id' => $teacher->department->id,
                'name' => $teacher->department->name,
                'short_name' => $teacher->department->short_name,
                'code' => $teacher->department->code,
                'description' => $teacher->department->description,
                'faculty' => $teacher->department->faculty ? [
                    'id' => $teacher->department->faculty->id,
                    'name' => $teacher->department->faculty->name,
                    'short_name' => $teacher->department->faculty->short_name,
                    'code' => $teacher->department->faculty->code,
                    'description' => $teacher->department->faculty->description,
                ] : null,
            ] : null,

            // All Assigned Departments & Faculties
            'assigned_departments' => $teacher->departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'short_name' => $dept->short_name,
                    'code' => $dept->code,
                    'pivot' => [
                        'job_type_id' => $dept->pivot->job_type_id ?? null,
                        'sort_order' => $dept->pivot->sort_order ?? null,
                        'assigned_by' => $dept->pivot->assigned_by ?? null,
                    ],
                    'faculty' => $dept->faculty ? [
                        'id' => $dept->faculty->id,
                        'name' => $dept->faculty->name,
                        'short_name' => $dept->faculty->short_name,
                        'code' => $dept->faculty->code,
                    ] : null,
                ];
            }),

            'administrative_roles' => ($teacher->user && $teacher->user->relationLoaded('administrativeRoles'))
                ? $teacher->user->administrativeRoles->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'short_name' => $r->short_name,
                    'pivot' => [
                        'department_id' => $r->pivot->department_id,
                        'faculty_id' => $r->pivot->faculty_id,
                        'start_date' => $r->pivot->start_date,
                        'end_date' => $r->pivot->end_date,
                        'is_active' => (bool) $r->pivot->is_active,
                    ],
                ])->values()->toArray()
                : [],

            'educations' => $teacher->educations->map(fn($e) => [
                'id' => $e->id,
                'degree_type' => $e->degreeType ? $e->degreeType->name : null,
                'country' => $e->country ? $e->country->name : 'Bangladesh',
                'result_type' => $e->resultType ? $e->resultType->name : null,
                'institution' => ($e->educationalInstitution ? $e->educationalInstitution->name : null) ?? $e->institution,
                'major' => ($e->majorRelation ? $e->majorRelation->name : null) ?? $e->major,
                'passing_year' => $e->passing_year,
                'duration' => $e->duration,
                'cgpa' => $e->cgpa,
                'scale' => $e->scale,
                'marks' => $e->marks,
                'grade' => $e->grade,
                'sort_order' => $e->sort_order,
            ]),

            'publications' => $teacher->publications->map(fn($p) => [
                'id' => $p->id,
                'type' => $p->type ? $p->type->name : null,
                'linkage' => $p->linkage ? $p->linkage->name : null,
                'quartile' => $p->quartile ? $p->quartile->name : null,
                'grant' => $p->grant ? $p->grant->name : null,
                'collaboration' => $p->collaboration ? $p->collaboration->name : null,
                'title' => $p->title,
                'journal_name' => $p->journal_name,
                'journal_link' => $p->journal_link,
                'publication_date' => $p->publication_date ? $p->publication_date->toDateString() : null,
                'publication_year' => $p->publication_year,
                'research_area' => $p->research_area,
                'h_index' => $p->h_index,
                'citescore' => $p->citescore,
                'impact_factor' => $p->impact_factor,
                'student_involvement' => (bool) $p->student_involvement,
                'keywords' => $p->keywords,
                'abstract' => $p->abstract,
                'status' => $p->status,
                'is_featured' => (bool) $p->is_featured,
                'sort_order' => $p->sort_order,
                'author_role' => $p->pivot ? $p->pivot->author_role : null,
                'incentive_amount' => $p->pivot ? $p->pivot->incentive_amount : null,
            ]),

            'research_projects' => $teacher->researchProjects->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'project_leader' => $r->project_leader,
                'funding_agency' => ($r->fundingAgencyOrganizationRelation ? $r->fundingAgencyOrganizationRelation->name : null) ?? $r->funding_agency,
                'budget' => $r->budget,
                'currency' => $r->currency,
                'role' => $r->role,
                'start_date' => $r->start_date ? $r->start_date->toDateString() : null,
                'end_date' => $r->end_date ? $r->end_date->toDateString() : null,
                'status' => $r->status,
                'outcome' => $r->outcome,
                'sort_order' => $r->sort_order,
            ]),

            'training_experiences' => $teacher->trainingExperiences->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'organization' => ($t->organizationRelation ? $t->organizationRelation->name : null) ?? $t->organization,
                'category' => $t->category,
                'duration_days' => $t->duration_days,
                'completion_date' => $t->completion_date ? $t->completion_date->toDateString() : null,
                'year' => $t->year,
                'country' => $t->countryRelation ? $t->countryRelation->name : 'Bangladesh',
                'certificate_url' => $t->certificate_url,
                'is_online' => (bool) $t->is_online,
                'description' => $t->description,
                'sort_order' => $t->sort_order,
            ]),

            'certifications' => $teacher->certifications->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'type' => $c->type,
                'issuing_authority' => ($c->issuingAuthorityOrganizationRelation ? $c->issuingAuthorityOrganizationRelation->name : null) ?? $c->issuing_authority,
                'issue_date' => $c->issue_date ? $c->issue_date->toDateString() : null,
                'expiry_date' => $c->expiry_date ? $c->expiry_date->toDateString() : null,
                'credential_id' => $c->credential_id,
                'credential_url' => $c->credential_url,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
            ]),

            'skills' => $teacher->skills->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'proficiency' => $s->proficiency,
                'sort_order' => $s->sort_order,
            ]),

            'teaching_areas' => $teacher->teachingAreas->map(fn($t) => [
                'id' => $t->id,
                'area' => $t->area,
                'description' => $t->description,
                'sort_order' => $t->sort_order,
            ]),

            'memberships' => $teacher->memberships->map(fn($m) => [
                'id' => $m->id,
                'organization' => $m->membershipOrganization ? $m->membershipOrganization->name : null,
                'membership_type' => $m->membershipType ? $m->membershipType->name : null,
                'membership_id' => $m->membership_id,
                'record_type' => $m->record_type,
                'position' => $m->position,
                'scope' => $m->scope,
                'url' => $m->url,
                'start_date' => $m->start_date ? $m->start_date->toDateString() : null,
                'end_date' => $m->end_date ? $m->end_date->toDateString() : null,
                'status' => $m->status,
                'description' => $m->description,
                'sort_order' => $m->sort_order,
                'is_active' => (bool) $m->is_active,
            ]),

            'awards' => $teacher->awards->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'awarding_body' => ($a->awardingBodyOrganizationRelation ? $a->awardingBodyOrganizationRelation->name : null) ?? $a->awarding_body,
                'type' => $a->type,
                'date' => $a->date ? $a->date->toDateString() : null,
                'year' => $a->year,
                'remarks' => $a->remarks,
                'attachment' => $a->attachment,
                'sort_order' => $a->sort_order,
            ]),

            'job_experiences' => $teacher->jobExperiences->map(fn($j) => [
                'id' => $j->id,
                'position' => ($j->positionRelation ? $j->positionRelation->name : null) ?? $j->position,
                'organization' => ($j->organizationRelation ? $j->organizationRelation->name : null) ?? $j->organization,
                'department' => $j->department,
                'location' => $j->location,
                'country' => ($j->countryRelation ? $j->countryRelation->name : null) ?? $j->country,
                'start_date' => $j->start_date ? $j->start_date->toDateString() : null,
                'end_date' => $j->end_date ? $j->end_date->toDateString() : null,
                'is_current' => (bool) $j->is_current,
                'responsibilities' => $j->responsibilities,
                'sort_order' => $j->sort_order,
            ]),

            'social_links' => $teacher->socialLinks->map(fn($s) => [
                'id' => $s->id,
                'platform' => $s->platform ? $s->platform->name : 'Social',
                'username' => $s->username,
                'url' => $s->url,
                'sort_order' => $s->sort_order,
            ]),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Step 1: Preview Overview of teacher import without saving to database.
     */
    public function previewTeacherImport(Request $request): JsonResponse
    {
        $rawPayload = $request->input('payload');

        // If no raw payload passed directly, search by employee_id / webpage / q
        if (empty($rawPayload)) {
            $employeeId = $request->input('employee_id');
            $webpage = $request->input('webpage');
            $q = $request->input('q') ?? $employeeId ?? $webpage;

            $connName = config('database.connections.api_only_db') ? 'api_only_db' : config('database.default');

            $teacherObj = null;

            if ($webpage) {
                $teacherObj = Teacher::on($connName)->where('webpage', $webpage)->first();
            }
            if (!$teacherObj && $employeeId) {
                $teacherObj = Teacher::on($connName)->where('employee_id', $employeeId)->first();
            }
            if (!$teacherObj && $q) {
                $teacherObj = Teacher::on($connName)
                    ->where('employee_id', $q)
                    ->orWhere('webpage', $q)
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->first();
            }

            if ($teacherObj) {
                $teacherObj->load([
                    'user',
                    'educations',
                    'skills',
                    'trainingExperiences',
                    'certifications',
                    'publications',
                    'jobExperiences',
                    'awards',
                    'memberships',
                    'socialLinks',
                    'teachingAreas',
                ]);

                $rawPayload = [
                    'data' => array_merge($teacherObj->toArray(), [
                        'email' => $teacherObj->user?->email ?? $teacherObj->secondary_email,
                        'employee_id' => $teacherObj->employee_id,
                        'webpage' => $teacherObj->webpage,
                        'first_name' => $teacherObj->first_name,
                        'last_name' => $teacherObj->last_name,
                        'phone' => $teacherObj->phone,
                        'educations' => $teacherObj->educations->map(fn($e) => [
                            'institution' => $e->institution,
                            'major' => $e->major,
                            'passing_year' => $e->passing_year,
                            'duration' => $e->duration,
                            'cgpa' => $e->cgpa,
                            'scale' => $e->scale,
                            'grade' => $e->grade,
                        ])->toArray(),
                        'skills' => $teacherObj->skills->map(fn($s) => [
                            'name' => $s->name,
                            'proficiency' => $s->proficiency,
                        ])->toArray(),
                        'job_experiences' => $teacherObj->jobExperiences->map(fn($j) => [
                            'position' => $j->position,
                            'organization' => $j->organization,
                            'department' => $j->department,
                            'start_date' => $j->start_date ? $j->start_date->toDateString() : null,
                            'end_date' => $j->end_date ? $j->end_date->toDateString() : null,
                        ])->toArray(),
                        'training_experiences' => $teacherObj->trainingExperiences->map(fn($t) => [
                            'title' => $t->title,
                            'organization' => $t->organization,
                            'category' => $t->category,
                            'duration_days' => $t->duration_days,
                            'completion_date' => $t->completion_date ? $t->completion_date->toDateString() : null,
                        ])->toArray(),
                        'certifications' => $teacherObj->certifications->map(fn($c) => [
                            'title' => $c->title,
                            'issuing_authority' => $c->issuing_authority,
                            'credential_id' => $c->credential_id,
                        ])->toArray(),
                        'publications' => $teacherObj->publications->map(fn($p) => [
                            'title' => $p->title,
                            'journal_name' => $p->journal_name,
                            'publication_year' => $p->publication_year,
                        ])->toArray(),
                        'social_links' => $teacherObj->socialLinks->map(fn($s) => [
                            'username' => $s->username,
                            'url' => $s->url,
                        ])->toArray(),
                    ])
                ];
            }

            // Fallback: check profile.json or legacy search
            if (empty($rawPayload)) {
                $sampleFile = public_path('documents/profile.json');
                if (file_exists($sampleFile)) {
                    $rawPayload = json_decode(file_get_contents($sampleFile), true);
                }
            }
        }

        if (empty($rawPayload)) {
            return response()->json([
                'success' => false,
                'message' => 'No teacher data found to preview.',
            ], 404);
        }

        $integrationService = app(\App\Services\IntegrationService::class);
        $mappingSlug = \App\Models\Setting::get('teacher_integration_mapping', 'erp_teacher_profile');
        $overview = $integrationService->transform((array) $rawPayload, $mappingSlug);

        $empId = $overview['Teacher']['employee_id'] ?? null;
        $wp = $overview['Teacher']['webpage'] ?? null;
        $email = $overview['User']['email'] ?? $overview['Teacher']['secondary_email'] ?? null;

        $existingTeacher = Teacher::query()
            ->when($empId, fn($q) => $q->where('employee_id', $empId))
            ->when($wp, fn($q) => $q->orWhere('webpage', $wp))
            ->when($email, fn($q) => $q->orWhere('secondary_email', $email))
            ->first();

        return response()->json([
            'success' => true,
            'preview' => true,
            'exists_locally' => (bool) $existingTeacher,
            'action' => $existingTeacher ? 'update' : 'create',
            'teacher_id' => $existingTeacher ? $existingTeacher->id : null,
            'raw_payload' => $rawPayload,
            'overview' => $overview,
        ]);
    }

    /**
     * Step 2: Confirm and insert/update teacher profile and all child relations into database.
     */
    public function confirmTeacherImport(Request $request): JsonResponse
    {
        $rawPayload = $request->input('payload');

        if (empty($rawPayload)) {
            // Check if preview payload was passed
            $employeeId = $request->input('employee_id');
            $webpage = $request->input('webpage');

            if ($webpage || $employeeId) {
                // Fetch preview response payload
                $previewRes = $this->previewTeacherImport($request);
                $resData = $previewRes->getData(true);
                if (isset($resData['raw_payload'])) {
                    $rawPayload = $resData['raw_payload'];
                }
            }
        }

        if (empty($rawPayload)) {
            return response()->json([
                'success' => false,
                'message' => 'Valid teacher payload is required for import.',
            ], 422);
        }

        $integrationService = app(\App\Services\IntegrationService::class);
        $teacher = $integrationService->importOrUpdateTeacher((array) $rawPayload, 'erp_teacher_profile');

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process teacher import.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher profile and relational data imported successfully.',
            'teacher_id' => $teacher->id,
            'webpage' => $teacher->webpage,
            'employee_id' => $teacher->employee_id,
            'full_name' => $teacher->full_name,
            'educations_count' => $teacher->educations()->count(),
            'skills_count' => $teacher->skills()->count(),
            'training_experiences_count' => $teacher->trainingExperiences()->count(),
        ]);
    }
}

