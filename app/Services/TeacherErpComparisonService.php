<?php

namespace App\Services;

use App\Helpers\FormPayloadResolver;
use App\Models\BloodGroup;
use App\Models\Country;
use App\Models\DegreeType;
use App\Models\Gender;
use App\Models\MembershipType;
use App\Models\Organization;
use App\Models\Religion;
use App\Models\Setting;
use App\Models\SocialMediaPlatform;
use App\Models\Teacher;
use App\Models\User;
use App\Support\RelationMerge;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TeacherErpComparisonService
{
    public const MODE_MERGE = 'merge';
    public const MODE_OVERRIDE = 'override';

    /**
     * Cache comparison per teacher within request lifecycle
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $cache = [];

    public function __construct(
        protected HrApiService $hrApi,
        protected IntegrationService $integration,
    ) {
    }

    /**
     * Check if the global setting allows teachers to sync with ERP.
     */
    public static function isTeacherSyncAllowed(): bool
    {
        return filter_var(Setting::get('allow_teacher_erp_sync', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine if a user can access the ERP comparison/sync for the given teacher.
     */
    public static function canAccess(Teacher $teacher, ?User $user = null): bool
    {
        if (blank($teacher->employee_id)) {
            return false;
        }

        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        // Admins can always compare and sync ERP data
        if ($user->can('View:SystemSettings', Setting::class) || $user->can('Update:Teacher')) {
            return true;
        }

        // If the logged-in user is this teacher, check the toggle
        if ($user->isTeacher() && $user->teacher?->id === $teacher->id) {
            return static::isTeacherSyncAllowed();
        }

        return false;
    }

    /**
     * Definition of supported comparison sections.
     */
    public static function getSectionsDefinition(): array
    {
        return [
            'personal_details' => [
                'label' => 'Personal Details',
                'description' => 'Names, gender, date of birth, blood group, nationality, religion, and biography',
                'type' => 'scalar',
                'fields' => [
                    'first_name' => 'First Name',
                    'middle_name' => 'Middle Name',
                    'last_name' => 'Last Name',
                    'gender_id' => 'Gender',
                    'date_of_birth' => 'Date of Birth',
                    'blood_group_id' => 'Blood Group',
                    'country_id' => 'Nationality',
                    'religion_id' => 'Religion',
                    'bio' => 'Biography',
                ],
            ],
            'contact_info' => [
                'label' => 'Contact & Location',
                'description' => 'Phone numbers, secondary email, office room, webpage, and addresses',
                'type' => 'scalar',
                'fields' => [
                    'phone' => 'Work Phone',
                    'personal_phone' => 'Personal Phone',
                    'secondary_email' => 'Secondary Email',
                    'office_room' => 'Office Room',
                    'webpage' => 'Webpage Slug',
                    'present_address' => 'Present Address',
                    'permanent_address' => 'Permanent Address',
                ],
            ],
            'educations' => [
                'label' => 'Educational Qualifications',
                'description' => 'Degrees, institutions, passing years, results, and majors',
                'type' => 'relation',
                'relation' => 'educations',
            ],
            'job_experiences' => [
                'label' => 'Job Experiences',
                'description' => 'Previous and current employment records and roles',
                'type' => 'relation',
                'relation' => 'jobExperiences',
            ],
            'training_experiences' => [
                'label' => 'Training Experiences',
                'description' => 'Professional training, workshops, and courses attended',
                'type' => 'relation',
                'relation' => 'trainingExperiences',
            ],
            'awards' => [
                'label' => 'Awards & Honors',
                'description' => 'Recognitions, scholarships, and academic awards',
                'type' => 'relation',
                'relation' => 'awards',
            ],
            'certifications' => [
                'label' => 'Certifications',
                'description' => 'Professional credentials and certifications',
                'type' => 'relation',
                'relation' => 'certifications',
            ],
            'skills' => [
                'label' => 'Skills & Competencies',
                'description' => 'Technical and specialized expertise',
                'type' => 'relation',
                'relation' => 'skills',
            ],
            'teaching_areas' => [
                'label' => 'Teaching Areas',
                'description' => 'Courses and subjects taught',
                'type' => 'relation',
                'relation' => 'teachingAreas',
            ],
            'memberships' => [
                'label' => 'Professional Memberships',
                'description' => 'Memberships in professional bodies and organizations',
                'type' => 'relation',
                'relation' => 'memberships',
            ],
            'social_links' => [
                'label' => 'Social & Academic Links',
                'description' => 'Profiles on LinkedIn, Google Scholar, GitHub, etc.',
                'type' => 'relation',
                'relation' => 'socialLinks',
            ],
        ];
    }

    /**
     * Fetch ERP data for the given teacher and resolve it into form format.
     *
     * @throws \RuntimeException
     */
    public function fetchErpData(Teacher $teacher): ?array
    {
        $employeeId = trim((string) $teacher->employee_id);

        if ($employeeId === '') {
            throw new \RuntimeException('Teacher does not have an Employee ID assigned.');
        }

        if (! $this->hrApi->isConfigured()) {
            throw new \RuntimeException('The ERP/HR API is not configured yet. Please configure it in System Settings.');
        }

        $profile = $this->hrApi->getTeacherProfile($employeeId);

        if ($profile === null) {
            throw new \RuntimeException("No profile was found in the ERP system for Employee ID: {$employeeId}.");
        }

        $slug = (string) Setting::get('teacher_integration_mapping', 'erp_teacher_profile');
        $overview = $this->integration->transform($profile, $slug);

        return FormPayloadResolver::resolveForForm($overview, $teacher);
    }

    /**
     * Build side-by-side comparison between FMS and ERP data.
     *
     * @return array{
     *     teacher: Teacher,
     *     employee_id: string,
     *     sections: array<string, array<string, mixed>>,
     *     summary: array{total: int, different: int, matching: int, new: int},
     *     incoming: array<string, mixed>
     * }
     */
    public function compare(Teacher $teacher, ?array $incoming = null): array
    {
        if (isset($this->cache[$teacher->id]) && $incoming === null) {
            return $this->cache[$teacher->id];
        }

        if ($incoming === null) {
            $incoming = $this->fetchErpData($teacher);
        }

        $teacher->loadMissing([
            'gender',
            'bloodGroup',
            'country',
            'religion',
            'educations.educationalInstitution',
            'educations.degreeType',
                        'jobExperiences',
            'trainingExperiences',
            'awards',
            'certifications',
            'skills',
            'teachingAreas',
            'memberships.membershipOrganization',
            'memberships.membershipType',
            'socialLinks.platform',
        ]);

        $definitions = static::getSectionsDefinition();
        $sections = [];
        $totalDiffCount = 0;
        $totalNewCount = 0;
        $totalMatchCount = 0;

        foreach ($definitions as $secKey => $secDef) {
            if ($secDef['type'] === 'scalar') {
                $comp = $this->compareScalarSection($teacher, $secDef, $incoming);
            } else {
                $comp = $this->compareRelationSection($teacher, $secDef, $incoming);
            }

            $sections[$secKey] = $comp;

            if ($comp['status'] === 'different') {
                $totalDiffCount++;
            } elseif ($comp['status'] === 'new') {
                $totalNewCount++;
            } elseif ($comp['status'] === 'match') {
                $totalMatchCount++;
            }
        }

        $result = [
            'teacher' => $teacher,
            'employee_id' => (string) $teacher->employee_id,
            'sections' => $sections,
            'summary' => [
                'total' => count($definitions),
                'different' => $totalDiffCount,
                'new' => $totalNewCount,
                'matching' => $totalMatchCount,
            ],
            'incoming' => $incoming,
        ];

        return $this->cache[$teacher->id] = $result;
    }

    /**
     * Compare scalar fields for a section.
     */
    protected function compareScalarSection(Teacher $teacher, array $secDef, array $incoming): array
    {
        $fields = [];
        $hasDifferences = false;
        $fmsPopulated = 0;
        $erpPopulated = 0;

        foreach ($secDef['fields'] as $fieldKey => $fieldLabel) {
            $fmsVal = $teacher->getAttribute($fieldKey);
            $erpVal = $incoming[$fieldKey] ?? null;

            if (filled($fmsVal)) {
                $fmsPopulated++;
            }
            if (filled($erpVal)) {
                $erpPopulated++;
            }

            $formattedFms = $this->formatFieldValue($fieldKey, $fmsVal, $teacher);
            $formattedErp = $this->formatFieldValue($fieldKey, $erpVal, null);

            $isDifferent = ! RelationMerge::sameValue($fmsVal, $erpVal);

            if ($isDifferent) {
                $hasDifferences = true;
            }

            $fields[] = [
                'key' => $fieldKey,
                'label' => $fieldLabel,
                'fms_value' => $formattedFms,
                'erp_value' => $formattedErp,
                'raw_fms' => $fmsVal,
                'raw_erp' => $erpVal,
                'is_different' => $isDifferent,
                'status' => match (true) {
                    blank($fmsVal) && filled($erpVal) => 'new_in_erp',
                    filled($fmsVal) && blank($erpVal) => 'fms_only',
                    $isDifferent => 'different',
                    default => 'identical',
                },
            ];
        }

        $status = match (true) {
            $fmsPopulated === 0 && $erpPopulated > 0 => 'new',
            $erpPopulated === 0 && $fmsPopulated > 0 => 'fms_only',
            $hasDifferences => 'different',
            default => 'match',
        };

        return [
            'key' => $secDef['label'],
            'label' => $secDef['label'],
            'description' => $secDef['description'],
            'type' => 'scalar',
            'status' => $status,
            'has_differences' => $hasDifferences,
            'fms_count' => $fmsPopulated,
            'erp_count' => $erpPopulated,
            'fields' => $fields,
        ];
    }

    /**
     * Compare relation records for a section.
     */
    protected function compareRelationSection(Teacher $teacher, array $secDef, array $incoming): array
    {
        $relName = $secDef['relation'];
        $existingModels = $teacher->getRelationValue($relName);
        $existingRows = is_iterable($existingModels)
            ? collect($existingModels)->map(fn ($m) => $m instanceof \Illuminate\Database\Eloquent\Model ? $m->toArray() : (array) $m)->toArray()
            : [];

        $incomingRows = is_array($incoming[$relName] ?? null) ? $incoming[$relName] : [];

        $matchKeys = RelationMerge::keysFor($relName);

        $fmsCount = count($existingRows);
        $erpCount = count($incomingRows);

        $fmsItems = [];
        foreach ($existingRows as $row) {
            $fmsItems[] = $this->formatRelationRowSummary($relName, $row);
        }

        $erpItems = [];
        foreach ($incomingRows as $row) {
            $erpItems[] = $this->formatRelationRowSummary($relName, $row);
        }

        // Determine matching vs differences
        $newInErp = 0;
        $matched = 0;

        foreach ($incomingRows as $inRow) {
            $foundMatch = false;
            foreach ($existingRows as $exRow) {
                if (RelationMerge::matches($matchKeys, $exRow, $inRow)) {
                    $foundMatch = true;
                    $matched++;
                    break;
                }
            }
            if (! $foundMatch) {
                $newInErp++;
            }
        }

        $hasDifferences = ($fmsCount !== $erpCount) || ($newInErp > 0);

        $status = match (true) {
            $fmsCount === 0 && $erpCount > 0 => 'new',
            $erpCount === 0 && $fmsCount > 0 => 'fms_only',
            $hasDifferences => 'different',
            default => 'match',
        };

        return [
            'label' => $secDef['label'],
            'description' => $secDef['description'],
            'type' => 'relation',
            'status' => $status,
            'has_differences' => $hasDifferences,
            'fms_count' => $fmsCount,
            'erp_count' => $erpCount,
            'new_in_erp' => $newInErp,
            'fms_items' => $fmsItems,
            'erp_items' => $erpItems,
        ];
    }

    /**
     * Format a scalar field value for user display (resolves foreign keys to labels).
     */
    protected function formatFieldValue(string $field, mixed $value, ?Teacher $teacher): string
    {
        if (blank($value)) {
            return '—';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return match ($field) {
            'gender_id' => Gender::find($value)?->name ?? (string) $value,
            'blood_group_id' => BloodGroup::find($value)?->name ?? (string) $value,
            'country_id' => Country::find($value)?->name ?? (string) $value,
            'religion_id' => Religion::find($value)?->name ?? (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Format education result string including grade, marks and total_marks.
     */
    protected function formatEducationResultSummary(array $row): ?string
    {
        $cgpa = $row['cgpa'] ?? null;
        $scale = $row['scale'] ?? null;
        $grade = $row['grade'] ?? null;
        $marks = $row['marks'] ?? null;
        $totalMarks = $row['total_marks'] ?? null;
        $result = $row['result'] ?? null;

        if (filled($cgpa)) {
            return 'CGPA: ' . $cgpa . (filled($scale) ? " / {$scale}" : '');
        }

        if (filled($grade) && filled($marks)) {
            $marksStr = filled($totalMarks) ? "Marks: {$marks}/{$totalMarks}" : "Marks: {$marks}";
            return "{$grade} ({$marksStr})";
        }

        if (filled($grade)) {
            return (string) $grade;
        }

        if (filled($marks)) {
            return filled($totalMarks) ? "Marks: {$marks}/{$totalMarks}" : "Marks: {$marks}";
        }

        if (filled($result)) {
            return "Result: {$result}";
        }

        return null;
    }

    /**
     * Format a relational item summary for clean side-by-side display.
     */
    protected function formatRelationRowSummary(string $relation, array $row): string
    {
        return match ($relation) {
            'educations' => implode(' | ', array_filter([
                DegreeType::find($row['degree_type_id'] ?? null)?->name ?? ($row['degree_title'] ?? null),
                Organization::find($row['educational_institution_id'] ?? null)?->name ?? ($row['institution'] ?? null),
                $row['passing_year'] ?? null,
                $this->formatEducationResultSummary($row),
            ])) ?: 'Education Record',

            'jobExperiences' => implode(' — ', array_filter([
                $row['position'] ?? null,
                $row['organization'] ?? null,
                trim(($row['start_date'] ?? '') . ' to ' . ($row['end_date'] ?? 'Present'), ' to '),
            ])) ?: 'Job Experience',

            'trainingExperiences' => implode(' — ', array_filter([
                $row['title'] ?? null,
                $row['organization'] ?? null,
                $row['start_date'] ?? null,
            ])) ?: 'Training Record',

            'awards' => implode(' — ', array_filter([
                $row['title'] ?? null,
                $row['awarding_body'] ?? null,
                $row['year'] ?? null,
            ])) ?: 'Award Record',

            'certifications' => implode(' — ', array_filter([
                $row['title'] ?? null,
                $row['issuing_authority'] ?? null,
            ])) ?: 'Certification',

            'skills' => (string) ($row['name'] ?? 'Skill'),

            'teachingAreas' => (string) ($row['area'] ?? 'Teaching Area'),

            'memberships' => implode(' — ', array_filter([
                Organization::find($row['membership_organization_id'] ?? null)?->name ?? ($row['organization'] ?? null),
                $row['position'] ?? null,
                MembershipType::find($row['membership_type_id'] ?? null)?->name ?? null,
            ])) ?: 'Membership',

            'socialLinks' => implode(': ', array_filter([
                SocialMediaPlatform::find($row['social_media_platform_id'] ?? null)?->name ?? 'Social',
                $row['url'] ?? ($row['username'] ?? null),
            ])) ?: 'Social Link',

            default => json_encode($row),
        };
    }

    /**
     * Apply selective synchronization from ERP to the teacher profile.
     *
     * @param array<int, string> $selectedSections
     * @return array{status: string, updated_sections: int, mode: string, changed: bool}
     */
    public function applySync(
        Teacher $teacher,
        array $selectedSections,
        string $mode = self::MODE_MERGE,
        bool $skipApproval = false
    ): array {
        if (empty($selectedSections)) {
            return [
                'status' => 'skipped',
                'updated_sections' => 0,
                'mode' => $mode,
                'changed' => false,
            ];
        }

        $incoming = $this->fetchErpData($teacher);

        if (! $incoming) {
            throw new \RuntimeException('Failed to retrieve ERP data for synchronization.');
        }

        // Start with current database state
        $payload = $teacher->load([
            'educations',
            'jobExperiences',
            'trainingExperiences',
            'awards',
            'certifications',
            'skills',
            'teachingAreas',
            'memberships',
            'socialLinks',
        ])->toArray();

        $definitions = static::getSectionsDefinition();
        $updatedCount = 0;

        foreach ($selectedSections as $secKey) {
            if (! isset($definitions[$secKey])) {
                continue;
            }

            $secDef = $definitions[$secKey];

            if ($secDef['type'] === 'scalar') {
                foreach (array_keys($secDef['fields']) as $field) {
                    $incomingVal = $incoming[$field] ?? null;

                    if ($mode === self::MODE_MERGE) {
                        // Only fill if local attribute is blank and incoming is filled
                        if (blank($teacher->getAttribute($field)) && filled($incomingVal)) {
                            $payload[$field] = $incomingVal;
                        }
                    } elseif ($mode === self::MODE_OVERRIDE) {
                        // Replace if incoming has a non-null value
                        if ($incomingVal !== null && $incomingVal !== '') {
                            $payload[$field] = $incomingVal;
                        }
                    }
                }
                $updatedCount++;
            } elseif ($secDef['type'] === 'relation') {
                $relName = $secDef['relation'];
                $existingRows = is_array($payload[$relName] ?? null) ? $payload[$relName] : [];
                $incomingRows = is_array($incoming[$relName] ?? null) ? $incoming[$relName] : [];

                if ($mode === self::MODE_MERGE) {
                    $keys = RelationMerge::keysFor($relName);
                    $mergeResult = RelationMerge::mergeRows($existingRows, $incomingRows, $keys);
                    $payload[$relName] = $mergeResult['rows'];
                } elseif ($mode === self::MODE_OVERRIDE) {
                    // Overwrite relation rows completely with incoming ERP records if ERP has any
                    if (! empty($incomingRows)) {
                        $payload[$relName] = $incomingRows;
                    }
                }
                $updatedCount++;
            }
        }

        // Remove non-teacher columns that might conflict
        unset($payload['email']);

        /** @var \App\Services\TeacherVersionService $versionService */
        $versionService = app(TeacherVersionService::class);
        $changed = $versionService->handleUpdateFromForm($teacher, $payload, $skipApproval);

        // Clear local memoized cache
        unset($this->cache[$teacher->id]);

        return [
            'status' => 'success',
            'updated_sections' => $updatedCount,
            'mode' => $mode,
            'changed' => $changed,
        ];
    }
}
