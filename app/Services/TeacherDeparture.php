<?php

namespace App\Services;

use App\Models\EmploymentStatus;
use App\Models\Teacher;
use App\Models\TeacherServicePeriod;
use App\Support\TeacherServicePeriods;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A teacher's departure, as HR records it.
 *
 * Until now a resignation reached the profile only when somebody remembered to
 * open it and change the status, so a teacher could leave in March and still be
 * listed in June. HR already knows the day; this lets it say so.
 *
 * Nothing new happens to the profile here. The departure is the same edit an
 * administrator makes on the Settings tab — an ending status and a leaving
 * date — and goes through the same path, so everything that edit sets off
 * follows from it without being repeated:
 *  - the observer's status cascade takes the profile off the public site and
 *    turns off sign-in (a retirement also archives it);
 *  - the open period of service closes with the date and the reason;
 *  - the change is kept in the version history under the HR account's name,
 *    and the teacher is told, exactly as for an administrator's edit.
 *
 * Approval is skipped for the same reason an administrator's edit skips it:
 * the office that knows is the one making the change.
 */
class TeacherDeparture
{
    public const STATUS_DEPARTED = 'departed';

    /** HR sent what the profile already says. Safe to send twice. */
    public const STATUS_UNCHANGED = 'unchanged';

    public function __construct(protected TeacherVersionService $versions)
    {
    }

    /**
     * The departure reasons HR may send — the statuses for which the profile
     * form insists on a leaving date. Not "archived": that is the old site's
     * leavers, a label for records, not something that happens to a person.
     *
     * @return array<int, string>
     */
    public static function reasons(): array
    {
        return TeacherServicePeriods::LEAVING_DATE_REQUIRED;
    }

    /**
     * @return array{status: string, teacher: Teacher, period: ?TeacherServicePeriod}
     *
     * @throws ValidationException when the leaving date comes before the teacher joined
     */
    public function record(Teacher $teacher, string $reason, Carbon $leavingDate): array
    {
        $status = EmploymentStatus::where('slug', $reason)->firstOrFail();

        // The rule the profile form applies, so HR cannot write what an
        // administrator would be refused.
        if ($teacher->joining_date && $leavingDate->lt($teacher->joining_date)) {
            throw ValidationException::withMessages([
                'leaving_date' => ['The leaving date is before the teacher joined ('
                    . $teacher->joining_date->toDateString() . ').'],
            ]);
        }

        $changed = DB::transaction(function () use ($teacher, $status, $leavingDate): bool {
            $changed = $this->versions->handleUpdateFromForm($teacher, [
                'employment_status_id' => $status->id,
                'leaving_date' => $leavingDate->toDateString(),
            ], skipApproval: true);

            /*
             * A departed teacher's app tokens go with them. Sign-in is closed
             * by the status, but a token issued before today would otherwise
             * keep working — Sanctum checks the token, not the profile.
             */
            if ($changed && ! $teacher->fresh()->login_allowed) {
                $teacher->user?->tokens()->delete();
            }

            return $changed;
        });

        $teacher->refresh();

        return [
            'status' => $changed ? self::STATUS_DEPARTED : self::STATUS_UNCHANGED,
            'teacher' => $teacher,
            'period' => TeacherServicePeriod::where('teacher_id', $teacher->id)->latest('id')->first(),
        ];
    }
}
