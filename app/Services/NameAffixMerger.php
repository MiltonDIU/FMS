<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSuffix;
use App\Models\NamePrefix;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Folds one name title into another and removes the one folded away.
 *
 * These lists exist because the old database wrote twelve titles twenty-five
 * ways, and the import collapsed the spellings it recognised. It cannot
 * recognise everything: "Ms. Mst." and "Dr. Mst." arrived as real rows on six
 * people, and a title somebody adds by hand next year will arrive the same way.
 * Merging is how those get tidied without opening six profiles one at a time.
 *
 * The two lists are joined to teachers differently — a prefix is one column on
 * the teacher, a suffix is a row in a pivot — so the two merges are not the
 * same operation. They are both here so that neither can quietly grow a rule
 * the other does not have.
 *
 * Everything runs in a transaction. A merge that moved half the teachers and
 * then failed would leave a list nobody could reason about.
 */
class NameAffixMerger
{
    /**
     * Moves every teacher from one prefix to another, then deletes the old one.
     *
     * A mass update rather than a save per teacher, deliberately: TeacherObserver
     * and TeacherVersionService watch this model, and saving 911 teachers one by
     * one would file 911 profile versions recording a correction to our own
     * lookup list. A query-builder update fires no model events, which is what
     * is wanted here — nobody's profile is changing, only which row its title
     * points at.
     *
     * @return int  How many teachers moved.
     */
    public function mergePrefix(NamePrefix $from, NamePrefix $into): int
    {
        if ($from->is($into)) {
            return 0;
        }

        return DB::transaction(function () use ($from, $into): int {
            $moved = Teacher::withTrashed()
                ->where('name_prefix_id', $from->id)
                ->update(['name_prefix_id' => $into->id]);

            $from->delete();

            return $moved;
        });
    }

    /**
     * Gives every teacher holding one suffix the other, then deletes the old one.
     *
     * The pivot carries a unique key on (teacher, suffix), so somebody who
     * already holds both would break an unconditional insert. They are found
     * first and only detached.
     *
     * The position a teacher wrote the old suffix in is kept when they are
     * given the new one: "PhD, MBA" is not "MBA, PhD", and a merge should not
     * quietly reorder somebody's qualifications.
     *
     * @return int  How many teachers ended up holding the surviving suffix
     *              because of this merge.
     */
    public function mergeSuffix(AcademicSuffix $from, AcademicSuffix $into): int
    {
        if ($from->is($into)) {
            return 0;
        }

        return DB::transaction(function () use ($from, $into): int {
            $already = DB::table('academic_suffix_teacher')
                ->where('academic_suffix_id', $into->id)
                ->pluck('teacher_id')
                ->all();

            $moving = DB::table('academic_suffix_teacher')
                ->where('academic_suffix_id', $from->id)
                ->whereNotIn('teacher_id', $already)
                ->get(['teacher_id', 'sort_order']);

            if ($moving->isNotEmpty()) {
                DB::table('academic_suffix_teacher')->insert(
                    $moving->map(fn ($row): array => [
                        'teacher_id' => $row->teacher_id,
                        'academic_suffix_id' => $into->id,
                        'sort_order' => $row->sort_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            // Everything on the old suffix goes, including the rows belonging to
            // people who already held both.
            DB::table('academic_suffix_teacher')->where('academic_suffix_id', $from->id)->delete();

            $from->delete();

            return $moving->count();
        });
    }

    /**
     * What a merge would do, so it can be said before it is done.
     *
     * @return array{moving: int, already: int}
     */
    public function preview(NamePrefix|AcademicSuffix $from, NamePrefix|AcademicSuffix $into): array
    {
        if ($from instanceof NamePrefix) {
            return [
                'moving' => Teacher::withTrashed()->where('name_prefix_id', $from->id)->count(),
                'already' => 0,
            ];
        }

        $already = DB::table('academic_suffix_teacher')
            ->where('academic_suffix_id', $into->id)
            ->pluck('teacher_id')
            ->all();

        $total = DB::table('academic_suffix_teacher')->where('academic_suffix_id', $from->id)->count();

        $overlap = DB::table('academic_suffix_teacher')
            ->where('academic_suffix_id', $from->id)
            ->whereIn('teacher_id', $already)
            ->count();

        return ['moving' => $total - $overlap, 'already' => $overlap];
    }
}
