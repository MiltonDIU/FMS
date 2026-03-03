<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportOldTeachersCommand extends Command
{
    protected $signature = 'export:old-teachers {--output=teachers_export.json} {--limit=0}';
    protected $description = 'Export teachers from old database — Phase 1: core profile only (BelongsTo fields)';

    protected array $newDeptMap  = [];
    protected array $newFacultyMap = [];
    protected array $newDesigMap = [];
    protected array $jobTypeMap  = [];
    protected array $adminRoleMap = [];

    public function handle(): int
    {
        $this->info('Building lookup tables...');
        $this->buildLookupTables();

        $this->info('Fetching old teachers...');
        $limit = (int) $this->option('limit');

        // LEFT JOIN — dfd_add-এ আছে এবং নেই উভয় teachers এক সাথে fetch করা হচ্ছে।
        // dfd_add-এ কোনো record নেই এমন teacher-রা archived হবে।
        // dfd_add-এ একজন teacher একাধিক dept-এ থাকতে পারে,
        // তাই recordListingID=1 (primary listing) কে prefer করা হয়,
        // না থাকলে MIN() দিয়ে প্রথমটা নেওয়া হয়
        $query = DB::connection('old_db')
            ->table('teacher as t')
            ->leftJoin('dfd_add as dfd', 'dfd.teacher_id', '=', 't.id')
            ->leftJoin('department as dept', 'dfd.department_id', '=', 'dept.department_id')
            ->leftJoin('faculty as fac', 'dfd.faculty_id', '=', 'fac.faculty_id')
            ->leftJoin('designation as des', 'dfd.designation_id', '=', 'des.designation_id')
            ->select(
                't.id                 as old_teacher_id',
                't.name',
                't.employeeID',
                't.email',
                't.phone',
                't.cell',
                't.webpage',
                't.currentResearch',
                // Primary dept: prefer recordListingID=1, else MIN
                DB::raw('COALESCE(
                    MIN(CASE WHEN dfd.recordListingID = 1 THEN dfd.department_id END),
                    MIN(dfd.department_id)
                ) as old_dept_id'),
                DB::raw('COALESCE(
                    MIN(CASE WHEN dfd.recordListingID = 1 THEN dfd.designation_id END),
                    MIN(dfd.designation_id)
                ) as old_desig_id'),
                DB::raw('MIN(dfd.is_part_time)   as is_part_time'),
                DB::raw('MIN(dfd.teacher_type)   as teacher_type'),
                DB::raw('COALESCE(
                    MIN(CASE WHEN dfd.recordListingID = 1 THEN dept.departmentname END),
                    MIN(dept.departmentname)
                ) as old_dept_name'),
                DB::raw('MIN(fac.facultyname)    as old_faculty_name'),
                DB::raw('COALESCE(
                    MIN(CASE WHEN dfd.recordListingID = 1 THEN des.designation END),
                    MIN(des.designation)
                ) as old_designation_name'),
                // NULL হলে teacher dfd_add-এ নেই → archived
                DB::raw('MIN(dfd.teacher_id) as dfd_teacher_id')
            )
            ->groupBy('t.id');  // Group by unique ID instead of employeeID

        if ($limit > 0) {
            $query->limit($limit);
        }

        $teachers = $query->get();
        $this->info("Found {$teachers->count()} teachers.");

        $exportData = [];
        $bar = $this->output->createProgressBar($teachers->count());

        // Pre-load ALL dfd_add rows grouped by employeeID for multi-dept assignment
        // teacher_id -> [ {dept_id, desig_id, job_type...}, ... ]
        $allDfdRows = DB::connection('old_db')
            ->table('dfd_add as dfd')
            ->join('teacher as t', 't.id', '=', 'dfd.teacher_id')
            ->leftJoin('department as dept', 'dept.department_id', '=', 'dfd.department_id')
            ->leftJoin('faculty as fac', 'fac.faculty_id', '=', 'dfd.faculty_id')
            ->leftJoin('designation as des', 'des.designation_id', '=', 'dfd.designation_id')
            ->select(
                'dfd.teacher_id',
                'dfd.faculty_id     as old_faculty_id',
                'dfd.department_id  as old_dept_id',
                'dfd.designation_id as old_desig_id',
                'dfd.is_part_time',
                'dfd.teacher_type',
                'dfd.recordListingID',
                'dfd.dean',
                'dfd.head',
                'dfd.advisor',
                'dfd.associate_dean',
                'dfd.intadvisor',
                'dfd.coordination',
                'dept.departmentname as dept_name',
                'dept.dslug         as dept_dslug',
                'fac.short_name     as faculty_short_name',
                'des.designation    as desig_name'
            )
            ->orderBy('t.id')
            ->orderBy('dfd.recordListingID')
            ->get()
            ->groupBy('teacher_id');   // Group by teacher_id

        foreach ($teachers as $teacher) {
            // dfd_teacher_id NULL মানে teacher dfd_add-এ নেই → archived
            $isArchived = ($teacher->dfd_teacher_id === null);
            $dfdRows    = $isArchived ? collect() : $allDfdRows->get($teacher->old_teacher_id, collect());
            $exportData[] = $this->transformTeacher($teacher, $dfdRows, $isArchived);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $filename = $this->option('output');
        $path = storage_path('app/public/exports/' . $filename);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Stats summary
        $archivedCount = count(array_filter($exportData, fn($t) => $t['teacher_profile']['is_archived'] === true));
        $activeCount   = count($exportData) - $archivedCount;
        $nullDept      = count(array_filter($exportData, fn($t) => !$t['teacher_profile']['is_archived'] && $t['teacher_profile']['department_id'] === null));
        $nullDesig     = count(array_filter($exportData, fn($t) => !$t['teacher_profile']['is_archived'] && $t['teacher_profile']['designation_id'] === null));
        $nullEmail     = count(array_filter($exportData, fn($t) => str_ends_with($t['user']['email'], '@diu.edu.bd') && !str_contains($t['user']['email'], '@daffodil')));

        $this->newLine();
        $this->info("✅ Export complete → {$path}");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total exported',                   count($exportData)],
                ['Active teachers (dfd_add present)', $activeCount],
                ['Archived teachers (no dfd_add)',    $archivedCount . ' (faculty=6, dept=31, desig=7, employment_status=9)'],
                ['Null department_id (active only)',  $nullDept . ' (need manual fix or GED dept missing in new DB)'],
                ['Null designation_id (active only)', $nullDesig . ' (unrecognized rank)'],
                ['Fallback email generated',          $nullEmail],
            ]
        );
        return 0;
    }

    // ── Lookup Tables ──

    private function buildLookupTables(): void
    {
        // Department map
        $oldDepts = DB::connection('old_db')->table('department')->get();
        $newDepts = DB::connection('mysql')->table('departments')->get();

        foreach ($oldDepts as $old) {
            $oldName = $this->normalizeName($old->departmentname);
            foreach ($newDepts as $new) {
                $newName = $this->normalizeName($new->name);
                if ($oldName === $newName || str_contains($newName, $oldName) || str_contains($oldName, $newName)) {
                    $this->newDeptMap[$old->department_id] = $new->id;
                    break;
                }
            }
        }

        // Manual overrides for name mismatches
        // old_dept_id => new_dept_id  (null = dept does not exist in new system)
        $manualDept = [
            7  => 11,   // MCT
            8  => null, // General Educational Development — not in new DB
            16 => 28,   // Journalism (extra space in old name)
            20 => 5,    // Innovation & Entrepreneurship
            23 => 12,   // Computing and Information System
            24 => 13,   // ITM
            27 => 6,    // Accounting
            28 => 7,    // Finance & Banking
            30 => 8,    // Marketing
            31 => 25,   // Genetic Engineering
            // NOTE: old dept 8 (GED) removed from new DB — no longer in dfd_add
            // Add Robotics if it exists in new DB (currently maps to null):
            // 32 => XX,  // Robotics & Mechatronics — add new dept ID when created
        ];
        foreach ($manualDept as $oldId => $newId) {
            $this->newDeptMap[$oldId] = $newId; // null entries are intentional
        }

        // Designation map
        $newDesigs = DB::connection('mysql')->table('designations')->get();
        $oldDesigs = DB::connection('old_db')->table('designation')->get();

        $rankMap = [];
        foreach ($newDesigs as $nd) {
            $rankMap[strtolower(trim($nd->name))] = $nd->id;
        }
        foreach ($oldDesigs as $od) {
            $this->newDesigMap[$od->designation_id] = $this->matchDesignation(
                strtolower($od->designation), $rankMap
            );
        }

        // Job type map
        $jobTypes = DB::connection('mysql')->table('job_types')->get(['id', 'name']);
        foreach ($jobTypes as $jt) {
            $this->jobTypeMap[strtolower(trim($jt->name))] = $jt->id;
        }

        // Faculty map
        $oldFacs = DB::connection('old_db')->table('faculty')->get();
        $newFacs = DB::connection('mysql')->table('faculties')->get();
        foreach ($oldFacs as $old) {
            $oldName = $this->normalizeName($old->facultyname);
            foreach ($newFacs as $new) {
                $newName = $this->normalizeName($new->name);
                if ($oldName === $newName || str_contains($newName, $oldName) || str_contains($oldName, $newName)) {
                    $this->newFacultyMap[$old->faculty_id] = $new->id;
                    break;
                }
            }
        }

        $this->newFacultyMap[1] = 1; // FSIT
        $this->newFacultyMap[2] = 2; // FE
        $this->newFacultyMap[3] = 3; // FBE
        $this->newFacultyMap[4] = 4; // FAHS
        $this->newFacultyMap[5] = 5; // FHSS

        // Administrative roles map
        $roles = DB::connection('mysql')->table('administrative_roles')->get(['id', 'name']);
        foreach ($roles as $r) {
            $this->adminRoleMap[strtolower(trim($r->name))] = $r->id;
        }

        $this->info("Dept map: " . count($this->newDeptMap) .
                    " | Faculty map: " . count($this->newFacultyMap) .
                    " | Desig map: " . count($this->newDesigMap) . " entries");
    }

    private function matchDesignation(string $oldName, array $rankMap): ?int
    {
        // Step 1: keyword priority (longer first to avoid partial match)
        $priority = [
            'associate professor',
            'assistant professor',
            'senior lecturer',
            'adjunct faculty',   // exact match before generic 'adjunct'
            'adjunct',
            'professor',
            'senior lecturer',
            'lecturer',
        ];
        foreach ($priority as $keyword) {
            if (isset($rankMap[$keyword]) && str_contains($oldName, $keyword)) {
                return $rankMap[$keyword];
            }
        }

        // Step 2: special role titles — map to closest academic rank
        $specialMap = [
            // Dean/Senior-level → Professor
            'dean'                 => $rankMap['professor'] ?? null,
            'chair professor'      => $rankMap['professor'] ?? null,
            'distinguished'        => $rankMap['professor'] ?? null,
            'emeritus'             => $rankMap['professor'] ?? null,
            'chancellor'           => $rankMap['professor'] ?? null,
            'founder'              => $rankMap['professor'] ?? null,
            'director'             => $rankMap['professor'] ?? null,
            'advisor'              => $rankMap['professor'] ?? null,  // Advisor, Int'l Advisor
            // Visiting / Adjunct / Industry → Adjunct Faculty
            'visiting'             => $rankMap['adjunct faculty'] ?? null,
            'industrial expert'    => $rankMap['adjunct faculty'] ?? null,
            'practice'             => $rankMap['adjunct faculty'] ?? null,
            'academician'          => $rankMap['adjunct faculty'] ?? null,  // Practicing Industry-Academician
            'researcher'           => $rankMap['adjunct faculty'] ?? null,
            'scholar'              => $rankMap['adjunct faculty'] ?? null,
            'attached'             => $rankMap['adjunct faculty'] ?? null,
            'part-time'            => $rankMap['adjunct faculty'] ?? null,
            // Coordinator-level → Senior Lecturer fallback
            'coordinator'          => $rankMap['senior lecturer'] ?? null,
        ];
        foreach ($specialMap as $keyword => $id) {
            if ($id && str_contains($oldName, $keyword)) {
                return $id;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\s*\([^)]+\)/', '', $name);
        return trim($name, ' ,.');
    }

    // ── Transform (Phase 1) ──

    private function transformTeacher(object $t, $dfdRows = null, bool $isArchived = false): array
    {
        $nameParts  = $this->parseName($t->name ?? '');
        $email      = $this->getValidEmail($t->email ?? '', $t->employeeID ?? '');
        $newDeptId  = $this->newDeptMap[$t->old_dept_id ?? 0]  ?? null;
        $newDesigId = $this->newDesigMap[$t->old_desig_id ?? 0] ?? null;
        $jobTypeId  = $this->resolveJobType($t);
        $phoneParsed = $this->parsePhoneAndExtension($t->phone ?? '');
        $phone = $phoneParsed['phone'];
        $personalPhone = $t->cell ?? null;

        // Requirement: phone is required. Fallback to personal_phone, then to default '016000000'
        if (empty($phone)) {
            if (!empty($personalPhone)) {
                $phone = $personalPhone;
            } else {
                $phone = '016000000';
//                $personalPhone = '016000000';
            }
        }

        // Build all department assignments from dfd_add rows
        $departmentAssignments = [];
        if ($dfdRows && $dfdRows->isNotEmpty()) {
            foreach ($dfdRows as $row) {
                $deptId = $this->newDeptMap[$row->old_dept_id] ?? null;
                if ($deptId === null) continue;  // skip unmapped (e.g. GED)

                $desigId    = $this->newDesigMap[$row->old_desig_id ?? 0] ?? null;
                $rowJobType = $this->resolveJobTypeFromRow($row);

                // Map administrative roles from flags
                $adminRoles = [];
                $facId = $this->newFacultyMap[$row->old_faculty_id] ?? null;

                $deptDslug       = $row->dept_dslug ?? null;
                $facultyShortName = $row->faculty_short_name ?? null;

                if (!empty($row->dean))           $adminRoles[] = ['role_id' => $this->adminRoleMap['dean'] ?? null,               'dept_dslug' => null,      'faculty_short_name' => $facultyShortName, 'department_id' => null,   'faculty_id' => $facId];
                if (!empty($row->head))           $adminRoles[] = ['role_id' => $this->adminRoleMap['head of department'] ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->advisor))        $adminRoles[] = ['role_id' => $this->adminRoleMap['advisor'] ?? null,            'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->associate_dean)) $adminRoles[] = ['role_id' => $this->adminRoleMap['associate dean'] ?? null,     'dept_dslug' => null,      'faculty_short_name' => $facultyShortName, 'department_id' => null,   'faculty_id' => $facId];
                if (!empty($row->intadvisor))     $adminRoles[] = ['role_id' => $this->adminRoleMap['intadvisor'] ?? null,         'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->coordination))   $adminRoles[] = ['role_id' => $this->adminRoleMap['program coordinator'] ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];

                $adminRoles = array_filter($adminRoles, fn($r) => $r['role_id'] !== null);

                // Avoid exact duplicate dept entries
                $key = $deptId;
                if (!isset($departmentAssignments[$key])) {
                    $departmentAssignments[$key] = [
                        'department_id'  => $deptId,
                        'designation_id' => $desigId,
                        'job_type_id'    => $rowJobType,
                        'is_primary'     => ((int)($row->recordListingID ?? 0)) === 1,
                        'sort_order'     => (int)($row->recordListingID ?? 99),
                        'administrative_roles' => array_values($adminRoles),
                        '_old_dept_name' => $row->dept_name,
                    ];
                }
            }
        }

        // Fallback: if no dfd_add rows mapped, use profile-level dept
        if (empty($departmentAssignments) && $newDeptId) {
            $departmentAssignments[$newDeptId] = [
                'department_id'  => $newDeptId,
                'designation_id' => $newDesigId,
                'job_type_id'    => $jobTypeId,
                'is_primary'     => true,
                '_old_dept_name' => $t->old_dept_name,
            ];
        }

        // ── Archived teacher overrides ──
        // dfd_add-এ record নেই এমন teacher-রা archived;
        // তাদের জন্য fixed dept/faculty/desig এবং status values সেট করা হচ্ছে।
        if ($isArchived) {
            return [
                'user' => [
                    'name'      => trim($t->name ?? ''),
                    'email'     => $email,
                    'is_active' => false,
                ],

                'teacher_profile' => [
                    'employee_id'          => $t->employeeID   ?? null,
                    'first_name'           => $nameParts['first_name'],
                    'middle_name'          => $nameParts['middle_name'],
                    'last_name'            => $nameParts['last_name'],
                    // Archived defaults
                    'department_id'        => 31,
                    'designation_id'       => 7,
                    'faculty_id'           => 6,
                    'job_type_id'          => null,
                    'employment_status_id' => 9,
                    'country_id'           => 18,
                    'gender_id'            => 0,
                    'blood_group_id'       => 0,
                    'religion_id'          => 0,
                    'phone'                => $phone,
                    'extension_no'         => $phoneParsed['extension_no'],
                    'personal_phone'       => $personalPhone,
                    'webpage'              => $t->webpage ?? null,
                    'bio'                  => null,
                    'research_interest'    => null,
                    'is_public'            => false,
                    'is_active'            => false,
                    'is_archived'          => true,
                    'profile_status'       => 'archived',
                    // Reference — stripped before real import
                    '_old_teacher_id'      => $t->old_teacher_id,
                    '_old_designation'     => null,
                    '_old_department'      => null,
                    '_old_faculty'         => null,
                ],

                // Archived teachers have no dept assignments from dfd_add
                'departments' => [],

                // HasMany — empty for archived
                'educations'           => [],
                'job_experiences'      => [],
                'awards'               => [],
                'training_experiences' => [],
                'teaching_areas'       => [],
                'memberships'          => [],
                'social_links'         => [],
            ];
        }

        // ── Normal (active) teacher ──
        return [
            'user' => [
                'name'      => trim($t->name ?? ''),
                'email'     => $email,
                'is_active' => true,
            ],

            'teacher_profile' => [
                'employee_id'          => $t->employeeID   ?? null,
                'first_name'           => $nameParts['first_name'],
                'middle_name'          => $nameParts['middle_name'],
                'last_name'            => $nameParts['last_name'],
                // Primary department (recordListingID=1 preferred)
                'department_id'        => $newDeptId,
                'designation_id'       => $newDesigId,
                'job_type_id'          => $jobTypeId,
                'employment_status_id' => 1,
                'country_id'           => 18,
                'gender_id'            => 0,
                'blood_group_id'       => 0,
                'religion_id'          => 0,
                'phone'                => $phone,
                'extension_no'         => $phoneParsed['extension_no'],
                'personal_phone'       => $personalPhone,
                'webpage'              => $t->webpage ?? null,
                // bio: null — old DB had no bio field
                'bio'                  => null,
                'research_interest'    => null,
                'is_public'            => true,
                'is_active'            => true,
                'is_archived'          => false,
                // Valid enum values: draft | pending | approved | rejected
                'profile_status'       => 'approved',
                // Reference — stripped before real import
                '_old_teacher_id'  => $t->old_teacher_id,
                '_old_designation' => $t->old_designation_name,
                '_old_department'  => $t->old_dept_name,
                '_old_faculty'     => $t->old_faculty_name,
            ],

            // All dept assignments from dfd_add — imported into department_teacher pivot
            'departments' => array_values($departmentAssignments),

            // HasMany — Phase 2 (will be handled separately via AI/regex pipeline)
            'educations'           => [],
            'job_experiences'      => [],
            'awards'               => [],
            'training_experiences' => [],
            'teaching_areas'       => [],
            'memberships'          => [],
            'social_links'         => [],
        ];
    }

    private function resolveJobType(object $t): ?int
    {
        if (!empty($t->is_part_time)) {
            return $this->jobTypeMap['part time'] ?? null;
        }
        return match ((int) ($t->teacher_type ?? 0)) {
            1       => $this->jobTypeMap['adjunct faculty']  ?? null,
            2       => $this->jobTypeMap['visiting faculty'] ?? null,
            default => $this->jobTypeMap['full time']        ?? null,
        };
    }

    // Same logic but accepts a dfd_add row directly (for per-dept pivot)
    private function resolveJobTypeFromRow(object $row): ?int
    {
        if (!empty($row->is_part_time)) {
            return $this->jobTypeMap['part time'] ?? null;
        }
        return match ((int) ($row->teacher_type ?? 0)) {
            1       => $this->jobTypeMap['adjunct faculty']  ?? null,
            2       => $this->jobTypeMap['visiting faculty'] ?? null,
            default => $this->jobTypeMap['full time']        ?? null,
        };
    }

    // ── Helpers ──

    private function cleanHtml(string $html): string
    {
        if (empty(trim($html))) return '';
        $html = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>'], "\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($html);
    }

    /**
     * Split a phone string into phone + extension_no.
     *
     * Examples:
     *   "IP: 43100"                   → phone: null,      ext: "43100"
     *   "Ext-304"                     → phone: null,      ext: "304"
     *   "Ex-65109"                    → phone: null,      ext: "65109"
     *   "Ext- 50100"                  → phone: null,      ext: "50100"
     *   "9116774, Ex-258"             → phone: "9116774", ext: "258"
     *   "+88 02 9138234-5 Ex-65109"   → phone: "+88 02 9138234-5", ext: "65109"
     *   "+8801713493055"              → phone: "+8801713493055", ext: null
     */
    private function parsePhoneAndExtension(string $raw): array
    {
        $raw = trim($raw);
        if (empty($raw)) {
            return ['phone' => null, 'extension_no' => null];
        }

        // Pattern: IP:XXXXX  or  Ext-XXXXX  or  Ex-XXXXX  (with optional spaces)
        $extPattern = '/(?:IP\s*:\s*|Ext(?:ension)?\s*[-#:]?\s*|Ex\s*-\s*)(\d+)/i';

        $extension = null;
        $phone     = $raw;

        if (preg_match($extPattern, $raw, $matches)) {
            $extension = trim($matches[1]);
            // Remove the extension part (and any separator before it) from phone
            $phone = preg_replace('/[,;\s]*' . preg_quote($matches[0], '/') . '/i', '', $raw);
            $phone = trim($phone, ' ,;-');
        }

        // If nothing left in phone after removing extension, set to null
        $phone = ($phone === '' || $phone === null) ? null : $phone;

        // If phone is ONLY an extension keyword with no real number, clear phone
        if ($phone && preg_match('/^(IP|Ext|Ex)\s*[-:#]?\s*$/i', $phone)) {
            $phone = null;
        }

        return [
            'phone'        => $phone,
            'extension_no' => $extension,
        ];
    }

    private function parseName(string $fullName): array
    {
        $fullName = preg_replace('/^(Dr\.?|Prof\.?|Mr\.?|Mrs\.?|Ms\.?|Md\.?)\s+/i', '', trim($fullName));
        $parts    = preg_split('/\s+/', $fullName);
        return [
            'first_name'  => $parts[0] ?? $fullName,
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last_name'   => count($parts) > 1 ? end($parts) : null,
        ];
    }

    private function getValidEmail(string $rawEmail, string $employeeId): string
    {
        // Split by comma or semicolon — old records can have multiple emails
        $parts = preg_split('/[,;]+/', strtolower(trim($rawEmail)));

        $candidates = [];
        foreach ($parts as $part) {
            // Remove ALL internal whitespace (handles "elahi.jmc@ daffodilvarsity.edu.bd")
            $cleaned = preg_replace('/\s+/', '', trim($part));
            if ($cleaned && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $cleaned;
            }
        }

        if (empty($candidates)) {
            // Fallback: generate from employeeID
            $slug = preg_replace('/[^a-z0-9]/', '', strtolower($employeeId));
            return $slug ? "{$slug}@diu.edu.bd" : 'unknown@diu.edu.bd';
        }

        // Priority 1: @diu.edu.bd
        foreach ($candidates as $email) {
            if (str_ends_with($email, '@diu.edu.bd')) return $email;
        }

        // Priority 2: @daffodilvarsity.edu.bd
        foreach ($candidates as $email) {
            if (str_ends_with($email, '@daffodilvarsity.edu.bd')) return $email;
        }

        // Priority 3: any other .edu.bd domain
        foreach ($candidates as $email) {
            if (str_ends_with($email, '.edu.bd')) return $email;
        }

        // Priority 4: first valid email (gmail etc.)
        return $candidates[0];
    }
}
