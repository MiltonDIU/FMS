<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AdministrativeRole;
use App\Models\Department;
use App\Models\DepartmentTeacher;
use App\Models\Teacher;
use App\Models\UserAdministrativeRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts a newly appointed Dean, Associate Dean, Head or Associate Head at the
 * top of the department listing.
 *
 *   Dean               → serial 1 in every department of the faculty
 *   Associate Dean     → serial 2 in every department of the faculty
 *   Head of Department → serial 3 in that department
 *   Associate Head     → serial 4 in that department
 *
 * A teacher already in the department keeps their row and only moves; one
 * who is not gets a row. The department is then renumbered: current office
 * holders first, in the order above, everyone else after them in the order
 * they already had. So when there is no Dean yet the Head is simply first,
 * and nobody is ever given a serial someone else already holds.
 *
 * Ending an appointment (inactive, end date, deleted, or moved to another
 * faculty or department) takes the teacher back out of the departments it
 * covered — except their own home department, and any department another
 * current appointment of theirs still covers. Those departments are then
 * renumbered, so the next holder moves up and no serial is left empty.
 */
final class DepartmentAdminPlacement
{
    /** Administrative role name (lower case) → rank in the department listing. */
    public const RANKS = [
        'dean' => 1,
        'associate dean' => 2,
        'head of department' => 3,
        'associate head' => 4,
    ];

    /** Ranks held over the whole faculty rather than one department. */
    private const FACULTY_RANKS = [1, 2];

    public static function place(UserAdministrativeRole $assignment): void
    {
        if (! $assignment->is_active || $assignment->end_date || $assignment->trashed()) {
            return;
        }

        $rank = self::rankOf($assignment->administrativeRole?->name);

        if ($rank === null) {
            return;
        }

        $teacher = Teacher::where('user_id', $assignment->user_id)->first();

        if (! $teacher) {
            return;
        }

        foreach (self::departmentsFor($assignment, $rank) as $departmentId) {
            DB::transaction(function () use ($teacher, $departmentId, $assignment): void {
                self::ensureMember($teacher, $departmentId, $assignment);
                self::renumber($departmentId);
            });
        }
    }

    /**
     * Undo place() for an appointment as it was before it ended or moved.
     *
     * $before carries the appointment's old values (role, user, faculty,
     * department); the database already holds the new state, so "still
     * covered by another appointment" is read from there.
     */
    public static function release(UserAdministrativeRole $before): void
    {
        $rank = self::rankOf(AdministrativeRole::whereKey($before->administrative_role_id)->value('name'));

        if ($rank === null) {
            return;
        }

        $teacher = Teacher::where('user_id', $before->user_id)->first();

        if (! $teacher) {
            return;
        }

        foreach (self::departmentsFor($before, $rank, activeOnly: false) as $departmentId) {
            DB::transaction(function () use ($teacher, $departmentId): void {
                $isHome = (int) $teacher->department_id === $departmentId;
                $stillCovered = array_key_exists((int) $teacher->user_id, self::rankedUsersIn($departmentId));

                if (! $isHome && ! $stillCovered) {
                    // One by one so each removal is in the activity log.
                    DepartmentTeacher::where('teacher_id', $teacher->id)
                        ->where('department_id', $departmentId)
                        ->get()
                        ->each
                        ->delete();
                }

                self::renumber($departmentId);
            });
        }
    }

    public static function rankOf(?string $roleName): ?int
    {
        return self::RANKS[strtolower(trim((string) $roleName))] ?? null;
    }

    /**
     * @return array<int, int>
     */
    private static function departmentsFor(UserAdministrativeRole $assignment, int $rank, bool $activeOnly = true): array
    {
        if (! in_array($rank, self::FACULTY_RANKS, true)) {
            return $assignment->department_id ? [(int) $assignment->department_id] : [];
        }

        $facultyId = $assignment->faculty_id
            ?? ($assignment->department_id ? Department::find($assignment->department_id)?->faculty_id : null);

        if (! $facultyId) {
            return [];
        }

        return Department::where('faculty_id', $facultyId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Already in the department: nothing to add, renumber() moves them.
     * A removed (soft-deleted) row is brought back rather than duplicated.
     */
    private static function ensureMember(Teacher $teacher, int $departmentId, UserAdministrativeRole $assignment): void
    {
        $row = DepartmentTeacher::withTrashed()
            ->where('teacher_id', $teacher->id)
            ->where('department_id', $departmentId)
            ->orderByRaw('deleted_at IS NULL DESC')
            ->first();

        if ($row && ! $row->trashed()) {
            return;
        }

        if ($row) {
            $row->restore();

            return;
        }

        DepartmentTeacher::create([
            'teacher_id' => $teacher->id,
            'department_id' => $departmentId,
            'job_type_id' => $teacher->job_type_id,
            'sort_order' => (int) DepartmentTeacher::where('department_id', $departmentId)->max('sort_order') + 1,
            'assigned_by' => $assignment->assigned_by ?? auth()->id(),
        ]);
    }

    /**
     * Office holders first by rank, everyone else after in their current order.
     */
    private static function renumber(int $departmentId): void
    {
        $rows = DepartmentTeacher::where('department_id', $departmentId)
            ->with('teacher:id,user_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $ranks = self::rankedUsersIn($departmentId);

        $ordered = $rows
            ->values()
            ->map(fn (DepartmentTeacher $row, int $i): array => [
                'row' => $row,
                'rank' => $ranks[$row->teacher?->user_id] ?? PHP_INT_MAX,
                'was' => $i,
            ])
            ->sort(fn (array $a, array $b): int => [$a['rank'], $a['was']] <=> [$b['rank'], $b['was']])
            ->values();

        foreach ($ordered as $i => $entry) {
            $serial = $i + 1;

            if ((int) $entry['row']->sort_order !== $serial) {
                // A query update: renumbering a whole department would
                // otherwise log one activity entry per teacher moved.
                DepartmentTeacher::whereKey($entry['row']->id)->update(['sort_order' => $serial]);
            }
        }
    }

    /**
     * Current office holders over this department: user id → best rank.
     *
     * @return array<int, int>
     */
    private static function rankedUsersIn(int $departmentId): array
    {
        $department = Department::find($departmentId);

        if (! $department) {
            return [];
        }

        $facultyDepartmentIds = Department::where('faculty_id', $department->faculty_id)->pluck('id')->all();

        /** @var Collection<int, UserAdministrativeRole> $current */
        $current = UserAdministrativeRole::query()
            ->with('administrativeRole:id,name')
            ->where('is_active', true)
            ->whereNull('end_date')
            ->where(function ($q) use ($department, $departmentId, $facultyDepartmentIds): void {
                $q->where('department_id', $departmentId)
                    ->orWhere('faculty_id', $department->faculty_id)
                    ->orWhereIn('department_id', $facultyDepartmentIds);
            })
            ->get();

        $ranks = [];

        foreach ($current as $assignment) {
            $rank = self::rankOf($assignment->administrativeRole?->name);

            if ($rank === null) {
                continue;
            }

            $overThisDepartment = in_array($rank, self::FACULTY_RANKS, true)
                // A Dean recorded against one of the faculty's departments
                // rather than the faculty itself still leads all of them.
                ? ((int) $assignment->faculty_id === (int) $department->faculty_id
                    || in_array($assignment->department_id, $facultyDepartmentIds))
                : (int) $assignment->department_id === $departmentId;

            if ($overThisDepartment) {
                $ranks[$assignment->user_id] = min($ranks[$assignment->user_id] ?? PHP_INT_MAX, $rank);
            }
        }

        return $ranks;
    }
}
