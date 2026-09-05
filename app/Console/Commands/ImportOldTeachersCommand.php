<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use App\Models\UserAdministrativeRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportOldTeachersCommand extends Command
{
    protected $signature = 'import:old-teachers
                            {--file=teachers_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0               : Import only N teachers (0 = all)}
                            {--dry-run               : Preview without writing to DB}
                            {--skip-existing         : Skip if employee_id already exists}';

    protected $description = 'Import teachers from exported JSON into the new database (Phase 1 — core profile only)';

    private bool  $dryRun       = false;
    private int   $created      = 0;
    private int   $skipped      = 0;
    private int   $failed       = 0;
    private array $errors       = [];

    // Store already existing employee IDs
    private array $existingEmployeeIds = [];

    // Lookup maps: old slug/short_name → new DB id
    // departments.code (matches old dept.dslug) → departments.id
    private array $deptCodeMap = [];
    // faculties.short_name (matches old faculty.short_name) → faculties.id
    private array $facultyShortNameMap = [];
    // administrative_roles.id → administrative_roles.name (lowercase)
    private array $adminRoleNameMap = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $limit        = (int)  $this->option('limit');
        $skipExisting = (bool) $this->option('skip-existing');
        $file         = storage_path('app/public/exports/' . $this->option('file'));

        // Build lookup maps from new DB
        $this->buildLookupMaps();

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers first.");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Invalid JSON in {$file}");
            return Command::FAILURE;
        }

        // Trim all string values recursively
        array_walk_recursive($data, function (&$val) {
            if (is_string($val)) {
                $val = trim($val);
            }
        });

        if ($limit > 0) {
            $data = array_slice($data, 0, $limit);
        }

        $total = count($data);
        $this->info($this->dryRun ? "🔍 DRY RUN — no changes will be written" : "🚀 Importing {$total} teachers...");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($data as $record) {
            try {
                $this->importRecord($record, $skipExisting);
            } catch (\Throwable $e) {
                $this->failed++;
                $emp = $record['teacher_profile']['employee_id'] ?? 'unknown';
                $this->errors[] = "  [{$emp}] {$e->getMessage()}";
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $total],
                ['✅ Created',       $this->created],
                ['⏭ Skipped',        $this->skipped],
                ['❌ Failed',        $this->failed],
            ]
        );

        // ✅ Show existing employee IDs
        if (!empty($this->existingEmployeeIds)) {
            $this->newLine();
            $this->warn("Already existing employee_ids:");

            foreach ($this->existingEmployeeIds as $empId) {
                $this->line(" - {$empId}");
            }

            $this->newLine();
            $this->info("Total existing employee_ids: " . count($this->existingEmployeeIds));
        }

        if (!empty($this->errors)) {
            $this->newLine();
            $this->warn("Failed records:");
            foreach ($this->errors as $err) {
                $this->line($err);
            }
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->warn("DRY RUN complete — no data was written.");
        }

        return Command::SUCCESS;
    }

    private function importRecord(array $record, bool $skipExisting): void
    {
        $userData    = $record['user'];
        $profileData = $record['teacher_profile'];
        $employeeId  = $profileData['employee_id'] ?? null;

        // ✅ Skip if employee_id already exists
        if ($skipExisting && $employeeId) {
            $exists = Teacher::where('employee_id', $employeeId)->exists();
            if ($exists) {
                $this->skipped++;
                $this->existingEmployeeIds[] = $employeeId;
                return;
            }
        }

        $email = $userData['email'];
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {

            if ($existingUser->teacher) {
                /*
                 * Already here, so the profile is left exactly as it is — the
                 * biography, the corrections, everything somebody has typed
                 * since. What is topped up is only where they belong: the
                 * department assignments and administrative roles.
                 *
                 * Those are the part a re-run has to be able to repair. Before
                 * the export matched departments by name, 117 teachers were
                 * filed under the wrong one, and returning here meant no import
                 * could ever put them right — the only way was to empty the
                 * table and spend three or four hours rebuilding it, losing
                 * every hand correction on the way.
                 *
                 * Adding rows nobody has is cheap and additive, so it stays
                 * within the same run rather than needing a repair script.
                 */
                if (!$this->dryRun) {
                    $this->syncPlacements($existingUser->teacher, $existingUser, $record);
                }

                $this->skipped++;

                if ($employeeId) {
                    $this->existingEmployeeIds[] = $employeeId;
                }

                return;
            }

            if (!$this->dryRun) {
                $this->createTeacherProfile($existingUser, $record);
            }

            $this->created++;
            return;
        }

        if (!$this->dryRun) {
            DB::transaction(function () use ($userData, $record) {
                $user = $this->createUser($userData);
                $this->createTeacherProfile($user, $record);
            });
        }

        $this->created++;
    }

    private function createUser(array $userData): User
    {
        return User::create([
            'name'              => $userData['name'],
            'email'             => $userData['email'],
            'password'          => Hash::make(Str::random(16)),
            'email_verified_at' => now(),
            'is_active'         => $userData['is_active'] ?? true,
        ]);
    }

    private function createTeacherProfile(User $user, array $record): Teacher
    {
        if (!$user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        $p           = $record['teacher_profile'];
        $departments = $record['departments'] ?? [];

        $stripped = collect($p)->reject(fn($v, $k) => str_starts_with($k, '_'))->all();

        $nullIfZero = ['gender_id', 'blood_group_id', 'religion_id', 'marital_status_id'];
        foreach ($nullIfZero as $field) {
            if (isset($stripped[$field]) && (int) $stripped[$field] === 0) {
                $stripped[$field] = null;
            }
        }

        if (!empty($stripped['webpage'])) {
            if (Teacher::where('webpage', $stripped['webpage'])->exists()) {
                $stripped['webpage'] = null;
            }
        }

        $allowed  = (new Teacher)->getFillable();
        $filtered = array_intersect_key($stripped, array_flip($allowed));

        $teacher = Teacher::create(array_merge($filtered, [
            'user_id'    => $user->id,
            'sort_order' => 0,
        ]));

        $this->syncPlacements($teacher, $user, $record);

        return $teacher;
    }

    /**
     * Attach a teacher to their departments and administrative roles.
     *
     * Split out of createTeacherProfile so that a teacher who is already here
     * can be brought up to date without being written again. Every write below
     * is syncWithoutDetaching or updateOrCreate: running it a second time adds
     * what is missing and leaves everything else alone.
     *
     * That is what makes a re-run worth doing. The import used to return the
     * moment it recognised somebody, so a department assignment that was wrong
     * the first time — and there were 117 of those, before the export started
     * matching departments by name — could never be repaired by importing
     * again. The only way through was to empty the table and start over, which
     * on this data set is a three to four hour job and throws away every
     * correction made by hand since.
     *
     * @param  array<string, mixed>  $record
     */
    private function syncPlacements(Teacher $teacher, User $user, array $record): void
    {
        $p = $record['teacher_profile'];
        $departments = $record['departments'] ?? [];

        if (!empty($departments)) {
            foreach ($departments as $dept) {
                $deptId = $dept['department_id'] ?? null;
                if (!$deptId) continue;

                $teacher->departments()->syncWithoutDetaching([
                    $deptId => [
                        'job_type_id' => $dept['job_type_id'] ?? null,
                        'sort_order'  => $dept['sort_order'] ?? 99,
                        'assigned_by' => null,
                    ],
                ]);

                /*
                 * Import Administrative Roles.
                 *
                 * The export has already resolved both ids by name and put them
                 * in this row, so they are what is used. The slug and short_name
                 * lookups below them are a fallback for an older export file
                 * that carries no ids.
                 *
                 * They used to be the only thing tried, and they are matched
                 * literally: old dept.dslug against departments.code, old
                 * faculty.short_name against faculties.short_name. Seven
                 * departments never agreed — Real Estate is 'bre' there and 'RE'
                 * here, Innovation & Entrepreneurship is 'de', and Architecture,
                 * English, Pharmacy, Accounting and Finance & Banking all spell
                 * theirs out in full — and neither does the Faculty of
                 * Engineering, which is 'engineering' there and 'FE' here.
                 *
                 * A miss is silent: the role is still created, with no
                 * department or no faculty on it, which is what scopes a head to
                 * their department and a dean to their faculty. That cost 7
                 * department-scoped roles and 16 faculty-scoped ones, 14 of them
                 * across the whole of Engineering.
                 *
                 * Renaming the codes would fix the lookup and change every
                 * department's public URL with it, so the ids win instead.
                 */
                $adminRoles = $dept['administrative_roles'] ?? [];
                foreach ($adminRoles as $ar) {
                    if (empty($ar['role_id'])) continue;

                    $resolvedDeptId = $ar['department_id'] ?? null;
                    if ($resolvedDeptId === null && !empty($ar['dept_dslug'])) {
                        $resolvedDeptId = $this->deptCodeMap[strtolower(trim($ar['dept_dslug']))] ?? null;
                    }

                    $resolvedFacultyId = $ar['faculty_id'] ?? null;
                    if ($resolvedFacultyId === null && !empty($ar['faculty_short_name'])) {
                        $resolvedFacultyId = $this->facultyShortNameMap[strtolower(trim($ar['faculty_short_name']))] ?? null;
                    }

                    $roleName = $this->adminRoleNameMap[$ar['role_id']] ?? '';

                    // Deans and Associate Deans should only be assigned to faculty (department_id is null)
                    if (str_contains($roleName, 'dean')) {
                        $resolvedDeptId = null;
                    }
                    // Heads and Associate Heads should only be assigned to department (faculty_id is null)
                    elseif (str_contains($roleName, 'head')) {
                        $resolvedFacultyId = null;
                    }

                    UserAdministrativeRole::updateOrCreate([
                        'user_id'                => $user->id,
                        'administrative_role_id' => $ar['role_id'],
                        'department_id'          => $resolvedDeptId,
                        'faculty_id'             => $resolvedFacultyId,
                    ], [
                        'start_date'             => '2024-01-01',
                        'is_active'              => true,
                    ]);
                }
            }
        } elseif (!empty($p['department_id'])) {
            $teacher->departments()->syncWithoutDetaching([
                $p['department_id'] => [
                    'job_type_id' => $p['job_type_id'] ?? null,
                    'sort_order'  => 0,
                    'assigned_by' => null,
                ],
            ]);
        }
    }

    /**
     * Build lookup maps from new DB for resolving department and faculty IDs.
     *
     * departments: code (= old dslug) → id
     * faculties:   short_name (= old short_name) → id
     */
    private function buildLookupMaps(): void
    {
        // departments.code → id
        $depts = DB::table('departments')->whereNull('deleted_at')->get(['id', 'code']);
        foreach ($depts as $d) {
            if (!empty($d->code)) {
                $this->deptCodeMap[strtolower(trim($d->code))] = $d->id;
            }
        }

        // faculties.short_name → id
        $faculties = DB::table('faculties')->whereNull('deleted_at')->get(['id', 'short_name']);
        foreach ($faculties as $f) {
            if (!empty($f->short_name)) {
                $this->facultyShortNameMap[strtolower(trim($f->short_name))] = $f->id;
            }
        }

        // administrative_roles.id → name
        $roles = DB::table('administrative_roles')->whereNull('deleted_at')->get(['id', 'name']);
        foreach ($roles as $r) {
            if (!empty($r->name)) {
                $this->adminRoleNameMap[$r->id] = strtolower(trim($r->name));
            }
        }

        $this->line('Lookup maps built — Depts: ' . count($this->deptCodeMap) .
                    ', Faculties: ' . count($this->facultyShortNameMap) .
                    ', Admin Roles: ' . count($this->adminRoleNameMap));
    }
}
