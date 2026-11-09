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
     * @param  int|null  $departmentId  One department on screen: follow the serial
     *                                  set for it in the admin panel (Department
     *                                  Teachers, filtered to that department), so
     *                                  the site shows the order the admin arranged.
     *                                  The order below only breaks ties and places
     *                                  anyone the department has no serial for.
     * @return Builder<\App\Models\Teacher>
     */
    public static function apply(Builder $query, bool $administrativeFirst = false, ?int $departmentId = null, bool $byDepartment = false): Builder
    {
        if ($departmentId) {
            self::bySerial($query, $departmentId);
        } elseif ($byDepartment && ! $administrativeFirst) {
            self::byHomeDepartmentSerial($query);
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
     * A whole faculty on screen: department by department, in the order the
     * faculty lists its departments, and inside each one the serial set for
     * it in the admin panel — so a department's teachers stand in the same
     * order here as on the department's own page.
     *
     * A teacher is placed under their home department. The caller must
     * already have joined `departments` on teachers.department_id.
     *
     * @param  Builder<\App\Models\Teacher>  $query
     * @return Builder<\App\Models\Teacher>
     */
    public static function byHomeDepartmentSerial(Builder $query): Builder
    {
        $row = ' FROM department_teacher dt_serial'
            . ' WHERE dt_serial.teacher_id = teachers.id AND dt_serial.department_id = teachers.department_id'
            . ' AND dt_serial.deleted_at IS NULL';

        return $query
            ->orderBy('departments.sort_order')
            ->orderBy('departments.id')
            ->orderByRaw("COALESCE((SELECT MIN(dt_serial.sort_order){$row}), ?)", [PHP_INT_MAX])
            ->orderByRaw("COALESCE((SELECT MIN(dt_serial.id){$row}), ?)", [PHP_INT_MAX]);
    }

    /**
     * The teacher's serial in one department, from department_teacher.
     *
     * A subquery rather than a join: the table has no unique key on
     * (teacher, department), and a join would list a duplicated row twice.
     * Teachers without a serial there go after everyone who has one.
     *
     * @param  Builder<\App\Models\Teacher>  $query
     * @return Builder<\App\Models\Teacher>
     */
    public static function bySerial(Builder $query, int $departmentId): Builder
    {
        $row = ' FROM department_teacher dt_serial'
            . ' WHERE dt_serial.teacher_id = teachers.id AND dt_serial.department_id = ?'
            . ' AND dt_serial.deleted_at IS NULL';

        return $query
            ->orderByRaw("COALESCE((SELECT MIN(dt_serial.sort_order){$row}), ?)", [$departmentId, PHP_INT_MAX])
            // Several teachers can share a serial (imported rows, or two typed
            // the same number). The admin list breaks that tie by row id, so
            // this does too — otherwise the rank order below would decide, and
            // the two lists would disagree exactly where the serials collide.
            ->orderByRaw("COALESCE((SELECT MIN(dt_serial.id){$row}), ?)", [$departmentId, PHP_INT_MAX]);
    }
}
