<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmploymentStatus;
use App\Models\Teacher;
use App\Models\TeacherServicePeriod;
use Illuminate\Support\HtmlString;

/**
 * A teacher's periods of service, kept in step with the three fields the
 * profile holds for the current one: joining date, leaving date and
 * employment status.
 *
 * The form only ever shows the current (or last) period. This keeps the
 * earlier ones: when a teacher leaves, the open period is closed; when they
 * come back — a serving status again, a new joining date, the leaving date
 * cleared — a new period opens and the old one stays as it was.
 *
 * Called from the teacher observer (profile saves, rollbacks), from the ERP
 * field sync (which saves quietly), and by teachers:sync-service-periods,
 * which puts every teacher right and is safe to run again.
 */
final class TeacherServicePeriods
{
    /** Statuses that end an appointment. "archived" is the old site's leavers. */
    public const ENDING_STATUSES = ['retired', 'resigned', 'terminated', 'archived'];

    /**
     * Statuses for which the form insists on a leaving date. Not "archived":
     * those came over from the old site, which never recorded one.
     */
    public const LEAVING_DATE_REQUIRED = ['retired', 'resigned', 'terminated'];

    public static function statusSlug(mixed $statusId): ?string
    {
        return filled($statusId) ? (self::statuses()[(int) $statusId]['slug'] ?? null) : null;
    }

    public static function isEndingStatus(mixed $statusId): bool
    {
        return in_array(self::statusSlug($statusId), self::ENDING_STATUSES, true);
    }

    public static function sync(Teacher $teacher, string $source = 'profile'): void
    {
        if (! $teacher->exists) {
            return;
        }

        $periods = TeacherServicePeriod::where('teacher_id', $teacher->id)->orderBy('id')->get();
        $open = $periods->firstWhere('ended', false);
        $joined = $teacher->joining_date?->toDateString();
        $left = $teacher->leaving_date?->toDateString();
        $reason = self::statuses()[(int) $teacher->employment_status_id]['name'] ?? null;

        if (self::isEndingStatus($teacher->employment_status_id)) {
            if ($open) {
                $open->update([
                    'joined_on' => $open->joined_on?->toDateString() ?? $joined,
                    'left_on' => $left,
                    'ended' => true,
                    'end_reason' => $reason,
                ]);

                return;
            }

            // Already ended: a later correction of the leaving date or reason
            // belongs to the period that ended last.
            if ($last = $periods->last()) {
                $last->update(array_filter(
                    ['left_on' => $left, 'end_reason' => $reason],
                    fn ($value): bool => $value !== null,
                ));

                return;
            }

            TeacherServicePeriod::create([
                'teacher_id' => $teacher->id,
                'joined_on' => $joined,
                'left_on' => $left,
                'ended' => true,
                'end_reason' => $reason,
                'source' => $source,
                'created_by' => auth()->id(),
            ]);

            return;
        }

        // Serving. A correction to the current joining date moves the open
        // period; with no open period the teacher has come back (or this is
        // their first), so a new one opens and earlier ones are left alone.
        if ($open) {
            if ($joined !== null && $open->joined_on?->toDateString() !== $joined) {
                $open->update(['joined_on' => $joined]);
            }

            return;
        }

        TeacherServicePeriod::create([
            'teacher_id' => $teacher->id,
            'joined_on' => $joined,
            'left_on' => null,
            'ended' => false,
            'source' => $source,
            'created_by' => auth()->id(),
        ]);
    }

    /** The periods as lines, for the profile form. */
    public static function describe(?Teacher $teacher): HtmlString
    {
        if (! $teacher) {
            return new HtmlString('—');
        }

        $lines = TeacherServicePeriod::where('teacher_id', $teacher->id)
            ->orderByRaw('joined_on is null')
            ->orderBy('joined_on')
            ->orderBy('id')
            ->get()
            ->map(function (TeacherServicePeriod $period): string {
                $from = $period->joined_on?->toFormattedDateString() ?? 'joining date unknown';
                $to = $period->ended
                    ? ($period->left_on?->toFormattedDateString() ?? 'leaving date unknown')
                    : 'present';
                $reason = $period->ended && $period->end_reason ? ' (' . e($period->end_reason) . ')' : '';

                return e($from) . ' → ' . e($to) . $reason;
            });

        return new HtmlString($lines->isEmpty() ? '—' : $lines->implode('<br>'));
    }

    /** @return array<int, array{slug: string, name: string}> */
    private static function statuses(): array
    {
        static $statuses = null;

        return $statuses ??= EmploymentStatus::withoutGlobalScopes()
            ->get(['id', 'slug', 'name'])
            ->mapWithKeys(fn (EmploymentStatus $status): array => [$status->id => ['slug' => $status->slug, 'name' => $status->name]])
            ->all();
    }
}
