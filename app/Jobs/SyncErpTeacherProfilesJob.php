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

        Teacher::query()
            ->whereIn('id', $this->teacherIds)
            ->chunkById(50, function ($teachers) use ($sync, $fields, &$stats, &$filled): void {
                foreach ($teachers as $teacher) {
                    $result = $sync->sync($teacher, $fields, $this->mode);

                    $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;

                    foreach ($result['changed'] as $column) {
                        $filled[$column] = ($filled[$column] ?? 0) + 1;
                    }

                    if ($result['status'] === 'failed') {
                        Log::warning('ERP profile sync failed for a teacher', [
                            'teacher_id' => $teacher->id,
                            'employee_id' => $teacher->employee_id,
                            'reason' => $result['message'],
                        ]);
                    }
                }
            });

        $this->report($stats, $filled);
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
     */
    protected function report(array $stats, array $filled): void
    {
        $lines = [
            $stats['updated'] . ' teacher(s) updated',
            $stats['unchanged'] . ' already matched the ERP',
        ];

        foreach (['not_found' => 'not found in the ERP', 'skipped' => 'skipped', 'failed' => 'failed'] as $key => $label) {
            if (($stats[$key] ?? 0) > 0) {
                $lines[] = $stats[$key] . ' ' . $label;
            }
        }

        if ($filled !== []) {
            arsort($filled);

            $detail = [];

            foreach ($filled as $column => $count) {
                $detail[] = ErpProfileFields::labels([$column])[0] . ' (' . $count . ')';
            }

            $lines[] = 'Filled: ' . implode(', ', $detail);
        }

        $body = implode('. ', $lines) . '.';

        Log::info('ERP profile sync finished', ['stats' => $stats, 'filled' => $filled]);

        $user = $this->requestedBy ? User::find($this->requestedBy) : null;

        if (! $user) {
            return;
        }

        Notification::make()
            ->title($stats['failed'] > 0 ? 'ERP profile sync finished with failures' : 'ERP profile sync finished')
            ->body($body)
            ->icon('heroicon-o-cloud-arrow-down')
            ->color($stats['failed'] > 0 ? 'warning' : 'success')
            ->sendToDatabase($user);
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
