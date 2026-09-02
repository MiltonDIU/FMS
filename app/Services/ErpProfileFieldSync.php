<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Teacher;
use App\Support\ErpProfileFields;
use Illuminate\Support\Str;

/**
 * Fills a teacher's personal and contact fields from their ERP profile.
 *
 * Everything it needs already exists: HrApiService fetches one employee's
 * profile, and IntegrationService::transform() runs it through the stored
 * `erp_teacher_profile` mapping — which is where the vendor's field names
 * (joinDate, workPhone, dateOfBirth) become our columns, and where a gender,
 * religion or nationality arrives as a name and leaves as an id. This class
 * exists to decide what of that result is allowed through, and whether it is
 * allowed to replace something already on file.
 */
class ErpProfileFieldSync
{
    /** Only write where we hold nothing. The safe default. */
    public const MODE_FILL_EMPTY = 'fill_empty';

    /** The ERP wins, even over a value already on file. */
    public const MODE_OVERWRITE = 'overwrite';

    /** Chosen, but we already held something and the mode said to keep it. */
    public const UNTOUCHED_ALREADY_SET = 'already_set';

    /** Chosen, but the ERP sent nothing for it. */
    public const UNTOUCHED_NOT_SUPPLIED = 'not_supplied';

    public function __construct(
        protected HrApiService $hrApi,
        protected IntegrationService $integration,
    ) {
    }

    /**
     * @param  array<int, string>  $fields  Columns to fill; anything off the whitelist is dropped.
     * @return array{status: string, changed: array<int, string>, message: ?string, untouched: array<string, array<int, string>>}
     */
    public function sync(Teacher $teacher, array $fields, string $mode = self::MODE_FILL_EMPTY): array
    {
        $fields = ErpProfileFields::only($fields);

        if ($fields === []) {
            return $this->result('skipped', message: 'No syncable field was selected.');
        }

        $employeeId = trim((string) $teacher->employee_id);

        if ($employeeId === '') {
            return $this->result('skipped', message: 'No employee id to look a profile up by.');
        }

        try {
            $profile = $this->hrApi->getTeacherProfile($employeeId);
        } catch (\Throwable $e) {
            // The other end is somebody else's server. It fails often enough
            // that the reason has to travel back as a result rather than as an
            // exception that stops the rest of the run.
            return $this->result('failed', message: Str::limit($e->getMessage(), 120));
        }

        if ($profile === null) {
            /*
             * The employee id is deliberately not in the message. A run reports
             * by grouping identical reasons, and an id in the text makes every
             * reason unique — six hundred lines that each say the same thing.
             * The id goes to the log, where looking one up is the point.
             */
            return $this->result('not_found', message: 'The ERP has no profile for this employee id.');
        }

        $slug = (string) Setting::get('teacher_integration_mapping', 'erp_teacher_profile');
        $incoming = $this->integration->transform($profile, $slug)['Teacher'] ?? [];

        $changed = [];

        /*
         * Why a chosen field was not written.
         *
         * Without this a run reports "Filled: Blood Group" and leaves the
         * operator unable to tell whether the other eight fields they ticked
         * were already on file or were never sent by the ERP. Those two answers
         * call for opposite next steps — run again with overwrite, or stop
         * expecting the field at all — so the run has to say which it was.
         */
        $untouched = [self::UNTOUCHED_ALREADY_SET => [], self::UNTOUCHED_NOT_SUPPLIED => []];

        foreach ($fields as $column) {
            $value = $incoming[$column] ?? null;

            // A blank from the ERP is an absence of information, not an
            // instruction to erase what we hold.
            if (blank($value)) {
                $untouched[self::UNTOUCHED_NOT_SUPPLIED][] = $column;

                continue;
            }

            if ($mode === self::MODE_FILL_EMPTY && filled($teacher->getAttribute($column))) {
                $untouched[self::UNTOUCHED_ALREADY_SET][] = $column;

                continue;
            }

            $teacher->setAttribute($column, $value);

            /*
             * isDirty rather than comparing the values by hand: joining_date
             * and date_of_birth are cast to dates, so the incoming string and
             * the stored Carbon are never equal as strings even when they are
             * the same day, and every run would report a change that was not one.
             */
            if ($teacher->isDirty($column)) {
                $changed[] = $column;
            }
        }

        if ($changed === []) {
            return $this->result('unchanged', untouched: $untouched);
        }

        /*
         * saveQuietly, deliberately.
         *
         * TeacherObserver::updating() hands any third-party edit to
         * TeacherVersionService, which turns it into a pending version for
         * approval and notifies the teacher. That is right for a person editing
         * someone else's profile in the admin panel. It is wrong here: the ERP
         * is the system of record for these ten fields, and routing a bulk
         * top-up through it would raise a thousand approval requests and a
         * thousand notifications for data nobody typed.
         *
         * The same reasoning the batch profile-score run and the photograph
         * fetch already use for their own system-driven writes.
         */
        $teacher->saveQuietly();

        return $this->result('updated', $changed, untouched: $untouched);
    }

    /**
     * @param  array<int, string>  $changed
     * @param  array<string, array<int, string>>  $untouched  keyed by UNTOUCHED_*
     * @return array{status: string, changed: array<int, string>, message: ?string, untouched: array<string, array<int, string>>}
     */
    protected function result(string $status, array $changed = [], ?string $message = null, array $untouched = []): array
    {
        return [
            'status' => $status,
            'changed' => $changed,
            'message' => $message,
            'untouched' => $untouched,
        ];
    }
}
