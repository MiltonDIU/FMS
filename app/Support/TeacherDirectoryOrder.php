<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The order teachers are listed in on the public site.
 *
 * Rank first, and within a rank the people employed on our own terms before
 * everyone else. A Professor is a Professor, so all of them belong in one block
 * — but the 65 regular professors are the department's own faculty and the 42
 * adjunct, 31 visiting and 6 contractual ones are not, and a list that
 * interleaves them by first name says otherwise on every page.
 *
 * So: rank groups, priority orders within the group. Both things people asked
 * for at once — colleagues of the same standing stay together, and our own
 * staff lead.
 *
 * The priority is not written here. It is job_types.sort_order, which already
 * carries it (Regular 1, Part Time 2, Adjunct Faculty 3, Contractual 4,
 * Visiting Faculty 5, Emeritus 6), so changing the running order is an edit to
 * that table and not to this file — and the admin screen that maintains job
 * types is where somebody would look for it.
 *
 * Every frontend listing calls this. Four copies of the same four order clauses
 * is how the search boxes ended up disagreeing with each other, and an ordering
 * that differs between the faculty page and the department page is the same
 * bug wearing different clothes.
 */
final class TeacherDirectoryOrder
{
    /**
     * Where a teacher with no job type goes: after everyone who has one.
     *
     * None today — every active teacher carries one — but the column is
     * nullable, and NULL sorts first in MySQL. Without this the one row
     * somebody forgets to fill in would appear above the professors.
     */
    private const UNRANKED = 9999;

    /**
     * Applies the listing order.
     *
     * The caller must already have joined `designations` and `job_types`; both
     * frontend search components do, in their base query. It is a join rather
     * than a correlated subquery because these lists are paginated and sorted
     * in the database, and a subquery in ORDER BY would be evaluated per row.
     *
     * @param  Builder<\App\Models\Teacher>  $query
     * @param  bool  $administrativeFirst  Put the people holding an administrative
     *                                     role at the top, in the order those roles
     *                                     carry. Used by the separate block the
     *                                     themes draw above the faculty list.
     * @param  int|null  $departmentId  One department on screen: order as the admin
     *                                  panel's Department Teachers table does when
     *                                  filtered to it (see byRankThenSerial).
     * @param  bool  $byDepartment  No department on screen (faculty or home page):
     *                              the same order, with each teacher's serial
     *                              taken from their home department.
     * @return Builder<\App\Models\Teacher>
     */
    public static function apply(Builder $query, bool $administrativeFirst = false, ?int $departmentId = null, bool $byDepartment = false): Builder
    {
        // Designation first, the department's serial only within a designation,
        // then seniority. One department on screen: its serial. A faculty or
        // the whole university: each teacher's home-department serial. Both
        // the administration group and the list below it.
        if ($departmentId || $byDepartment) {
            return self::byRankThenSerial($query, $departmentId);
        }

        if ($administrativeFirst) {
            $query
                ->orderBy('admin_role_sort')
                ->orderBy('admin_user_sort');
        }

        return $query
            ->orderBy('designations.sort_order')
            ->orderByRaw('COALESCE(job_types.sort_order, ?)', [self::UNRANKED])
            // Within one rank on one job type, whatever order the department
            // has arranged by hand, and then alphabetical so it is at least
            // stable rather than whatever the database felt like returning.
            ->orderBy('teachers.sort_order')
            ->orderBy('teachers.first_name');
    }

    /**
     * The one order every public listing uses:
     *
     *   1. designation (Professor before Associate Professor, and so on)
     *   2. serial set in the admin panel (Department Teachers) — in the given
     *      department, or each teacher's home department when none is given
     *   3. joining date — whoever joined earlier
     *   4. date of birth — the elder
     *
     * The admin panel's Department Teachers table, filtered to a department,
     * sorts the same way, so the two read alike.
     *
     * The serial is a subquery rather than a join: department_teacher has no
     * unique key on (teacher, department), and a join would list a duplicated
     * row twice. Missing values go last rather than first (MySQL sorts NULL
     * first); the id only makes the order stable when everything else is
     * equal. Whether someone holds an administrative role is decided before
     * this, by which group they are listed in.
     *
     * The caller must already have joined `designations`.
     *
     * @param  Builder<\App\Models\Teacher>  $query
     * @return Builder<\App\Models\Teacher>
     */
    public static function byRankThenSerial(Builder $query, ?int $departmentId = null): Builder
    {
        $serial = '(SELECT MIN(dt_serial.sort_order) FROM department_teacher dt_serial'
            . ' WHERE dt_serial.teacher_id = teachers.id AND dt_serial.department_id = '
            . ($departmentId ? '?' : 'teachers.department_id')
            . ' AND dt_serial.deleted_at IS NULL)';

        return $query
            ->orderByRaw('COALESCE(designations.sort_order, ?)', [self::UNRANKED])
            ->orderByRaw("COALESCE({$serial}, ?)", $departmentId ? [$departmentId, PHP_INT_MAX] : [PHP_INT_MAX])
            ->orderByRaw('teachers.joining_date IS NULL')
            ->orderBy('teachers.joining_date')
            ->orderByRaw('teachers.date_of_birth IS NULL')
            ->orderBy('teachers.date_of_birth')
            ->orderBy('teachers.id');
    }
}
