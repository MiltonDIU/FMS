<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves shared role addresses off the login and onto secondary_email.
 *
 * headeee@, deanfsit@, headpharmacy@ and the rest belong to a post, not to a
 * person. The post changes hands and the address does not, so three different
 * teachers hold headpharmacy@ in the old database and three hold headeee@.
 * users.email carries a UNIQUE index, so only one of each could ever have been
 * a login; the others were given a mangled variant that reaches nobody.
 *
 * export:old-teachers now routes them to teacher_profile.secondary_email and
 * generates a login from the employee id instead. This brings the database
 * already imported into line with that, reading the same export file so the
 * two cannot disagree.
 *
 * Safe to run more than once: it writes only where the value differs, and a
 * login is replaced only when what is there now is itself a role address.
 */
class SyncTeacherSecondaryEmailsCommand extends Command
{
    protected $signature = 'teachers:sync-secondary-emails
                            {--file=teachers_export.json : Export file inside storage/app/public/exports/}
                            {--apply : Write the changes. Without it nothing is saved.}';

    protected $description = 'Apply secondary_email from the teacher export, moving shared role addresses off the login';

    /**
     * The same two lists ExportOldTeachersCommand classifies by, and matched
     * the same way — words anywhere in the local part, short tokens only at
     * the start. If these drift apart, this command starts disagreeing with
     * the file it is reading about what counts as a role address.
     */
    protected array $roleWords = [
        'dean', 'head', 'director', 'advisor', 'adviser', 'registrar',
        'controller', 'chancellor', 'chairman', 'principal', 'provost',
        'treasurer', 'coordinator', 'coordination', 'international', 'helpdesk',
    ];

    protected array $rolePrefixes = [
        'vc', 'vicechancellor', 'vice.chancellor', 'provc', 'pro-vc',
        'info', 'admin', 'support', 'office', 'contact',
    ];

