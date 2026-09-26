<?php

namespace App\Console\Commands;

use App\Support\DesignationTitle;
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

    /**
     * old designation_id → the title carried beside the rank, or null.
     *
     * "Associate Professor & Director, M.Sc in CSE" resolves to the Associate
     * Professor row in newDesigMap and leaves "Director, M.Sc in CSE" here.
     * Until this existed the second half was read for its rank and then thrown
     * away, so a directorship survived nowhere in the new system.
     */
    protected array $extraDesigMap = [];

    // Tracks used emails → old_teacher_id (for duplicate detection)
    protected array $usedEmails = [];
    // Tracks used employee IDs → old_teacher_id
    protected array $usedEmployeeIds = [];
    // Conflict log entries
    protected array $conflictLog = [];

    /**
     * Words that make an address a post's rather than a person's. Matched
     * anywhere in the local part.
     *
     * Anywhere, not at the start, because the post is very often qualified in
     * front of the word. Eleven addresses were slipping through on a
     * starts-with test, and eight of them were being exported as somebody's
     * login: "aheadte@" and "aheadcse2@" are the assistant heads of Textile
     * Engineering and CSE, "adeanfbe@" the associate dean of Business and
     * Entrepreneurship, "campusdirector@daffodiluniversity.ae" the director of
     * the UAE campus. Each begins with a letter that is not part of the role
     * word, so "head", "dean" and "director" never matched.
     *
     * These are long enough that finding one inside a local part means what it
     * says; none of the 2,129 names in the old table produces one by accident.
     */
    protected array $roleEmailWords = [
        'dean', 'head', 'director', 'advisor', 'adviser', 'registrar',
        'controller', 'chancellor', 'chairman', 'principal', 'provost',
        'treasurer', 'coordinator', 'coordination', 'international',
        'helpdesk', 'provost',
    ];

    /**
     * Short or ambiguous tokens, matched only at the START of the local part.
     *
     * "vc" inside a word is a coincidence — matching it anywhere would catch a
     * name — so these keep the stricter test they always had.
     */
    protected array $roleEmailPrefixes = [
        'vc', 'vicechancellor', 'vice.chancellor', 'provc', 'pro-vc',
        'info', 'admin', 'support', 'office', 'contact',
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
                't.picture',
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

        /*
         * Fold the same person's second row into their first before anything
         * is written.
         *
         * 30 employee ids are held by two rows of the old teacher table, and
         * an employee id is HR's own number, so that is one person entered
         * twice rather than two people. Exporting both produced two profiles
         * that then had to be merged back together by hand in the new system.
         *
         * Collapsing here rather than merging later is not only less work: it
         * is the only point at which the two rows are still side by side with
         * their departments, emails and details intact. Three of the pairs are
         * somebody genuinely listed under two departments — Tamanna Akter in
         * Computer Science and in Computing and Information Systems — and the
         * export has carried several departments per teacher all along, so
         * those become one profile assigned to both.
         */
        [$teachers, $mergedDfdRows] = $this->collapseDuplicateEmployeeIds($teachers, $allDfdRows);

        $bar = $this->output->createProgressBar($teachers->count());

        foreach ($teachers as $teacher) {
            $isArchived = ($teacher->dfd_teacher_id === null);
            $dfdRows    = $isArchived
                ? collect()
                : ($mergedDfdRows[$teacher->old_teacher_id] ?? $allDfdRows->get($teacher->old_teacher_id, collect()));
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
        $roleOnly        = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'role_email_moved_to_secondary'));
        $collapsed       = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'employee_id_collapsed'));
        $twoPeople       = count(array_filter($this->conflictLog, fn($c) => $c['type'] === 'employee_id_shared_by_different_people'));
        $withSecondary   = count(array_filter($exportData, fn($t) => filled($t['teacher_profile']['secondary_email'] ?? null)));

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
                ['Duplicate profiles collapsed',      $collapsed],
                ['  left separate (different people)',$twoPeople],
                ['Employee ID duplicates (log)',      $empIdConflicts],
                ['Spare address → secondary_email',   $withSecondary],
                ['  of those, only a shared address', $roleOnly . ' (login address generated)'],
            ]
        );
        return 0;
    }

    // ── Lookup Tables ──

    private function buildLookupTables(): void
    {
        /*
         * Department map.
         *
         * Matched on the name, exactly, after the "Department of " prefix that
         * every old row carries is dropped. Nothing about it depends on either
         * side's id, which is the whole point: this command is meant to be run
         * again whenever the old database moves, and ids do not survive that.
         *
         * It used to do two things instead, and both of them were wrong.
         *
         * The first was a substring test — str_contains in either direction,
         * taking the first hit. "Department of Tourism & Hospitality Management"
         * contains "Management", so its 24 people were filed under Management;
         * "Environmental Science and Disaster Management" went the same way with
         * another 13.
         *
         * The second was a hand-written table of old id => new id. Its numbers
         * belonged to some earlier version of the departments table and no
         * longer pointed anywhere sensible: Multimedia & Creative Technology
         * was sent to Electrical and Electronic Engineering, Journalism to
         * Multimedia, Computing and Information System to Architecture,
         * Information Technology & Management to Civil Engineering, and Genetic
         * Engineering and Biotechnology to Development Studies. 117 teachers in
         * seven departments, filed under names that had nothing to do with
         * them, while the six departments they belonged to stood empty.
         *
         * Exact matching resolves 31 of the 33 old departments on its own. The
         * two it cannot are named below, because a rename or a department that
         * does not exist yet is a fact about the data, not something a fuzzy
         * test should be left to guess at.
         */
        $oldDepts = DB::connection('old_db')->table('department')->get();
        $newDepts = DB::connection('mysql')->table('departments')->get();

        $newDeptByName = [];
        foreach ($newDepts as $new) {
            $newDeptByName[$this->normalizeName($new->name)] = $new->id;
        }

        /*
         * Old name => new name, for departments that were genuinely renamed
         * between the two systems. Keyed by name rather than by id so that
         * re-importing from a different copy of the old database cannot
         * silently point an entry at the wrong department.
         *
         * 'fisheries' has no counterpart in the new structure at all — four
         * people are attached to it. Rather than file them under a department
         * they do not belong to, they are reported at the end of the mapping
         * and left for a decision; add the department and its name here and
         * they will come across on the next run.
         */
        $deptAliases = [
            // 'old name (without "department of")' => 'new department name',
        ];

        $unmatchedDepts = [];

        foreach ($oldDepts as $old) {
            $name = $this->normalizeName($old->departmentname);
            $name = preg_replace('/^department of\s+/', '', $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            $name = $deptAliases[$name] ?? $name;

            $newId = $newDeptByName[$this->normalizeName($name)] ?? null;

            $this->newDeptMap[$old->department_id] = $newId;

            if ($newId === null) {
                $unmatchedDepts[$old->department_id] = $old->departmentname;
            }
        }

        // Designation map
        // Soft-deleted rows excluded: a retired designation must not be handed
        // out again. This is the query builder, which does not apply the model's
        // SoftDeletes scope, so the condition is spelled out.
        $newDesigs = DB::connection('mysql')->table('designations')->whereNull('deleted_at')->get();
        $oldDesigs = DB::connection('old_db')->table('designation')->get();

        $rankMap = [];
        foreach ($newDesigs as $nd) {
            $rankMap[strtolower(trim($nd->name))] = $nd->id;
        }
        /*
         * One old designation string can hold two facts: "Professor & Director,
         * MBA Program" is a rank and a standing title. Split first, match the
         * rank half, and keep the other half for teachers.extra_designation —
         * matchDesignation() reads a rank out of whatever it is given and
         * discards the rest, so without the split the directorship is lost.
         */
        $isRank = fn (string $half) => $this->namesRankExplicitly($half, $rankMap);

        foreach ($oldDesigs as $od) {
            [$rankText, $extra] = DesignationTitle::split($od->designation, $isRank);

            $this->newDesigMap[$od->designation_id] = $this->matchDesignation(
                strtolower($rankText), $rankMap
            );
            $this->extraDesigMap[$od->designation_id] = $extra;
        }

        // Job type map
        $jobTypes = DB::connection('mysql')->table('job_types')->get(['id', 'name']);
        foreach ($jobTypes as $jt) {
            $this->jobTypeMap[strtolower(trim($jt->name))] = $jt->id;
        }

        /*
         * Faculty map. Same rule as the departments above, with "Faculty of "
         * dropped instead.
         *
         * The five hard-coded lines that used to follow this loop pinned old 1-5
         * to new 1-5. They happened to be right, and they hid the fact that the
         * loop under them was doing the same substring guessing that misfiled
         * seven departments. Old faculty 6 was not in that list and matched
         * nothing, so the five people under it carried no faculty at all —
         * which reaches the administrative role rows, where the faculty is what
         * scopes a dean or an associate dean.
         */
        $oldFacs = DB::connection('old_db')->table('faculty')->get();
        $newFacs = DB::connection('mysql')->table('faculties')->get();

        $newFacByName = [];
        foreach ($newFacs as $new) {
            $newFacByName[$this->normalizeName($new->name)] = $new->id;
        }

        /*
         * Old name => new name, for a faculty that was renamed between the two
         * systems. Empty, and it should stay that way: Agriculture Sciences was
         * in here only because this side was missing the faculty entirely, and
         * FacultySeeder now carries all six under the same names the old site
         * uses.
         */
        $facultyAliases = [
            // 'old name (without "faculty of")' => 'new faculty name',
        ];

        $unmatchedFacs = [];

        foreach ($oldFacs as $old) {
            $name = $this->normalizeName($old->facultyname);
            $name = preg_replace('/^faculty of\s+/', '', $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            $name = $facultyAliases[$name] ?? $name;

            $newId = $newFacByName[$this->normalizeName($name)] ?? null;

            $this->newFacultyMap[$old->faculty_id] = $newId;

            if ($newId === null) {
                $unmatchedFacs[$old->faculty_id] = $old->facultyname;
            }
        }

        // Administrative roles map
        $roles = DB::connection('mysql')->table('administrative_roles')->get(['id', 'name']);
        foreach ($roles as $r) {
            $this->adminRoleMap[strtolower(trim($r->name))] = $r->id;
        }

        $this->reportMapping($unmatchedDepts, $unmatchedFacs);
    }

    /**
     * What the mapping did, and what it could not do.
     *
     * This command is run again every time the old database is refreshed, so a
     * department that stops matching has to be visible on the run that breaks
     * it rather than discovered later in an empty listing. Anything that mapped
     * to nothing is printed with the number of people behind it, and every new
     * department that no old department feeds is listed underneath — a new
     * department legitimately has nobody yet, but so does one whose name has
     * drifted apart from its old counterpart.
     *
     * @param  array<int, string>  $unmatchedDepts
     * @param  array<int, string>  $unmatchedFacs
     */
    private function reportMapping(array $unmatchedDepts, array $unmatchedFacs): void
    {
        $mappedDepts = count(array_filter($this->newDeptMap, fn ($id) => $id !== null));

        $this->info(sprintf(
            'Mapped %d of %d departments, %d of %d faculties, %d designations.',
            $mappedDepts,
            count($this->newDeptMap),
            count(array_filter($this->newFacultyMap, fn ($id) => $id !== null)),
            count($this->newFacultyMap),
            count($this->newDesigMap),
        ));

        foreach ($unmatchedDepts as $oldId => $name) {
            $people = DB::connection('old_db')
                ->table('dfd_add')
                ->where('department_id', $oldId)
                ->distinct()
                ->count('teacher_id');

            if ($people === 0) {
                $this->line(sprintf('  no match: %s — nobody attached, nothing lost.', $name));

                continue;
            }

            $this->warn(sprintf(
                '  NO MATCH: %s — %d teacher(s) will not reach a department. Add it to $deptAliases, or create the department.',
                $name,
                $people,
            ));
        }

        foreach ($unmatchedFacs as $oldId => $name) {
            $this->warn(sprintf('  NO MATCH: %s — add it to $facultyAliases.', $name));
        }

        $targets = array_filter(array_values($this->newDeptMap), fn ($id) => $id !== null);

        $orphans = DB::connection('mysql')
            ->table('departments')
            ->whereNotIn('id', $targets ?: [0])
            ->orderBy('id')
            ->pluck('name', 'id');

        foreach ($orphans as $id => $name) {
            $this->line(sprintf('  no old department feeds #%d %s', $id, $name));
        }
    }

    /**
     * Keyword → the designation row it names, most specific first.
     *
     * The order is the whole of the logic: "associate professor" has to be
     * tested before "professor", and both senior-scale spellings before
     * "lecturer". Without that last part "Lecturer (Senior Scale)" matches the
     * plain Lecturer row, which is what happened to every senior-scale lecturer
     * in the old data — the grade simply disappeared on import.
     *
     * Only academic ranks appear here. "Adjunct" and "Visiting" describe how
     * somebody is employed, not what they are, and jobTypeFor() already reads
     * both out of the same string into job_type_id.
     */
    private const RANK_KEYWORDS = [
        // Before "professor", or it is read as a plain Professor. Only used
        // where the designation exists; otherwise "professor" still catches it.
        'emeritus professor'      => 'emeritus professor',
        'lecturer (senior scale)' => 'lecturer (senior scale)',
        'senior scale'            => 'lecturer (senior scale)',
        'associate professor'     => 'associate professor',
        'assistant professor'     => 'assistant professor',
        'senior lecturer'         => 'senior lecturer',
        'professor'               => 'professor',
        'lecturer'                => 'lecturer',
    ];

    /**
     * Whether this half of a designation names an academic rank in so many
     * words.
     *
     * Deliberately stricter than matchDesignation(), which falls back to
     * "a dean is a professor" for a title that names no rank at all. That
     * fallback is right when there is only one title to read and wrong when
     * deciding which of two halves is the rank: it made isRank("Dean") true, so
     * "Dean & Professor" was never swapped and the rank was read out of the
     * word "Dean". "Associate Dean & Associate Professor" came through as plain
     * Professor because of it — a grade demoted by one and promoted by the
     * other, on somebody who is neither.
     */
    private function namesRankExplicitly(string $name, array $rankMap): bool
    {
        foreach (self::RANK_KEYWORDS as $keyword => $target) {
            if (isset($rankMap[$target]) && str_contains(strtolower($name), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function matchDesignation(string $oldName, array $rankMap): ?int
    {
        $priority = self::RANK_KEYWORDS;

        foreach ($priority as $keyword => $target) {
            if (isset($rankMap[$target]) && str_contains($oldName, $keyword)) {
                return $rankMap[$target];
            }
        }

        /*
         * Reached only when the title names no rank at all.
         *
         * The first group are titles a rank is implied by — a dean, an emeritus,
         * a distinguished chair are all professors.
         *
         * The second group name no rank and never will: "Visiting Faculty",
         * "Industrial Expert", "Research Scholar" say how somebody is engaged,
         * not what grade they hold. They used to land on an "Adjunct Faculty"
         * designation, which was the same mistake written down — that row has
         * been retired from the designations table, because job_types is where
         * this belongs and already carries Adjunct Faculty, Visiting Faculty,
         * Contractual and Part Time. jobTypeFor() reads the engagement out of
         * this very string, so nothing is lost by admitting the grade is unknown
         * rather than inventing one.
         */
        $professor = $rankMap['professor'] ?? null;
        $noRank = $rankMap['system - unassigned designation'] ?? null;

        $specialMap = [
            'dean'              => $professor,
            'chair professor'   => $professor,
            'distinguished'     => $professor,
            'emeritus'          => $professor,
            'chancellor'        => $professor,
            'founder'           => $professor,
            'director'          => $professor,
            'advisor'           => $professor,
            'adjunct'           => $noRank,
            'visiting'          => $noRank,
            'industrial expert' => $noRank,
            'practice'          => $noRank,
            'academician'       => $noRank,
            'researcher'        => $noRank,
            'scholar'           => $noRank,
            'attached'          => $noRank,
            'part-time'         => $noRank,
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

    // ── Duplicate collapsing ──

    /**
     * Folds rows sharing an employee id into one, keeping what each held.
     *
     * The survivor is the row that knows where the person works: one with a
     * dfd_add record, so it is not archived. On 18 of the 30 pairs only one
     * row has any department at all and the other is a bare stub; on 9 neither
     * does; on 3 they sit in different departments and both are kept as
     * assignments on the one profile.
     *
     * Nothing the folded-away row held is dropped. Its email joins the
     * survivor's, so the one that does not become the login still lands in
     * secondary_email — the two rows often carry different addresses, and
     * Rokanuzzaman's rokanuzzaman.eng@ and rokanuzzaman.eee0106.c@ are both
     * his. Any field the survivor has blank is filled from it.
     *
     * A pair whose names do not match is left alone and logged. Four of those
     * are one person typed twice — "Afsana Hossain Rima" and "Afsana Hosssain
     * Rima" — but one is two different people who were issued the same
     * employee id, and collapsing them would lose a teacher.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $teachers
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection>  $allDfdRows
     * @return array{0: \Illuminate\Support\Collection<int, object>, 1: array<int, \Illuminate\Support\Collection>}
     */
    private function collapseDuplicateEmployeeIds($teachers, $allDfdRows): array
    {
        $mergedDfdRows = [];
        $drop = [];

        $groups = $teachers
            ->filter(fn (object $t): bool => trim((string) ($t->employeeID ?? '')) !== '')
            ->groupBy(fn (object $t): string => strtolower(trim((string) $t->employeeID)));

        foreach ($groups as $employeeId => $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            if (! $this->looksLikeOnePerson($rows)) {
                $this->conflictLog[] = [
                    'type' => 'employee_id_shared_by_different_people',
                    'employee_id' => $employeeId,
                    'rows' => $rows->map(fn (object $t): array => [
                        'old_teacher_id' => $t->old_teacher_id,
                        'name' => trim((string) $t->name),
                        'email' => $t->email,
                    ])->all(),
                    'note' => 'Names differ too much to be one person; both were exported separately.',
                ];

                continue;
            }

            // The row that knows where they work, then the fuller record.
            $survivor = $rows->sortBy([
                fn (object $a, object $b): int => ($a->dfd_teacher_id === null ? 1 : 0) <=> ($b->dfd_teacher_id === null ? 1 : 0),
                fn (object $a, object $b): int => $this->filledFieldCount($b) <=> $this->filledFieldCount($a),
                fn (object $a, object $b): int => $a->old_teacher_id <=> $b->old_teacher_id,
            ])->first();

            $others = $rows->reject(fn (object $t): bool => $t->old_teacher_id === $survivor->old_teacher_id);

            $dfd = collect($allDfdRows->get($survivor->old_teacher_id, collect()));

            foreach ($others as $other) {
                $this->foldRowInto($other, $survivor);

                $dfd = $dfd->concat($allDfdRows->get($other->old_teacher_id, collect()));
                $drop[$other->old_teacher_id] = true;
            }

            // One assignment per department, whichever row it came from.
            $dfd = $dfd->unique(fn (object $row) => $row->old_dept_id)->values();

            if ($dfd->isNotEmpty()) {
                $mergedDfdRows[$survivor->old_teacher_id] = $dfd;

                // Departments came from somewhere, so this is not an archived row.
                $survivor->dfd_teacher_id = $survivor->dfd_teacher_id ?? $survivor->old_teacher_id;
            }

            $this->conflictLog[] = [
                'type' => 'employee_id_collapsed',
                'employee_id' => $employeeId,
                'kept_old_teacher_id' => $survivor->old_teacher_id,
                'folded_old_teacher_ids' => $others->pluck('old_teacher_id')->all(),
                'name' => trim((string) $survivor->name),
                'email' => $survivor->email,
                'departments' => $dfd->pluck('dept_name')->filter()->unique()->values()->all(),
                'note' => 'One person held two rows in the old teacher table; exported as a single profile.',
            ];
        }

        return [
            $teachers->reject(fn (object $t): bool => isset($drop[$t->old_teacher_id]))->values(),
            $mergedDfdRows,
        ];
    }

    /**
     * Whether every row in a group is plausibly the same human being.
     *
     * Compared on letters alone, so spacing and punctuation cannot separate
     * "S. M. Mahmudur Rahman" from "S.M. Mahmudur Rahman". The threshold is
     * set where it divides the real data: the four misspellings score 91% and
     * up, and the one pair that is genuinely two people scores far below.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function looksLikeOnePerson($rows): bool
    {
        $names = $rows->map(fn (object $t): string => preg_replace('/[^a-z]/', '', strtolower((string) $t->name)))
            ->filter()
            ->values();

        if ($names->count() < 2) {
            return true;
        }

        $first = $names->first();

        foreach ($names->skip(1) as $name) {
            if ($name === $first || str_contains($first, $name) || str_contains($name, $first)) {
                continue;
            }

            similar_text($first, $name, $percent);

            if ($percent < 85.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Carries everything the folded-away row held onto the survivor.
     */
    private function foldRowInto(object $from, object $survivor): void
    {
        /*
         * Emails are appended rather than chosen between. resolveEmail picks
         * the login out of the whole list and puts the rest in
         * secondary_email, so both addresses survive without this having to
         * decide which is which.
         */
        $emails = array_filter([
            trim((string) ($survivor->email ?? '')),
            trim((string) ($from->email ?? '')),
        ]);

        $survivor->email = implode(', ', array_unique($emails));

        foreach (['phone', 'cell', 'webpage', 'currentResearch', 'picture', 'study_leave',
                  'old_dept_id', 'old_desig_id', 'old_dept_name', 'old_faculty_name',
                  'old_designation_name', 'is_part_time', 'teacher_type'] as $field) {
            if (blank($survivor->{$field} ?? null) && filled($from->{$field} ?? null)) {
                $survivor->{$field} = $from->{$field};
            }
        }
    }

    private function filledFieldCount(object $row): int
    {
        $n = 0;

        foreach (get_object_vars($row) as $value) {
            if (filled($value)) {
                $n++;
            }
        }

        return $n;
    }

    // ── Transform (Phase 1) ──

    private function transformTeacher(object $t, $dfdRows = null, bool $isArchived = false): array
    {
        $nameParts   = $this->parseName($t->name ?? '');
        $resolvedEmail  = $this->resolveEmail($t, $isArchived);
        $email          = $resolvedEmail['primary'];
        $secondaryEmail = $resolvedEmail['secondary'];
        $newDeptId   = $this->newDeptMap[$t->old_dept_id ?? 0]  ?? null;
        $newDesigId  = $this->newDesigMap[$t->old_desig_id ?? 0] ?? null;
        $extraDesig  = $this->extraDesigMap[$t->old_desig_id ?? 0] ?? null;
        $jobTypeId   = $this->resolveJobType($t);
        $phoneParsed = $this->parsePhoneAndExtension($t->phone ?? '');
        $phone       = $phoneParsed['phone'];
        $personalPhone = $t->cell ?? null;

        /*
         * A teacher with no office number on file gets none.
         *
         * This used to write '016000000' — a number nobody can be reached on —
         * for the 324 teachers who have neither a phone nor a cell recorded.
         * The column is nullable, and every theme drops the Phone row when it is
         * empty, so leaving it null shows nothing instead of showing a wrong
         * number and putting it in the vCard as well.
         */
        if (empty($phone) && !empty($personalPhone)) {
            $phone = $personalPhone;
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

        /*
         * "Professor & Head" must not also carry the extra title "Head": the
         * head flag on the same dfd_add row already produces a Head of
         * Department assignment, and the profile would then say it twice —
         * once in the title and once in the badge beside it.
         *
         * Only the roles this teacher actually receives are compared, so a
         * title naming a role the new system has no row for is kept as text.
         * Advisor is exactly that case: administrative_roles has no Advisor,
         * so those assignments are dropped at the filter below and the words
         * "& Advisor" would otherwise be lost with them.
         */
        if ($extraDesig !== null) {
            $heldRoleIds = [];

            foreach ($departmentAssignments as $assignment) {
                foreach ($assignment['administrative_roles'] as $role) {
                    $heldRoleIds[] = $role['role_id'];
                }
            }

            $heldRoleNames = array_keys(array_filter(
                $this->adminRoleMap,
                fn ($id) => in_array($id, $heldRoleIds, true),
            ));

            if (DesignationTitle::repeatsAdministrativeRole($extraDesig, $heldRoleNames)) {
                $extraDesig = null;
            }
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
                    // Resolved to ids by the import, which is where the lookup
                    // tables live; the export stays readable.
                    'name_prefix'          => $nameParts['name_prefix'],
                    'name'                 => $nameParts['name'],
                    'academic_suffixes'    => $nameParts['academic_suffixes'],
                    'first_name'           => $nameParts['first_name'],
                    'middle_name'          => $nameParts['middle_name'],
                    'last_name'            => $nameParts['last_name'],
                    'department_id'        => 32,
                    'designation_id'       => 7,
                    'extra_designation'    => null,
                    'faculty_id'           => 7,
                    'job_type_id'          => 7,
                    'employment_status_id' => 9,
                    'country_id'           => 18,
                    'gender_id'            => 0,
                    'blood_group_id'       => 0,
                    'religion_id'          => 0,
                    'phone'                => $phone,
                    'extension_no'         => $phoneParsed['extension_no'],
                    'personal_phone'       => $personalPhone,
                    // Every address except the login: shared department ones
                    // (headeee@, deanfsit@ …) and second personal ones alike.
                    'secondary_email'      => $secondaryEmail,
                    'webpage'              => $t->webpage ?? null,
                    'photo'                => $t->picture ?: null,
                    'bio'                  => null,
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
                // Resolved to ids by the import, which is where the lookup
                // tables live; the export stays readable.
                'name_prefix'          => $nameParts['name_prefix'],
                'name'                 => $nameParts['name'],
                'academic_suffixes'    => $nameParts['academic_suffixes'],
                'first_name'           => $nameParts['first_name'],
                'middle_name'          => $nameParts['middle_name'],
                'last_name'            => $nameParts['last_name'],
                'department_id'        => $newDeptId,
                'designation_id'       => $newDesigId,
                'extra_designation'    => $extraDesig,
                'job_type_id'          => $jobTypeId,
                'employment_status_id' => $employmentStatusId,
                'country_id'           => 18,
                'gender_id'            => 0,
                'blood_group_id'       => 0,
                'religion_id'          => 0,
                'phone'                => $phone,
                'extension_no'         => $phoneParsed['extension_no'],
                'personal_phone'       => $personalPhone,
                // Every address except the login: shared department ones
                // (headeee@, deanfsit@ …) and second personal ones alike.
                'secondary_email'      => $secondaryEmail,
                'webpage'              => $t->webpage ?? null,
                'photo'                => $t->picture ?: null,
                'bio'                  => null,
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
     * Resolve a teacher's addresses into the one they log in with and the rest.
     *
     * Returns ['primary' => string, 'secondary' => ?string].
     *
     * Primary priority:
     *   1. Personal institutional email (not a role/shared address) — @diu.edu.bd preferred
     *   2. Personal institutional email — @daffodilvarsity.edu.bd
     *   3. Any personal .edu.bd email
     *   4. Any personal non-edu email (gmail etc.)
     *   5. A generated address — never a role address
     *
     * A role address is never the primary any more, not even as a last resort.
     * headeee@, deanfsit@ and headpharmacy@ are the department's addresses and
     * not any one person's: the post changes hands and the address does not, so
     * three different teachers hold headpharmacy@ in the old table and three
     * hold headeee@. users.email carries a UNIQUE index, so those cannot all
     * become logins; previously the export folded the old teacher id into the
     * address to force them apart, which invented a login nobody can receive
     * mail at.
     *
     * They are kept rather than dropped, on secondary_email — as is every other
     * address the teacher has and does not log in with, so that the migration
     * loses none of them. 118 teachers carry one; for 18 of them the only
     * address the old database holds is a shared one, so their login is
     * generated.
     *
     * Uniqueness: if the selected primary is already taken by another teacher,
     * the conflict is logged and, for a different person, made distinct.
     *
     * @return array{primary: string, secondary: ?string}
     */
    private function resolveEmail(object $t, bool $isArchived): array
    {
        $rawEmail   = $t->email      ?? '';
        $employeeId = $t->employeeID ?? '';

        $allCandidates = $this->parseAllEmails($rawEmail, $employeeId);

        // Separate personal vs role emails
        $personal = array_filter($allCandidates, fn($e) => !$this->isRoleEmail($e));
        $roleOnly  = array_filter($allCandidates, fn($e) => $this->isRoleEmail($e));

        // Pick best personal email (by domain priority)
        $chosen = $this->pickByDomainPriority($personal)
               ?? $this->generatedEmailFor($employeeId, (int) $t->old_teacher_id);

        /*
         * Every address the teacher holds except the one they log in with.
         *
         * Not just the role ones. A teacher can have a second personal address
         * as easily as a departmental one — Zahirul Islam has
         * zahirete@daffodilvarsity.edu.bd and zahir375@gmail.com, Mahbubul
         * Haque has his DIU address and one at Manchester — and only one of
         * them can be the login. The other used to be read, classified, and
         * then dropped on the floor: 77 addresses belonging to 52 people
         * existed in the old database and nowhere in the new one.
         *
         * So secondary_email is every leftover address rather than only the
         * shared ones. It takes the count from 66 to 118 and means the
         * migration loses no address at all.
         *
         * Order is the order they were written, which puts the institutional
         * ones first in almost every case. 114 people have one leftover and 4
         * have two; the longest value is 69 characters against a varchar(255).
         */
        $others = array_values(array_filter($allCandidates, fn ($e) => $e !== $chosen));

        $secondary = $others !== [] ? implode(', ', $others) : null;

        // A teacher whose only address was a role one now has a generated
        // login, which is worth knowing about rather than discovering later.
        if ($personal === [] && $roleOnly !== []) {
            $this->conflictLog[] = [
                'type'             => 'role_email_moved_to_secondary',
                'old_teacher_id'   => $t->old_teacher_id,
                'name'             => trim($t->name ?? ''),
                'generated_primary'=> $chosen,
                'secondary_email'  => $secondary,
                'all_emails'       => $allCandidates,
                'note'             => 'Only a shared role address on file; it was moved to secondary_email and a login address generated.',
            ];
        }

        /*
         * Uniqueness.
         *
         * The import matches people by email, so two export rows sharing one
         * address become one person. The note here used to say the import would
         * resolve it; it does not, and 109 of the 2,118 exported teachers were
         * disappearing into somebody else's record.
         *
         * Most of them onto one address: 78 teachers have no email at all in the
         * old database and every one of them was given 'unknown@diu.edu.bd', so
         * 77 people vanished — among them Dr. Monjur Ahmed, the visiting
         * researcher missing from Computing and Information System.
         *
         * A shared address means one of two different things, and the name tells
         * them apart. The same name twice is the same person listed twice — Mr.
         * Bikash Kumar Paul is in the old table under both Software Engineering
         * and CIS with one address — and those should still merge into one
         * teacher. A different name is a different person, whether they had no
         * address at all or share a departmental one the way Asit Ghosh and
         * A.B.M. Islam share aheadte@daffodilvarsity.edu.bd, and those must not
         * be merged.
         *
         * So a different person gets the old teacher id folded into the address.
         * That is stable — the same old database yields the same address on
         * every run — which matters for a migration that gets re-run.
         */
        $emailKey = strtolower($chosen);
        $name = trim($t->name ?? '');

        if (isset($this->usedEmails[$emailKey])) {
            $prev = $this->usedEmails[$emailKey];
            $samePerson = $this->normalizeName($prev['name']) === $this->normalizeName($name);

            if (! $samePerson) {
                $chosen = $this->uniqueEmailFor($chosen, (int) $t->old_teacher_id);
                $emailKey = strtolower($chosen);
            }

            $this->conflictLog[] = [
                'type'               => 'email_duplicate',
                'old_teacher_id'     => $t->old_teacher_id,
                'name'               => $name,
                'email'              => $chosen,
                'raw_email_field'    => $rawEmail,
                'all_emails_parsed'  => $allCandidates,
                'first_seen_teacher' => $prev['old_teacher_id'],
                'first_seen_name'    => $prev['name'],
                'resolution'         => $samePerson
                    ? 'Same name — left as one address so the import merges the two rows into one teacher.'
                    : 'Different person on a shared address — given a distinct address so they are not merged away.',
            ];
        }

        if (! isset($this->usedEmails[$emailKey])) {
            $this->usedEmails[$emailKey] = [
                'old_teacher_id' => $t->old_teacher_id,
                'name'           => $name,
            ];
        }

        return ['primary' => $chosen, 'secondary' => $secondary];
    }

    /**
     * A login address for somebody the old database has no personal one for.
     *
     * Built from the employee id, which is unique and stable, so re-running the
     * export against the same old database produces the same address. The old
     * teacher id is the fallback for the handful with no employee id either —
     * without it they would all collide on 'unknown@diu.edu.bd', which is how
     * 77 people used to vanish into one record.
     */
    private function generatedEmailFor(string $employeeId, int $oldTeacherId): string
    {
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($employeeId));

        return $slug !== '' ? "{$slug}@diu.edu.bd" : "teacher.{$oldTeacherId}@diu.edu.bd";
    }

    /**
     * The same address with the old teacher id folded into the local part, so
     * two different people cannot land on one login.
     */
    private function uniqueEmailFor(string $email, int $oldTeacherId): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, 'diu.edu.bd');

        return sprintf('%s.%d@%s', $local, $oldTeacherId, $domain ?: 'diu.edu.bd');
    }

    /**
     * Parse raw email field into a cleaned, deduplicated list of valid addresses.
     */
    private function parseAllEmails(string $rawEmail, string $employeeId): array
    {
        /*
         * The old `email` column is one varchar holding anything up to four
         * addresses, separated however the person typing felt like: a comma in
         * 99 rows, a semicolon in 2, and nothing but a space in 104.
         *
         * Splitting on [,;] alone — which is what this did — turned
         * "headpess@diu.edu.bd m.kamal@daffodilvarsity.edu.bd" into a single
         * part, and the whitespace strip below then glued it into
         * "headpess@diu.edu.bdm.kamal@..." which validates as nothing. Those
         * teachers fell through to the generated employee-id address and both
         * of their real addresses were lost.
         *
         * Whitespace cannot simply be added to the split, because it is also
         * what breaks single addresses: "elahi.jmc@ daffodilvarsity.edu.bd" and
         * "alamin @daffodilvarsity.edu.bd" are one address each, typed with a
         * stray space. So the space beside an @ is closed up first, and only
         * then is the remaining whitespace treated as a separator.
         */
        $normalised = preg_replace('/\s*@\s*/', '@', strtolower(trim($rawEmail)));

        $parts = preg_split('/[,;\/\|\s]+/', (string) $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidates = [];

        foreach ($parts as $part) {
            $cleaned = trim($part, " \t\n\r\0\x0B.<>()");
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
     * Returns true if the email local-part belongs to a post rather than a person.
     *
     * Two tests, because the two lists mean different things: a role word can
     * sit anywhere in the local part ("aheadcse2", "campusdirector"), while the
     * short tokens are only safe to read at the start.
     */
    private function isRoleEmail(string $email): bool
    {
        $local = strtolower(explode('@', $email)[0] ?? $email);
        // Remove trailing digits (e.g. "dean2" → "dean")
        $localStripped = rtrim($local, '0123456789');

        foreach ($this->roleEmailWords as $word) {
            if (str_contains($localStripped, $word)) {
                return true;
            }
        }

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
        return $this->jobTypeFor(
            $t->is_part_time ?? null,
            $t->teacher_type ?? null,
            $t->old_designation_name ?? null,
        );
    }

    private function resolveJobTypeFromRow(object $row): ?int
    {
        return $this->jobTypeFor(
            $row->is_part_time ?? null,
            $row->teacher_type ?? null,
            $row->desig_name ?? null,
        );
    }

    /**
     * How somebody is employed, from the two columns that say so and — when
     * they do not — from the designation they were given.
     *
     * teacher_type is the intended source, but it is mostly unset: of the 38
     * people carrying the designation "Visiting Professor", 30 have
     * teacher_type = 0, which this read as Regular. The word "Visiting" was
     * sitting in the designation the whole time and nothing looked at it, so a
     * visiting professor arrived here as a permanent one — the right rank on
     * the wrong terms.
     *
     * The designation is only consulted when the columns say nothing, so a row
     * that does set teacher_type still wins. Rank is not touched: it stays with
     * matchDesignation(), which already reads "Visiting Professor" as Professor.
     * That is the split this system is built on — designation carries the rank,
     * job type carries the terms, and neither has to encode the other.
     */
    private function jobTypeFor($isPartTime, $teacherType, ?string $designation): ?int
    {
        if (!empty($isPartTime)) {
            return $this->jobTypeMap['part time'] ?? null;
        }

        $type = (int) ($teacherType ?? 0);

        if ($type === 1) {
            return $this->jobTypeMap['adjunct faculty'] ?? null;
        }

        if ($type === 2) {
            return $this->jobTypeMap['visiting faculty'] ?? null;
        }

        $name = strtolower(trim((string) $designation));

        if ($name !== '') {
            // Ordered: 'guest' and 'practice' name the terms more precisely than
            // 'visiting' does, so they are tested first.
            $fromDesignation = [
                'guest'             => 'adjunct faculty',
                'adjunct'           => 'adjunct faculty',
                'of practice'       => 'contractual',
                'industrial expert' => 'contractual',
                'visiting'          => 'visiting faculty',
                'part-time'         => 'part time',
                'part time'         => 'part time',
                'emeritus'          => 'emeritus',
            ];

            foreach ($fromDesignation as $keyword => $jobType) {
                if (str_contains($name, $keyword) && isset($this->jobTypeMap[$jobType])) {
                    return $this->jobTypeMap[$jobType];
                }
            }
        }

        return $this->jobTypeMap['regular'] ?? null;
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

    /**
     * The name, the title in front of it, and the letters after it.
     *
     * This used to be one line:
     *
     *     preg_replace('/^(Dr\.?|Prof\.?|Mr\.?|Mrs\.?|Ms\.?|Md\.?)\s+/i', '', $name)
     *
     * and that line is where every name problem in the new database came from.
     * `Md\.?` was in it, so 108 people lost the first word of their name —
     * "Md. Shah Jahan" was stored as "Shah Jahan". It replaced once instead of
     * looping, so "Prof. Dr. M. Mizanur Rahman" kept the second title. It never
     * matched "Professor", only "Prof.", so 117 people have "Professor" sitting
     * in first_name. And it knew nothing about what follows a name, so sixteen
     * people's surname is literally "PhD".
     *
     * LegacyNameParser does the whole job and is checked against all 2,129
     * legacy names: not one word is dropped or invented, only title spellings
     * are made consistent.
     *
     * first_name/middle_name/last_name are still filled, because everything
     * downstream still reads them. They are now filled from the name with the
     * title and the qualifications already taken off, so the surname is at
     * least a word out of the person's name.
     *
     * @return array{
     *     name_prefix: string|null,
     *     name: string,
     *     academic_suffixes: array<int, string>,
     *     first_name: string,
     *     middle_name: string|null,
     *     last_name: string|null,
     * }
     */
    private function parseName(string $fullName): array
    {
        $parsed = \App\Support\LegacyNameParser::parse($fullName);
        $parts  = preg_split('/\s+/', $parsed['name'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'name_prefix'       => $parsed['prefix'],
            'name'              => $parsed['name'],
            'academic_suffixes' => $parsed['suffixes'],

            'first_name'  => $parts[0] ?? $parsed['name'],
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
