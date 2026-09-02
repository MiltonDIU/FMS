<?php

namespace App\Jobs;

use App\Models\Teacher;
use App\Models\User;
use App\Services\ErpProfileFieldSync;
use App\Support\ErpProfileFields;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fills the selected profile fields for a set of teachers from the ERP.
 *
 * One job for the whole selection rather than one per teacher, because the
 * point of running it is the summary: which fields were filled, for how many
 * people, and who could not be found. Split across a thousand jobs there is
 * nothing left to report and no single place to look when the ERP is down.
 *
 * It is also why this is queued at all. Each teacher costs one HTTP call to a
 * server we do not control, so a selection of any size is far past what a web
 * request can wait for.
 */
class SyncErpTeacherProfilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Two hours. A full run is one outbound call per teacher, and the whole
     * faculty at a slow moment is a long way past the default 60 seconds.
     */
    public int $timeout = 7200;

    /**
     * Not retried. A retry would re-fetch every teacher in the selection,
     * including the ones already done, and the run is safe to simply start
     * again by hand once the reason it failed has been dealt with.
     */
    public int $tries = 1;

    /**
     * @param  array<int, int>  $teacherIds
     * @param  array<int, string>  $fields
     */
    public function __construct(
        protected array $teacherIds,
        protected array $fields,
        protected string $mode,
        protected ?int $requestedBy = null,
    ) {
    }

    public function handle(ErpProfileFieldSync $sync): void
    {
        $fields = ErpProfileFields::only($this->fields);

        if ($fields === [] || $this->teacherIds === []) {
            return;
        }

        $stats = ['updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'not_found' => 0, 'failed' => 0];

        // How many teachers each field was actually filled for, which is the
        // one number that says whether the run did what it was asked to.
        $filled = [];

        /*
         * Why the ones that did not work did not work, counted per reason.
         *
         * "22 failed" on its own sends the reader to the log file. Grouped, the
         * same run says eighteen were refused because the ERP only serves
         * academic staff — nothing to fix — and four were genuinely not found,
         * which is a data question worth asking. Same information, one of them
         * actionable.
         */
        $reasons = [];

        /*
         * Chosen fields that were not written, and why. A run that fills one
         * field out of nine otherwise looks like eight silent failures.
         */
        $untouched = [];

        Teacher::query()
            ->whereIn('id', $this->teacherIds)
            ->chunkById(50, function ($teachers) use ($sync, $fields, &$stats, &$filled, &$reasons, &$untouched): void {
                foreach ($teachers as $teacher) {
                    $result = $sync->sync($teacher, $fields, $this->mode);

                    $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;

                    foreach ($result['changed'] as $column) {
                        $filled[$column] = ($filled[$column] ?? 0) + 1;
                    }

                    foreach ($result['untouched'] ?? [] as $why => $columns) {
                        foreach ($columns as $column) {
                            $untouched[$why][$column] = ($untouched[$why][$column] ?? 0) + 1;
                        }
                    }

                    if (filled($result['message'])) {
                        $reasons[$result['message']] = ($reasons[$result['message']] ?? 0) + 1;
                    }

                    if (in_array($result['status'], ['failed', 'not_found'], true)) {
                        // The teacher's own ids stay here rather than in the
                        // notification, so one can be looked up when a reason
                        // needs chasing.
                        Log::warning('ERP profile sync could not complete for a teacher', [
                            'teacher_id' => $teacher->id,
                            'employee_id' => $teacher->employee_id,
                            'status' => $result['status'],
                            'reason' => $result['message'],
                        ]);
                    }
                }
            });

        $this->report($stats, $filled, $reasons, $untouched);
    }

    /**
     * Tells whoever started the run how it went.
     *
     * A queued run finishes with nobody watching, so the result has to find its
     * way back rather than waiting to be looked for. The panel already carries
     * database notifications, so it goes there.
     *
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $filled
     * @param  array<string, int>  $reasons
     */
    protected function report(array $stats, array $filled, array $reasons = [], array $untouched = []): void
    {
        $body = static::summaryBody($stats, $filled, $reasons, $untouched);

        Log::info('ERP profile sync finished', [
            'stats' => $stats,
            'filled' => $filled,
            'reasons' => $reasons,
            'untouched' => $untouched,
            'summary' => $body,
        ]);

        $user = $this->requestedBy ? User::find($this->requestedBy) : null;

        if (! $user) {
            return;
        }

        Notification::make()
            ->title(($stats['failed'] ?? 0) > 0 ? 'ERP profile sync finished with failures' : 'ERP profile sync finished')
            ->body($body)
            ->icon('heroicon-o-cloud-arrow-down')
            ->color(($stats['failed'] ?? 0) > 0 ? 'warning' : 'success')
            ->sendToDatabase($user);
    }

    /**
     * The sentence, and the reasons under it, that a finished run reports.
     *
     * Separate from sending so the wording can be exercised on its own — the
     * notification itself only writes inside a panel or worker context, which
     * makes the text impossible to check anywhere else.
     *
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $filled
     * @param  array<string, int>  $reasons
     * @param  array<string, array<string, int>>  $untouched
     */
    public static function summaryBody(array $stats, array $filled, array $reasons = [], array $untouched = []): string
    {
        $lines = [($stats['updated'] ?? 0) . ' teacher(s) updated'];

        /*
         * Only counts that happened. A line reading "0 already matched the ERP"
         * is noise on every run that had none, and it pushed the reasons — the
         * part worth reading — further down.
         */
        foreach ([
            'unchanged' => 'already matched the ERP',
            'not_found' => 'not found in the ERP',
            'skipped' => 'skipped',
            'failed' => 'failed',
        ] as $key => $label) {
            if (($stats[$key] ?? 0) > 0) {
                $lines[] = $stats[$key] . ' ' . $label;
            }
        }

        /*
         * The counts are one sentence; everything below gets its own line.
         * Run together they made a paragraph nobody reads to the end of, and
         * the field detail is the part being asked about.
         */
        $body = implode('. ', $lines) . '.';

        $lines = [];

        if ($filled !== []) {
            arsort($filled);

            $detail = [];

            foreach ($filled as $column => $count) {
                $detail[] = ErpProfileFields::labels([$column])[0] . ' (' . $count . ')';
            }

            $lines[] = 'Filled: ' . implode(', ', $detail);
        }

        /*
         * The fields that were asked for and not written.
         *
         * A run that ticks nine fields and reports one reads as eight silent
         * failures. These two lines are the difference between "the ERP has
         * nothing for this, stop expecting it" and "we already hold a value,
         * run again with overwrite if the ERP should win" — opposite next
         * steps, and neither one guessable from the filled list alone.
         */
        foreach ([
            ErpProfileFieldSync::UNTOUCHED_ALREADY_SET => 'Left alone, already on file',
            ErpProfileFieldSync::UNTOUCHED_NOT_SUPPLIED => 'Not sent by the ERP',
        ] as $why => $label) {
            $columns = $untouched[$why] ?? [];

            if ($columns === []) {
                continue;
            }

            arsort($columns);

            $detail = [];

            foreach ($columns as $column => $count) {
                $detail[] = ErpProfileFields::labels([$column])[0] . ' (' . $count . ')';
            }

            $lines[] = $label . ': ' . implode(', ', $detail);
        }

        foreach ($lines as $line) {
            $body .= "\n" . $line . '.';
        }

        if ($reasons !== []) {
            arsort($reasons);

            // Four is enough to see the shape of a run without turning a
            // notification into a report. The rest are in the log.
            $shown = array_slice($reasons, 0, 4, true);

            foreach ($shown as $reason => $count) {
                $body .= "\n• " . $count . ' × ' . rtrim($reason, '.');
            }

            if (count($reasons) > count($shown)) {
                $body .= "\n• and " . (count($reasons) - count($shown)) . ' other reason(s) — see the log';
            }
        }

        return $body;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ERP profile sync job failed outright', ['reason' => $e->getMessage()]);

        $user = $this->requestedBy ? User::find($this->requestedBy) : null;

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('ERP profile sync did not finish')
            ->body(\Illuminate\Support\Str::limit($e->getMessage(), 160))
            ->danger()
            ->sendToDatabase($user);
    }
}