    public function handle(): int
    {
        $path = storage_path('app/public/exports/' . $this->option('file'));

        if (! file_exists($path)) {
            $this->error("Export not found: {$path}");
            $this->line('Run: php artisan export:old-teachers');

            return Command::FAILURE;
        }

        $records = json_decode(file_get_contents($path), true);

        if (! is_array($records)) {
            $this->error("Invalid JSON in {$path}");

            return Command::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'Applying changes…' : 'DRY RUN — nothing will be written. Add --apply to save.');

        $stats = [
            'records with a secondary_email' => 0,
            'matched to a teacher' => 0,
            'no matching teacher' => 0,
            'secondary_email written' => 0,
            'secondary_email already correct' => 0,
            'login replaced (was a role address)' => 0,
            'login left alone (already personal)' => 0,
            'login clash, skipped' => 0,
        ];

        $changes = [];
        $unmatched = [];

        foreach ($records as $r) {
            $secondary = $r['teacher_profile']['secondary_email'] ?? null;

            if (blank($secondary)) {
                continue;
            }

            $stats['records with a secondary_email']++;

            $employeeId = trim((string) ($r['teacher_profile']['employee_id'] ?? ''));

            /*
             * Matched on employee_id, which is HR's own number and the only
             * identity the export and this database reliably share. A handful
             * of numbers are held by two rows — the same person imported twice
             * — and both get the address, because both are that person.
             */
            $teachers = $employeeId === ''
                ? collect()
                : DB::table('teachers')->whereNull('deleted_at')
                    ->whereRaw('TRIM(employee_id) = ?', [$employeeId])
                    ->get(['id', 'user_id', 'secondary_email', 'first_name', 'last_name']);

            if ($teachers->isEmpty()) {
                $stats['no matching teacher']++;
                $unmatched[] = [$r['user']['name'] ?? '?', $employeeId ?: '(none)', $secondary];

                continue;
            }

            foreach ($teachers as $t) {
                $stats['matched to a teacher']++;

                $currentEmail = DB::table('users')->where('id', $t->user_id)->value('email');
                $newPrimary = $r['user']['email'] ?? null;

                $row = [
                    'teacher_id' => $t->id,
                    'name' => trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? '')),
                    'employee_id' => $employeeId,
                    'secondary_before' => $t->secondary_email,
                    'secondary_after' => $secondary,
                    'login_before' => $currentEmail,
                    'login_after' => $currentEmail,
                ];

                // 1. The secondary address.
                if ((string) $t->secondary_email === (string) $secondary) {
                    $stats['secondary_email already correct']++;
                } else {
                    $stats['secondary_email written']++;

                    if ($apply) {
                        DB::table('teachers')->where('id', $t->id)
                            ->update(['secondary_email' => $secondary, 'updated_at' => now()]);
                    }
                }

                // 2. The login, but only if it is itself a role address.
                if ($this->isRoleEmail((string) $currentEmail)) {
                    if (blank($newPrimary)) {
                        $stats['login clash, skipped']++;
                    } elseif ($this->emailTakenByAnother((string) $newPrimary, (int) $t->user_id)) {
                        $stats['login clash, skipped']++;
                        $row['login_after'] = $currentEmail . '   (clash: ' . $newPrimary . ' already in use)';
                    } else {
                        $stats['login replaced (was a role address)']++;
                        $row['login_after'] = $newPrimary;

                        if ($apply) {
                            DB::table('users')->where('id', $t->user_id)
                                ->update(['email' => $newPrimary, 'updated_at' => now()]);
                        }
                    }
                } else {
                    $stats['login left alone (already personal)']++;
                }

                if ($row['secondary_before'] !== $row['secondary_after'] || $row['login_before'] !== $row['login_after']) {
                    $changes[] = $row;
                }
            }
        }

        $this->report($stats, $changes, $unmatched, $apply);

        return Command::SUCCESS;
    }

    protected function emailTakenByAnother(string $email, int $userId): bool
    {
        return DB::table('users')
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])
            ->where('id', '!=', $userId)
            ->exists();
    }

    protected function isRoleEmail(string $email): bool
    {
        $local = rtrim(mb_strtolower(explode('@', $email)[0] ?? ''), '0123456789');

        foreach ($this->roleWords as $w) {
            if (str_contains($local, $w)) {
                return true;
            }
        }

        foreach ($this->rolePrefixes as $p) {
            if ($local === $p || str_starts_with($local, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<int, array<string, mixed>>  $changes
     * @param  array<int, array<int, string>>  $unmatched
     */
    protected function report(array $stats, array $changes, array $unmatched, bool $apply): void
    {
        $this->newLine();
        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($changes !== []) {
            $this->newLine();
            $this->line('<comment>Changes' . ($apply ? ' applied' : ' that would be made') . ':</comment>');

            foreach ($changes as $c) {
                $this->line(sprintf('  id=%-6d %-28s emp=%s', $c['teacher_id'], mb_substr($c['name'], 0, 28), $c['employee_id']));

                if ($c['secondary_before'] !== $c['secondary_after']) {
                    $this->line(sprintf('        secondary: %s -> %s', $c['secondary_before'] ?? '(none)', $c['secondary_after']));
                }

                if ($c['login_before'] !== $c['login_after']) {
                    $this->line(sprintf('        login:     %s -> %s', $c['login_before'], $c['login_after']));
                }
            }
        }

        if ($unmatched !== []) {
            $this->newLine();
            $this->line('<comment>No teacher in this database carries these employee ids:</comment>');
            foreach ($unmatched as [$name, $emp, $sec]) {
                $this->line(sprintf('  %-34s emp=%-12s secondary=%s', mb_substr($name, 0, 34), $emp, $sec));
            }
        }

        $this->newLine();
        $this->info($apply ? 'Done.' : 'Nothing was written. Re-run with --apply to save.');
    }
}
