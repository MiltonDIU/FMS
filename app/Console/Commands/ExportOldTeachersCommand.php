<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportOldTeachersCommand extends Command
{
    protected $signature = 'export:old-teachers {--output=teachers_export.json} {--limit=0}';
    protected $description = 'Export teachers from old database — Phase 1: core profile only (BelongsTo fields)';

    protected array $newDeptMap    = [];
    protected array $newFacultyMap = [];
    protected array $newDesigMap   = [];
    protected array $jobTypeMap    = [];
    protected array $adminRoleMap  = [];

    // Tracks used emails → old_teacher_id (for duplicate detection)
    protected array $usedEmails = [];
    // Tracks used employee IDs → old_teacher_id
    protected array $usedEmployeeIds = [];
    // Conflict log entries
    protected array $conflictLog = [];

    /**
     * Role/shared email prefixes to avoid as primary email.
     * If a teacher has one of these AND another personal email, prefer the personal one.
     *
     * Matching logic: the LOCAL PART (before @) is checked — if it starts with
     * or equals one of these keywords (case-insensitive), it is considered a
     * "role email" and deprioritised.
     */
    protected array $roleEmailPrefixes = [
        'dean', 'head', 'director', 'advisor', 'registrar', 'controller',
        'vc', 'vicechancellor', 'vice.chancellor', 'chancellor', 'provc', 'pro-vc',
        'international', 'intladvisor', 'coordination', 'coordinator',
        'chairman', 'principal', 'provost', 'treasurer',
        'info', 'admin', 'support', 'office', 'contact', 'helpdesk',
    ];

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
                't.study_leave',
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

        // Pre-load ALL dfd_add rows grouped by teacher_id for multi-dept assignment
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
            ->groupBy('teacher_id');

        foreach ($teachers as $teacher) {
            $isArchived = ($teacher->dfd_teacher_id === null);
            $dfdRows    = $isArchived ? collect() : $allDfdRows->get($teacher->old_teacher_id, collect());
            $exportData[] = $this->transformTeacher($teacher, $dfdRows, $isArchived);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // ── Write main export ──
        $filename = $this->option('output');
        $exportDir = storage_path('app/public/exports/');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $path = $exportDir . $filename;
        file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // ── Write conflict log ──
        $conflictPath = $exportDir . 'teachers_conflict_log.json';
        file_put_contents($conflictPath, json_encode($this->conflictLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Stats summary
        $archivedCount   = count(array_filter($exportData, fn($t) => $t['teacher_profile']['is_archived'] === true));
        $activeCount     = count($exportData) - $archivedCount;
        $nullDept        = count(array_filter($exportData, fn($t) => !$t['teacher_profile']['is_archived'] && $t['teacher_profile']['department_id'] === null));
        $nullDesig       = count(array_filter($exportData, fn($t) => !$t['teacher_profile']['is_archived'] && $t['teacher_profile']['designation_id'] === null));
        $fallbackEmail   = count(array_filter($exportData, fn($t) => str_ends_with($t['user']['email'], '@diu.edu.bd') && !str_contains($t['user']['email'], '@daffodil')));

        $emailConflicts  = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'email_duplicate'));
        $empIdConflicts  = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'employee_id_duplicate'));
        $roleEmailUsed   = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'role_email_forced'));

        $this->newLine();
        $this->info("✅ Export complete → {$path}");
        $this->info("📋 Conflict log   → {$conflictPath}");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total exported',                    count($exportData)],
                ['Active teachers (dfd_add present)', $activeCount],
                ['Archived teachers (no dfd_add)',    $archivedCount . ' (faculty=6, dept=31, desig=7, employment_status=9)'],
                ['Null department_id (active only)',  $nullDept . ' (need manual fix or GED dept missing in new DB)'],
                ['Null designation_id (active only)', $nullDesig . ' (unrecognized rank)'],
                ['Fallback email generated',          $fallbackEmail],
                ['Email duplicates (conflict log)',   $emailConflicts],
                ['Employee ID duplicates (log)',      $empIdConflicts],
                ['Role-email forced (no alternative)',$roleEmailUsed],
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
        $manualDept = [
            7  => 11,
            8  => null,
            16 => 28,
            20 => 5,
            23 => 12,
            24 => 13,
            27 => 6,
            28 => 7,
            30 => 8,
            31 => 25,
        ];
        foreach ($manualDept as $oldId => $newId) {
            $this->newDeptMap[$oldId] = $newId;
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

        $this->newFacultyMap[1] = 1;
        $this->newFacultyMap[2] = 2;
        $this->newFacultyMap[3] = 3;
        $this->newFacultyMap[4] = 4;
        $this->newFacultyMap[5] = 5;

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
        $priority = [
            'associate professor',
            'assistant professor',
            'senior lecturer',
            'adjunct faculty',
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

        $specialMap = [
            'dean'              => $rankMap['professor']      ?? null,
            'chair professor'   => $rankMap['professor']      ?? null,
            'distinguished'     => $rankMap['professor']      ?? null,
            'emeritus'          => $rankMap['professor']      ?? null,
            'chancellor'        => $rankMap['professor']      ?? null,
            'founder'           => $rankMap['professor']      ?? null,
            'director'          => $rankMap['professor']      ?? null,
            'advisor'           => $rankMap['professor']      ?? null,
            'visiting'          => $rankMap['adjunct faculty'] ?? null,
            'industrial expert' => $rankMap['adjunct faculty'] ?? null,
            'practice'          => $rankMap['adjunct faculty'] ?? null,
            'academician'       => $rankMap['adjunct faculty'] ?? null,
            'researcher'        => $rankMap['adjunct faculty'] ?? null,
            'scholar'           => $rankMap['adjunct faculty'] ?? null,
            'attached'          => $rankMap['adjunct faculty'] ?? null,
            'part-time'         => $rankMap['adjunct faculty'] ?? null,
            'coordinator'       => $rankMap['senior lecturer'] ?? null,
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
        $nameParts   = $this->parseName($t->name ?? '');
        $email       = $this->resolveEmail($t, $isArchived);
        $newDeptId   = $this->newDeptMap[$t->old_dept_id ?? 0]  ?? null;
        $newDesigId  = $this->newDesigMap[$t->old_desig_id ?? 0] ?? null;
        $jobTypeId   = $this->resolveJobType($t);
        $phoneParsed = $this->parsePhoneAndExtension($t->phone ?? '');
        $phone       = $phoneParsed['phone'];
        $personalPhone = $t->cell ?? null;

        if (empty($phone)) {
            if (!empty($personalPhone)) {
                $phone = $personalPhone;
            } else {
                $phone = '016000000';
            }
        }

        $employmentStatusId = 1; // Default: Active (ID 1)
        if (!$isArchived) {
            $studyLeaveVal = (int) ($t->study_leave ?? 0);
            if ($studyLeaveVal === 1) {
                $employmentStatusId = 3; // Study Leave (ID 3)
            } elseif ($studyLeaveVal === 2) {
                $employmentStatusId = 2; // On Leave (ID 2)
            }
        }

        // ── Employee ID duplicate check ──
        $employeeId = $t->employeeID ?? null;
        if ($employeeId !== null && $employeeId !== '') {
            $empIdKey = strtolower(trim((string)$employeeId));
            if (isset($this->usedEmployeeIds[$empIdKey])) {
                // Duplicate employee ID found — log it
                $this->conflictLog[] = [
                    'type'              => 'employee_id_duplicate',
                    'employee_id'       => $employeeId,
                    'old_teacher_id'    => $t->old_teacher_id,
                    'name'              => trim($t->name ?? ''),
                    'email'             => $email,
                    'first_seen_teacher_id' => $this->usedEmployeeIds[$empIdKey]['old_teacher_id'],
                    'first_seen_name'   => $this->usedEmployeeIds[$empIdKey]['name'],
                    'first_seen_email'  => $this->usedEmployeeIds[$empIdKey]['email'],
                    'note'              => 'Both teachers will be exported; import script must handle uniqueness.',
                ];
            } else {
                $this->usedEmployeeIds[$empIdKey] = [
                    'old_teacher_id' => $t->old_teacher_id,
                    'name'           => trim($t->name ?? ''),
                    'email'          => $email,
                ];
            }
        }

        // Build all department assignments from dfd_add rows
        $departmentAssignments = [];
        if ($dfdRows && $dfdRows->isNotEmpty()) {
            foreach ($dfdRows as $row) {
                $deptId = $this->newDeptMap[$row->old_dept_id] ?? null;
                if ($deptId === null) continue;

                $desigId    = $this->newDesigMap[$row->old_desig_id ?? 0] ?? null;
                $rowJobType = $this->resolveJobTypeFromRow($row);

                $adminRoles       = [];
                $facId            = $this->newFacultyMap[$row->old_faculty_id] ?? null;
                $deptDslug        = $row->dept_dslug ?? null;
                $facultyShortName = $row->faculty_short_name ?? null;

                if (!empty($row->dean))           $adminRoles[] = ['role_id' => $this->adminRoleMap['dean']                ?? null, 'dept_dslug' => null,       'faculty_short_name' => $facultyShortName, 'department_id' => null,    'faculty_id' => $facId];
                if (!empty($row->head))           $adminRoles[] = ['role_id' => $this->adminRoleMap['head of department'] ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->advisor))        $adminRoles[] = ['role_id' => $this->adminRoleMap['advisor']            ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->associate_dean)) $adminRoles[] = ['role_id' => $this->adminRoleMap['associate dean']     ?? null, 'dept_dslug' => null,       'faculty_short_name' => $facultyShortName, 'department_id' => null,    'faculty_id' => $facId];
                if (!empty($row->intadvisor))     $adminRoles[] = ['role_id' => $this->adminRoleMap['intadvisor']         ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];
                if (!empty($row->coordination))   $adminRoles[] = ['role_id' => $this->adminRoleMap['program coordinator'] ?? null, 'dept_dslug' => $deptDslug, 'faculty_short_name' => $facultyShortName, 'department_id' => $deptId, 'faculty_id' => $facId];

                $adminRoles = array_values(array_filter($adminRoles, fn($r) => $r['role_id'] !== null));

                $key = $deptId;
                if (!isset($departmentAssignments[$key])) {
                    $departmentAssignments[$key] = [
                        'department_id'        => $deptId,
                        'designation_id'       => $desigId,
                        'job_type_id'          => $rowJobType,
                        'is_primary'           => ((int)($row->recordListingID ?? 0)) === 1,
                        'sort_order'           => (int)($row->recordListingID ?? 99),
                        'administrative_roles' => $adminRoles,
                        '_old_dept_name'       => $row->dept_name,
                    ];
                }
            }
        }

        if (empty($departmentAssignments) && $newDeptId) {
            $departmentAssignments[$newDeptId] = [
                'department_id'        => $newDeptId,
                'designation_id'       => $newDesigId,
                'job_type_id'          => $jobTypeId,
                'is_primary'           => true,
                'administrative_roles' => [],
                '_old_dept_name'       => $t->old_dept_name,
            ];
        }

        // ── Archived teacher overrides ──
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
                    'department_id'        => 31,
                    'designation_id'       => 7,
                    'faculty_id'           => 6,
                    'job_type_id'          => 7,
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
                    'login_allowed'        => false,
                    'is_archived'          => true,
                    'profile_status'       => 'archived',
                    '_old_teacher_id'      => $t->old_teacher_id,
                    '_old_designation'     => null,
                    '_old_department'      => null,
                    '_old_faculty'         => null,
                ],
                'departments'          => [],
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
                'department_id'        => $newDeptId,
                'designation_id'       => $newDesigId,
                'job_type_id'          => $jobTypeId,
                'employment_status_id' => $employmentStatusId,
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
                'is_public'            => true,
                'is_active'            => true,
                'login_allowed'        => true,
                'is_archived'          => false,
                'profile_status'       => 'approved',
                '_old_teacher_id'      => $t->old_teacher_id,
                '_old_designation'     => $t->old_designation_name,
                '_old_department'      => $t->old_dept_name,
                '_old_faculty'         => $t->old_faculty_name,
            ],
            'departments'          => array_values($departmentAssignments),
            'educations'           => [],
            'job_experiences'      => [],
            'awards'               => [],
            'training_experiences' => [],
            'teaching_areas'       => [],
            'memberships'          => [],
            'social_links'         => [],
        ];
    }

    // ── Email Resolution ──

    /**
     * Resolve the best unique email for a teacher.
     *
     * Priority:
     *   1. Personal institutional email (not a role/shared address) — @diu.edu.bd preferred
     *   2. Personal institutional email — @daffodilvarsity.edu.bd
     *   3. Any personal .edu.bd email
     *   4. Any personal non-edu email (gmail etc.)
     *   5. Role/shared email (last resort — only if no personal alternative)
     *
     * Uniqueness: if the selected email is already taken by another teacher,
     * the conflict is logged but the email is still used (import script decides).
     */
    private function resolveEmail(object $t, bool $isArchived): string
    {
        $rawEmail   = $t->email      ?? '';
        $employeeId = $t->employeeID ?? '';

        $allCandidates = $this->parseAllEmails($rawEmail, $employeeId);

        // Separate personal vs role emails
        $personal = array_filter($allCandidates, fn($e) => !$this->isRoleEmail($e));
        $roleOnly  = array_filter($allCandidates, fn($e) => $this->isRoleEmail($e));

        // Pick best personal email (by domain priority)
        $chosen = $this->pickByDomainPriority($personal)
               ?? $this->pickByDomainPriority($roleOnly)  // fallback to role email
               ?? 'unknown@diu.edu.bd';

        // If we had to fall back to a role email, log it
        if ($this->isRoleEmail($chosen) && !empty($roleOnly)) {
            $this->conflictLog[] = [
                'type'           => 'role_email_forced',
                'old_teacher_id' => $t->old_teacher_id,
                'name'           => trim($t->name ?? ''),
                'email_used'     => $chosen,
                'all_emails'     => $allCandidates,
                'note'           => 'No personal email found; role/shared email was the only option.',
            ];
        }

        // Uniqueness check
        $emailKey = strtolower($chosen);
        if (isset($this->usedEmails[$emailKey])) {
            $prev = $this->usedEmails[$emailKey];
            $this->conflictLog[] = [
                'type'               => 'email_duplicate',
                'old_teacher_id'     => $t->old_teacher_id,
                'name'               => trim($t->name ?? ''),
                'email'              => $chosen,
                'raw_email_field'    => $rawEmail,
                'all_emails_parsed'  => $allCandidates,
                'first_seen_teacher' => $prev['old_teacher_id'],
                'first_seen_name'    => $prev['name'],
                'note'               => 'Duplicate email — both records exported; import script must resolve.',
            ];
        } else {
            $this->usedEmails[$emailKey] = [
                'old_teacher_id' => $t->old_teacher_id,
                'name'           => trim($t->name ?? ''),
            ];
        }

        return $chosen;
    }

    /**
     * Parse raw email field into a cleaned, deduplicated list of valid addresses.
     */
    private function parseAllEmails(string $rawEmail, string $employeeId): array
    {
        $parts = preg_split('/[,;]+/', strtolower(trim($rawEmail)));
        $candidates = [];

        foreach ($parts as $part) {
            $cleaned = preg_replace('/\s+/', '', trim($part));
            if ($cleaned && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $cleaned;
            }
        }

        if (empty($candidates)) {
            $slug = preg_replace('/[^a-z0-9]/', '', strtolower($employeeId));
            $candidates[] = $slug ? "{$slug}@diu.edu.bd" : 'unknown@diu.edu.bd';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Returns true if the email local-part looks like a shared/role address.
     *
     * Logic: extract the part before '@', strip digits from the end,
     * then check if it starts with (or equals) any known role prefix.
     */
    private function isRoleEmail(string $email): bool
    {
        $local = strtolower(explode('@', $email)[0] ?? $email);
        // Remove trailing digits (e.g. "dean2" → "dean")
        $localStripped = rtrim($local, '0123456789');

        foreach ($this->roleEmailPrefixes as $prefix) {
            if ($localStripped === $prefix || str_starts_with($localStripped, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * From a list of emails, return the best one by institutional domain priority.
     * Returns null if the list is empty.
     */
    private function pickByDomainPriority(array $emails): ?string
    {
        $emails = array_values($emails);
        if (empty($emails)) return null;

        // 1. @diu.edu.bd
        foreach ($emails as $e) {
            if (str_ends_with($e, '@diu.edu.bd')) return $e;
        }
        // 2. @daffodilvarsity.edu.bd
        foreach ($emails as $e) {
            if (str_ends_with($e, '@daffodilvarsity.edu.bd')) return $e;
        }
        // 3. any .edu.bd
        foreach ($emails as $e) {
            if (str_ends_with($e, '.edu.bd')) return $e;
        }
        // 4. first available
        return $emails[0];
    }

    // ── Job Type Helpers ──

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
     */
    private function parsePhoneAndExtension(string $raw): array
    {
        $raw = trim($raw);
        if (empty($raw)) {
            return ['phone' => null, 'extension_no' => null];
        }

        $extPattern = '/(?:IP\s*:\s*|Ext(?:ension)?\s*[-#:]?\s*|Ex\s*-\s*)(\d+)/i';

        $extension = null;
        $phone     = $raw;

        if (preg_match($extPattern, $raw, $matches)) {
            $extension = trim($matches[1]);
            $phone     = preg_replace('/[,;\s]*' . preg_quote($matches[0], '/') . '/i', '', $raw);
            $phone     = trim($phone, ' ,;-');
        }

        $phone = ($phone === '' || $phone === null) ? null : $phone;

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

    /**
     * @deprecated  Use resolveEmail() instead. Kept for reference only.
     */
    private function getValidEmail(string $rawEmail, string $employeeId): string
    {
        $parts = preg_split('/[,;]+/', strtolower(trim($rawEmail)));
        $candidates = [];
        foreach ($parts as $part) {
            $cleaned = preg_replace('/\s+/', '', trim($part));
            if ($cleaned && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $cleaned;
            }
        }
        if (empty($candidates)) {
            $slug = preg_replace('/[^a-z0-9]/', '', strtolower($employeeId));
            return $slug ? "{$slug}@diu.edu.bd" : 'unknown@diu.edu.bd';
        }
        foreach ($candidates as $email) {
            if (str_ends_with($email, '@diu.edu.bd')) return $email;
        }
        foreach ($candidates as $email) {
            if (str_ends_with($email, '@daffodilvarsity.edu.bd')) return $email;
        }
        foreach ($candidates as $email) {
            if (str_ends_with($email, '.edu.bd')) return $email;
        }
        return $candidates[0];
    }
}
