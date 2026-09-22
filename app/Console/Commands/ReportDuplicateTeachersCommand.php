<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Lists the teacher profiles that are the same person twice.
 *
 * Read-only. It decides nothing and writes nothing; it exists so the merge can
 * be looked at before it is done, because two of the groups it finds must not
 * be merged at all and that is only visible once they are laid out.
 *
 * Two identities are considered, both asked for:
 *
 *  - employee_id. HR's own number for a person, so two live rows carrying the
 *    same one are almost always one person entered twice. 26 groups.
 *  - email. Currently finds nothing and cannot find anything: users.email
 *    carries a UNIQUE index, so the database refuses a second row with the
 *    same address. It is still checked rather than assumed, because the index
 *    could be dropped and because secondary_email has no such protection.
 *
 * Names are deliberately not an identity here. 56 groups of teachers share a
 * name while holding different employee_ids, and those are different people —
 * HR issued them separate numbers. Merging on a name would fuse them.
 */
class ReportDuplicateTeachersCommand extends Command
{
    protected $signature = 'teachers:duplicates
                            {--json= : Also write the full report to this path}
                            {--show-safe : List the groups that look safe to merge, not only the risky ones}';

    protected $description = 'Report teacher profiles sharing an employee_id or an email address, and what merging each group would move';

    /** The placeholder department every archived teacher is parked in. */
    protected ?int $unassignedDepartmentId = null;

