<?php

namespace App\Services;

use App\Models\IntegrationMapping;
use Illuminate\Support\Arr;

class IntegrationService
{
    /**
     * Transform the raw API data based on the mapping configuration.
     *
     * @param array $data The raw record from the external API (or envelope e.g. ['data' => ...]).
     * @param string $slug The slug of the IntegrationMapping to use.
     * @return array The transformed data structure grouped by model.
     */
    public function transform(array $data, string $slug): array
    {
        $mapping = IntegrationMapping::where('slug', $slug)->first();

        if (!$mapping || empty($mapping->mapping_config)) {
            return $data;
        }

        $transformed = [
            'User' => [],
            'Teacher' => [],
            'Relations' => [],
        ];

        // Unwrap envelope if data is under 'data'
        $payload = (isset($data['data']) && is_array($data['data']) && !isset($data['id'])) ? $data['data'] : $data;

        foreach ($mapping->mapping_config as $config) {
            $sourceField = $config['source_field'] ?? null;
            $targetModel = $config['target_model'] ?? null;
            $targetField = $config['target_field'] ?? null;

            if (!$sourceField || !$targetModel || !$targetField) {
                continue;
            }

            // Check if target model is a child relation model
            if (!in_array($targetModel, ['User', 'Teacher'])) {
                // Determine source array path (e.g. "educations" or "data.educations")
                // If sourceField contains dot notation like "educations.degree_type"
                $parts = explode('.', $sourceField);
                if (count($parts) >= 2) {
                    $arrayKey = $parts[0];
                    $childKey = implode('.', array_slice($parts, 1));
                    $arrayData = Arr::get($payload, $arrayKey) ?? Arr::get($data, $arrayKey);

                    if (is_array($arrayData)) {
                        if (!isset($transformed['Relations'][$targetModel])) {
                            $transformed['Relations'][$targetModel] = [];
                        }

                        foreach ($arrayData as $index => $item) {
                            $val = is_array($item) ? Arr::get($item, $childKey) : null;
                            $val = $this->resolveValue($val, $targetField, $config);
                            if ($val !== null) {
                                $transformed['Relations'][$targetModel][$index][$targetField] = $val;
                            }
                        }
                    }
                }
            } else {
                // Direct model mapping (User or Teacher)
                $value = Arr::get($payload, $sourceField) ?? Arr::get($data, $sourceField);
                $value = $this->resolveValue($value, $targetField, $config);
                if ($value !== null) {
                    $transformed[$targetModel][$targetField] = $value;
                }
            }
        }

        $transformed['Teacher'] = $this->splitPackedName($transformed['Teacher']);

        return $transformed;
    }

    /**
     * Split a full name that arrived in one field.
     *
     * The HR system frequently sends the whole name as first_name and leaves
     * last_name empty — "Md. Fokhray Hossain", nothing else. last_name is a
     * required field on the teacher form, so the import filled the form and
     * then refused to save it.
     *
     * Only fires when there is nothing to lose: a last name already mapped from
     * the source is never touched, and a single-word first name is left alone
     * because there is no second part to take.
     *
     * The surname is the last word and everything before it is the given name.
     * middle_name is left alone — it is optional on the record, and inferring it
     * from the second word is what reduced "Md. Fokhray Hossain" to a first name
     * of "Md.".
     *
     * @param array $teacher the mapped Teacher attributes
     */
    protected function splitPackedName(array $teacher): array
    {
        $first = trim((string) ($teacher['first_name'] ?? ''));

        if ($first === '' || filled($teacher['last_name'] ?? null)) {
            return $teacher;
        }

        if (!str_contains($first, ' ')) {
            return $teacher;
        }

        $parts = \App\Support\TeacherName::split($first);

        $teacher['first_name'] = $parts['first_name'];
        $teacher['last_name'] = $parts['last_name'];

        return $teacher;
    }

