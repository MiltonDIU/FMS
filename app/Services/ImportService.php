<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportService
{
    /**
     * Parse a JSON file and return a flat list of keys.
     *
     * @param array $jsonContent
     * @return array
     */
    public function getJsonKeys(array $jsonContent): array
    {
        if (empty($jsonContent)) {
            return [];
        }

        // Take the first item to analyze keys
        $firstItem = is_array($jsonContent) ? ($jsonContent[0] ?? []) : [];
        
        return array_keys($this->flatten($firstItem));
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $new_key = $prefix . ($prefix ? '.' : '') . $key;

            if (is_array($value) && !empty($value)) {
                // Check if it's an associative array or a list
                $isAssoc = array_keys($value) !== range(0, count($value) - 1);
                
                if ($isAssoc) {
                    $result = array_merge($result, $this->flatten($value, $new_key));
                } else {
                    // It's a list (e.g., educations), keep the key as a base
                    // We might want to drill down into the first item of the list to get keys there too
                    $result[$new_key] = $value;
                    
                    if (isset($value[0]) && is_array($value[0])) {
                         $result = array_merge($result, $this->flatten($value[0], $new_key . '.*'));
                    }
                }
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }

    /**
     * Import data based on mapping.
     *
     * @param array $data List of records (rows)
     * @param array $mapping ['json_key' => 'table.column']
    /**
     * Import teachers using standard schema.
     * Returns success count and list of failed records.
     *
     * @param array $data List of records
     * @return array ['success_count' => int, 'failed_records' => array]
     */
    public function importTeachers(array $data): array
    {
        $successCount = 0;
        $failedRecords = [];

        foreach ($data as $index => $record) {
            DB::beginTransaction();
            try {
                // Flatten to handle nested structures easily if needed, 
                // but for standard schema we might expect a specific structure.
                // Let's assume the standard schema matches the "Teacher Model" structure we designed.
                
                // 1. Validate / Extract User Data
                $email = $record['user']['email'] ?? $record['email'] ?? null;
                $name  = $record['user']['name'] ?? $record['name'] ?? 'Unknown';
                
                if (empty($email)) {
                    throw new \Exception("Email is required.");
                }

                // 2. Validate / Extract Teacher Data
                $employeeId = $record['employee_id'] ?? $record['teacher']['employee_id'] ?? null;
                
                if (empty($employeeId)) {
                    throw new \Exception("Employee ID is required.");
                }

                // Create/Update User
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                    ]
                );
                
                if (!$user->hasRole('Teacher')) {
                    $user->assignRole('Teacher');
                }

                // Prepare Teacher Data
                // We merge the flat record with specific nested fields if they exist at top level
                // For standard import, we expect fields to be top-level or in 'profile'/'teacher' keys?
                // Let's support the detailed JSON structure we created: top-level fields + nested arrays.
                
                $teacherData = $record;
                unset($teacherData['user']); // Remove user key from teacher data
                unset($teacherData['educations']);
                unset($teacherData['job_experiences']);
                unset($teacherData['publications']);
                
                // Map known sub-keys if they exist in a wrapper like 'profile'
                if (isset($record['profile']) && is_array($record['profile'])) {
                    $teacherData = array_merge($teacherData, $record['profile']);
                }

                $teacherData['user_id'] = $user->id;
                
                // Resolve Relations (Designation, Department, etc.)
                $this->resolveRelationIds($teacherData);

                $teacher = Teacher::updateOrCreate(
                    ['employee_id' => $employeeId],
                    $teacherData
                );

                // Process Nested Relations
                // 1. Educations
                if (!empty($record['educations'])) {
                    $teacher->educations()->delete(); 
                    foreach ($record['educations'] as $edu) {
                        if (empty($edu['degree_type_id']) || empty($edu['institution'])) continue; // Skip invalid
                        $this->resolveRelationIds($edu);
                        $teacher->educations()->create($edu);
                    }
                }
                
                // 2. Job Experiences
                if (!empty($record['job_experiences'])) {
                    $teacher->jobExperiences()->delete();
                    foreach ($record['job_experiences'] as $job) {
                        if (empty($job['position']) || empty($job['organization'])) continue;
                        $this->resolveRelationIds($job);
                        $teacher->jobExperiences()->create($job);
                    }
                }
                
                // 3. Awards
                if (!empty($record['awards'])) {
                    $teacher->awards()->delete();
                    foreach ($record['awards'] as $award) {
                        if (empty($award['title'])) continue;
                        $this->resolveRelationIds($award);
                        $teacher->awards()->create($award);
                    }
                }

                // 4. Certifications
                if (!empty($record['certifications'])) {
                    $teacher->certifications()->delete();
                    foreach ($record['certifications'] as $cert) {
                        if (empty($cert['title'])) continue;
                        $this->resolveRelationIds($cert);
                        $teacher->certifications()->create($cert);
                    }
                }

                // 5. Training Experiences
                if (!empty($record['training_experiences'])) {
                    $teacher->trainingExperiences()->delete();
                    foreach ($record['training_experiences'] as $training) {
                        if (empty($training['title'])) continue;
                        $this->resolveRelationIds($training);
                        $teacher->trainingExperiences()->create($training);
                    }
                }

                // 6. Skills
                if (!empty($record['skills'])) {
                    $teacher->skills()->delete();
                    foreach ($record['skills'] as $skill) {
                        if (empty($skill['name'])) continue;
                        $this->resolveRelationIds($skill);
                        $teacher->skills()->create($skill);
                    }
                }

                // 7. Teaching Areas
                if (!empty($record['teaching_areas'])) {
                    $teacher->teachingAreas()->delete();
                    foreach ($record['teaching_areas'] as $area) {
                        if (empty($area['area'])) continue;
                        $this->resolveRelationIds($area);
                        $teacher->teachingAreas()->create($area);
                    }
                }

                // 8. Memberships
                if (!empty($record['memberships'])) {
                    $teacher->memberships()->delete();
                    foreach ($record['memberships'] as $membership) {
                        if (empty($membership['membership_organization_id'])) continue;
                        $this->resolveRelationIds($membership);
                        $teacher->memberships()->create($membership);
                    }
                }

                // 9. Social Links
                if (!empty($record['social_links'])) {
                    $teacher->socialLinks()->delete();
                    foreach ($record['social_links'] as $link) {
                        if (empty($link['url'])) continue;
                        $this->resolveRelationIds($link);
                        // Social Media Platform ID resolution might need distinct logic if names are used
                        $teacher->socialLinks()->create($link);
                    }
                }

                DB::commit();
                $successCount++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                // Add error to record and push to failures
                $record['_error'] = $e->getMessage();
                $failedRecords[] = $record;
            }
        }

        return [
            'success_count' => $successCount,
            'failed_records' => $failedRecords,
        ];
    }

    /**
     * Resolve relationship IDs from names (e.g., Department Name -> department_id).
     */
    private function resolveRelationIds(array &$data): void
    {
        // Fields ending with _id that are NOT foreign keys (they're text fields)
        $excludedFields = [
            'credential_id',      // Certification credential ID (text)
            'membership_id',      // Membership ID (text)
            'source_reference_id', // Job Experience source reference (text)
            'erp_id',             // ERP system ID (text)
        ];

        foreach ($data as $key => $value) {
            // Skip if this is an excluded text field
            if (in_array($key, $excludedFields)) {
                continue;
            }

            // Only process if it ends with _id and value is a string (name)
            if (str_ends_with($key, '_id') && is_string($value) && !is_numeric($value)) {
                $relationName = str_replace('_id', '', $key);
                $tableName = Str::plural($relationName); // e.g. designation -> designations
                
                // Special cases for table names if needed
                if ($key === 'country_id') $tableName = 'countries';
                if ($key === 'category_id') $tableName = 'categories';

                // Try to find the record by name
                $id = DB::table($tableName)->where('name', $value)->value('id');
                
                if ($id) {
                    $data[$key] = $id; // Replace string with ID
                } else {
                    // If not found, throw an error to prevent invalid data
                    throw new \Exception("Could not find {$relationName} with name '{$value}' in {$tableName} table. Please use exact name or numeric ID.");
                }
            }
        }
    }
}