    public function handle(): int
    {
        $this->unassignedDepartmentId = DB::table('departments')->where('code', 'SUD')->value('id');

        $groups = array_merge(
            $this->groupsBy('employee_id', fn () => DB::table('teachers')
                ->whereNull('deleted_at')
                ->whereNotNull('employee_id')
                ->whereRaw("TRIM(employee_id) <> ''")
                ->selectRaw('TRIM(employee_id) as k')
                ->groupBy('k')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('k')),
            $this->emailGroups(),
        );

        if ($groups === []) {
            $this->info('No teacher profiles share an employee_id or an email address.');

            return Command::SUCCESS;
        }

        $this->summarise($groups);
        $this->listGroups($groups);

        if ($path = $this->option('json')) {
            File::put($path, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->info("Full report written to {$path}");
        }

        return Command::SUCCESS;
    }

    /**
     * @param  \Closure(): \Illuminate\Support\Collection<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    protected function groupsBy(string $identity, \Closure $keys): array
    {
        $out = [];

        foreach ($keys() as $key) {
            $rows = DB::table('teachers as t')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoin('departments as d', 'd.id', '=', 't.department_id')
                ->whereNull('t.deleted_at')
                ->whereRaw("TRIM(t.{$identity}) = ?", [$key])
                ->select('t.id', 't.first_name', 't.middle_name', 't.last_name', 't.is_archived',
                         't.department_id', 't.joining_date', 't.employee_id', 't.profile_score',
                         'u.email', 'd.code as dept_code')
                ->orderBy('t.id')
                ->get();

            $out[] = $this->describe($identity, $key, $rows);
        }

        return $out;
    }

    /**
     * Teachers reachable at the same address, through their user account.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function emailGroups(): array
    {
        $keys = DB::table('teachers as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('t.deleted_at')
            ->whereNull('u.deleted_at')
            ->selectRaw('LOWER(TRIM(u.email)) as k')
            ->groupBy('k')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('k');

        $out = [];

        foreach ($keys as $key) {
            $rows = DB::table('teachers as t')
                ->join('users as u', 'u.id', '=', 't.user_id')
                ->leftJoin('departments as d', 'd.id', '=', 't.department_id')
                ->whereNull('t.deleted_at')
                ->whereRaw('LOWER(TRIM(u.email)) = ?', [$key])
                ->select('t.id', 't.first_name', 't.middle_name', 't.last_name', 't.is_archived',
                         't.department_id', 't.joining_date', 't.employee_id', 't.profile_score',
                         'u.email', 'd.code as dept_code')
                ->orderBy('t.id')
                ->get();

            $out[] = $this->describe('email', $key, $rows);
        }

        return $out;
    }

    /**
     * Everything worth knowing about one group before merging it.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    protected function describe(string $identity, string $key, $rows): array
    {
        $members = $rows->map(fn ($r): array => [
            'teacher_id' => (int) $r->id,
            'name' => $this->fullName($r),
            'email' => $r->email,
            'employee_id' => trim((string) $r->employee_id),
            'department' => $r->dept_code,
            'is_archived' => (bool) $r->is_archived,
            'joining_date' => $r->joining_date ? substr($r->joining_date, 0, 10) : null,
            'publications' => $this->publicationCount((int) $r->id),
            'department_links' => DB::table('department_teacher')->where('teacher_id', $r->id)->count(),
        ])->all();

        $keeper = $this->pickKeeper($rows);

        /*
         * Names that are not the same name. Four of these groups are one person
         * typed two ways ("s. m. mahmudur rahman" / "s.m. mahmudur rahman") and
         * one is two different people who were issued the same employee_id.
         * Both need a human; the difference between them is not mechanical.
         */
        $normalised = $rows->map(fn ($r) => $this->normalise($this->fullName($r)))->unique();
        $namesAgree = $normalised->count() === 1;

        // Both rows on the same paper: merging would double the authorship.
        $shared = DB::table('publication_authors')
            ->where('authorable_type', \App\Models\Teacher::class)
            ->whereIn('authorable_id', $rows->pluck('id'))
            ->selectRaw('publication_id, COUNT(*) as n')
            ->groupBy('publication_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('publication_id');

        $moving = collect($members)->where('teacher_id', '!=', $keeper)->sum('publications');

        return [
            'identity' => $identity,
            'key' => $key,
            'members' => $members,
            'proposed_keeper' => $keeper,
            'names_agree' => $namesAgree,
            'shared_publications' => $shared->all(),
            'publications_to_move' => $moving,
            'risk' => $this->risk($namesAgree, $shared->count(), $rows, $keeper),
        ];
    }

    /**
     * Which row should survive.
     *
     * Not the one with the content, deliberately. The surviving row is the one
     * that best represents the person as they are now — on staff, in a real
     * department, with a joining date — and the publications are carried over
     * to it. On 6 of these groups the publications sit on the archived row, so
     * a rule that kept whichever row held the data would leave the university
     * with its research filed under a retired profile.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    protected function pickKeeper($rows): int
    {
        return (int) $rows->sortBy([
            fn ($a, $b) => ($a->is_archived ? 1 : 0) <=> ($b->is_archived ? 1 : 0),
            fn ($a, $b) => ($a->department_id === $this->unassignedDepartmentId ? 1 : 0)
                <=> ($b->department_id === $this->unassignedDepartmentId ? 1 : 0),
            fn ($a, $b) => (blank($a->joining_date) ? 1 : 0) <=> (blank($b->joining_date) ? 1 : 0),
            fn ($a, $b) => $this->publicationCount((int) $b->id) <=> $this->publicationCount((int) $a->id),
            fn ($a, $b) => $a->id <=> $b->id,
        ])->first()->id;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    protected function risk(bool $namesAgree, int $sharedPublications, $rows, int $keeper): string
    {
        if (! $namesAgree) {
            return 'REVIEW — the two rows carry different names; this may be two people, not one';
        }

        if ($sharedPublications > 0) {
            return 'REVIEW — both rows author the same publication, so the authorship must be de-duplicated, not moved';
        }

        $holder = $rows->first(fn ($r) => $this->publicationCount((int) $r->id) > 0);

        if ($holder && (int) $holder->id !== $keeper && $holder->is_archived) {
            return 'CARE — the publications sit on the archived row and must be moved onto the keeper';
        }

        return 'safe';
    }

    protected function publicationCount(int $teacherId): int
    {
        static $cache = [];

        return $cache[$teacherId] ??= DB::table('publication_authors')
            ->where('authorable_type', \App\Models\Teacher::class)
            ->where('authorable_id', $teacherId)
            ->count();
    }

    protected function fullName(object $r): string
    {
        return trim(preg_replace('/\s+/', ' ',
            ($r->first_name ?? '') . ' ' . ($r->middle_name ?? '') . ' ' . ($r->last_name ?? '')));
    }

    protected function normalise(string $name): string
    {
        return trim(preg_replace('/[^a-z]/', '', mb_strtolower($name)));
    }

    /** @param array<int, array<string, mixed>> $groups */
    protected function summarise(array $groups): void
    {
        $byIdentity = [];
        $byRisk = [];

        foreach ($groups as $g) {
            $byIdentity[$g['identity']] = ($byIdentity[$g['identity']] ?? 0) + 1;
            $label = explode(' —', $g['risk'])[0];
            $byRisk[$label] = ($byRisk[$label] ?? 0) + 1;
        }

        $rows = [];
        foreach ($byIdentity as $k => $v) $rows[] = ["Groups sharing an {$k}", $v];
        foreach ($byRisk as $k => $v) $rows[] = ["  of those, {$k}", $v];

        $rows[] = ['Profiles involved', array_sum(array_map(fn ($g) => count($g['members']), $groups))];
        $rows[] = ['Profiles that would be retired', array_sum(array_map(fn ($g) => count($g['members']) - 1, $groups))];
        $rows[] = ['Publication authorships to move', array_sum(array_column($groups, 'publications_to_move'))];

        $this->table(['Metric', 'Count'], $rows);
    }

    /** @param array<int, array<string, mixed>> $groups */
    protected function listGroups(array $groups): void
    {
        $showSafe = (bool) $this->option('show-safe');

        usort($groups, fn ($a, $b) => [$a['risk'] === 'safe', $a['key']] <=> [$b['risk'] === 'safe', $b['key']]);

        foreach ($groups as $g) {
            if ($g['risk'] === 'safe' && ! $showSafe) {
                continue;
            }

            $this->newLine();
            $this->line("<comment>{$g['identity']} {$g['key']}</comment>  —  {$g['risk']}");

            foreach ($g['members'] as $m) {
                $this->line(sprintf(
                    '  %s id=%-6d %-30s %-26s dept=%-6s %s pubs=%-4d join=%s',
                    $m['teacher_id'] === $g['proposed_keeper'] ? '<info>KEEP</info>' : '    ',
                    $m['teacher_id'],
                    mb_substr($m['name'], 0, 30),
                    mb_substr((string) $m['email'], 0, 26),
                    $m['department'] ?? '-',
                    $m['is_archived'] ? 'archived' : 'active  ',
                    $m['publications'],
                    $m['joining_date'] ?? '-',
                ));
            }

            if ($g['shared_publications'] !== []) {
                $this->line('       shared publication ids: ' . implode(', ', $g['shared_publications']));
            }
        }

        if (! $showSafe) {
            $this->newLine();
            $this->line('<comment>Only the groups needing attention are shown. Add --show-safe for the rest.</comment>');
        }
    }
}
