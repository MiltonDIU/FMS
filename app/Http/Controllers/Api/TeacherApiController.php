<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeacherApiController extends Controller
{
    /**
     * Search for a teacher by employee_id in legacy database.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string',
        ]);

        $query = trim($request->input('q'));

        try {
            $legacyQuery = DB::connection('old_db')
                ->table('dfd_add')
                ->leftJoin('teacher', 'dfd_add.teacher_id', '=', 'teacher.id')
                ->leftJoin('faculty', 'dfd_add.faculty_id', '=', 'faculty.faculty_id')
                ->leftJoin('department', 'dfd_add.department_id', '=', 'department.department_id')
                ->select(
                    'teacher.id',
                    'teacher.name',
                    'teacher.employeeID',
                    'teacher.email',
                    'teacher.phone',
                    'teacher.cell',
                    'teacher.webpage',
                    'dfd_add.faculty_id',
                    'dfd_add.department_id',
                    'dfd_add.Teacher_type',
                    'dfd_add.is_part_time',
                    'faculty.short_name as faculty_slug',
                    'department.dslug as department_slug',
                    'teacher.academicQualification',
                    'teacher.trainingExperience',
                    'teacher.professional_experience',
                    'teacher.teachingArea',
                    'teacher.awardScholarship',
                    'teacher.membership',
                    'teacher.previousEmployment',
                )->whereNotNull('teacher.employeeID')
                ->where('teacher.employeeID', '!=', '');

            // 🔎 Search only if q has value
            if (!empty($query)) {
                $legacyQuery->where(function ($q2) use ($query) {
                    $q2->where('teacher.name', 'LIKE', "%{$query}%")
                        ->orWhere('teacher.employeeID', 'LIKE', "{$query}%")
                        ->orWhere('teacher.email', 'LIKE', "%{$query}%")
                        ->orWhere('teacher.phone', 'LIKE', "%{$query}%")
                        ->orWhere('teacher.cell', 'LIKE', "%{$query}%");
                });
            }

            $legacyTeacher = $legacyQuery
                ->groupBy('teacher.employeeID')
                ->limit(20)
                ->get();

            if ($legacyTeacher->isNotEmpty()) {
                $localEmployeeIds = Teacher::pluck('employee_id')->toArray();

                $integrationService = app(\App\Services\IntegrationService::class);

                $transformedData = $legacyTeacher->map(function ($item) use ($integrationService, $localEmployeeIds) {
                    $itemArray = (array) $item;

                    if (isset($itemArray['name'])) {
                        $nameData = self::transformName($itemArray['name']);
                        $itemArray = array_merge($itemArray, $nameData);
                    }

                    $itemArray['teacher_type_mapped'] = null;

                    if (isset($itemArray['is_part_time']) && $itemArray['is_part_time'] == 1) {
                        $itemArray['teacher_type_mapped'] = 2;
                    } elseif (isset($itemArray['Teacher_type'])) {
                        if ($itemArray['Teacher_type'] == 1) {
                            $itemArray['teacher_type_mapped'] = 1;
                        } elseif ($itemArray['Teacher_type'] == 0) {
                            $itemArray['teacher_type_mapped'] = 3;
                        }
                    }

                    $itemArray['exists_locally'] = in_array($itemArray['employeeID'] ?? null, $localEmployeeIds);

                    return $itemArray;
                });

                return response()->json([
                    'success' => true,
                    'source' => 'legacy',
                    'message' => 'Teachers found.',
                    'data' => $transformedData,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No teacher found.',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Transform legacy single name field into first_name, middle_name, last_name
     *
     * Rules:
     * - 1 word: Add "Mx." + name as first_name, name as last_name
     * - 2 words: first_name and last_name
     * - 3+ words: first_name, middle_name(s), last_name
     *
     * @param string $fullName
     * @return array
     */
    public static function transformName(string $fullName): array
    {
        $fullName = trim($fullName);
        $parts = preg_split('/\s+/', $fullName);
        $partCount = count($parts);

        if ($partCount === 1) {
            // Single word: Add Mx. + name as first_name, use name as last_name
            return [
                'first_name' => 'Mx. ' . $parts[0],
                'middle_name' => null,
                'last_name' => $parts[0],
            ];
        } elseif ($partCount === 2) {
            // Two words: first_name and last_name
            return [
                'first_name' => $parts[0],
                'middle_name' => null,
                'last_name' => $parts[1],
            ];
        } else {
            // Three or more words: first, middle(s), last
            $firstName = $parts[0];
            $lastName = array_pop($parts);
            array_shift($parts); // Remove first name from parts
            $middleName = implode(' ', $parts);

            return [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
            ];
        }
    }
}