    /**
     * Turn text into a foreign key when the target column asks for one.
     *
     * The HR API sends "Male", "ITM", "Active" where the teachers table stores
     * gender_id, department_id, employment_status_id. Any column ending in _id
     * is looked up by name or code in the table its name implies, so no mapping
     * row needs extra configuration for it.
     *
     * Every other value passes straight through, which is why the mappings
     * written before this existed behave exactly as they did.
     *
     * @param array $config the mapping row, which may carry a lookup_model override
     */
    protected function resolveValue(mixed $value, string $targetField, array $config): mixed
    {
        if ($value === null) {
            return null;
        }

        $explicitModel = $config['lookup_model'] ?? null;

        /*
         * The mapping row's own answer wins when it has one. Rules written
         * before the flag existed carry no answer, and fall back to deciding
         * from the column name — which is what they already did.
         */
        $isRelation = array_key_exists('is_relation', $config)
            ? (bool) $config['is_relation']
            : (\App\Support\LookupResolver::handles($targetField) || filled($explicitModel));

        if (!$isRelation) {
            return $value;
        }

        return \App\Support\LookupResolver::resolve(
            $value,
            $targetField,
            $explicitModel,
            $config['match_column'] ?? null,
        );
    }

    /**
     * Import or update a Teacher and all child relations from API response.
     *
     * @param array $apiResponse Raw response array from API.
     * @param string $slug IntegrationMapping slug.
     * @return \App\Models\Teacher|null
     */
    public function importOrUpdateTeacher(array $apiResponse, string $slug): ?\App\Models\Teacher
    {
        $transformed = $this->transform($apiResponse, $slug);

        $userData = $transformed['User'] ?? [];
        $teacherData = $transformed['Teacher'] ?? [];
        $relations = $transformed['Relations'] ?? [];

        if (empty($teacherData) && empty($userData)) {
            return null;
        }

        // 1. Create/Update User
        $userEmail = $userData['email'] ?? $teacherData['secondary_email'] ?? null;
        $userName = $userData['name'] ?? trim(($teacherData['first_name'] ?? '') . ' ' . ($teacherData['last_name'] ?? ''));

        if (!$userEmail && !empty($teacherData['employee_id'])) {
            $userEmail = $teacherData['employee_id'] . '@daffodilvarsity.edu.bd';
        }

        if (!$userEmail) {
            return null;
        }

        $user = \App\Models\User::updateOrCreate(
            ['email' => $userEmail],
            [
                'name' => $userName ?: 'Teacher',
                'password' => $userData['password'] ?? bcrypt('12345678'),
                'is_active' => true,
            ]
        );

        // 2. Create/Update Teacher
        $teacherData['user_id'] = $user->id;
        if (!isset($teacherData['department_id'])) {
            // Default to department 1 if not mapped/provided
            $defaultDept = \App\Models\Department::first();
            $teacherData['department_id'] = $defaultDept ? $defaultDept->id : 1;
        }
        if (!isset($teacherData['designation_id'])) {
            $defaultDesig = \App\Models\Designation::first();
            $teacherData['designation_id'] = $defaultDesig ? $defaultDesig->id : 1;
        }
        if (!isset($teacherData['first_name'])) {
            $teacherData['first_name'] = $user->name;
        }

        $employeeId = $teacherData['employee_id'] ?? null;
        $webpage = $teacherData['webpage'] ?? null;

        $teacherQuery = \App\Models\Teacher::where('user_id', $user->id);
        if ($employeeId) {
            $teacherQuery->orWhere('employee_id', $employeeId);
        }
        if ($webpage) {
            $teacherQuery->orWhere('webpage', $webpage);
        }
        $teacher = $teacherQuery->first();

        if ($teacher) {
            $teacher->update($teacherData);
        } else {
            $teacher = \App\Models\Teacher::create($teacherData);
        }

        // 3. Process Child Relations
        $relationModelMap = [
            'Education' => 'educations',
            'TrainingExperience' => 'trainingExperiences',
            'Certification' => 'certifications',
            'Skill' => 'skills',
            'TeachingArea' => 'teachingAreas',
            'Membership' => 'memberships',
            'Award' => 'awards',
            'JobExperience' => 'jobExperiences',
            'SocialLink' => 'socialLinks',
            'ResearchProject' => 'researchProjects',
        ];

        foreach ($relations as $modelName => $records) {
            if (empty($records) || !isset($relationModelMap[$modelName])) {
                continue;
            }

            $relationName = $relationModelMap[$modelName];
            if (method_exists($teacher, $relationName)) {
                // Delete existing and replace with new mapped array
                $teacher->$relationName()->delete();

                foreach ($records as $record) {
                    if (!empty(array_filter($record))) {
                        $teacher->$relationName()->create($record);
                    }
                }
            }
        }

        return $teacher;
    }
}
