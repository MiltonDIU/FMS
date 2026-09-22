<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Folds one teacher profile into another and retires the one folded away.
 *
 * The migration brought the same person in twice. 26 employee ids are held by
 * two profiles each, and an employee id is HR's own number for a person, so
 * two live rows carrying one is almost always one person entered twice.
 *
 * What makes this worth a service rather than a few update queries is that the
 * data is spread across both rows rather than sitting on one. On 6 of those
 * groups the publications hang off the archived profile while the live one is
 * the person's real record, so "keep the row with the data" and "keep the row
 * that represents the person" are different answers. This moves the data to
 * whichever row is kept.
 *
 * Everything runs in one transaction. A half-moved profile is worse than
 * either of the two it started as.
 */
class TeacherMerger
{
    /**
     * Tables holding a teacher's own records, keyed by the column that points
     * at them. Every one of these simply changes owner.
     *
     * They are all empty today — the profile details have not been imported
     * yet — and listed anyway, because the day they fill is not the day anyone
     * will remember this list needs extending.
     */
    protected const OWNED_TABLES = [
        'educations' => 'teacher_id',
        'awards' => 'teacher_id',
        'certifications' => 'teacher_id',
        'job_experiences' => 'teacher_id',
        'memberships' => 'teacher_id',
        'research_interests' => 'teacher_id',
        'research_projects' => 'teacher_id',
        'skills' => 'teacher_id',
        'social_links' => 'teacher_id',
        'teaching_areas' => 'teacher_id',
        'training_experiences' => 'teacher_id',
        'teacher_versions' => 'teacher_id',
        'email_batch_recipients' => 'teacher_id',
    ];

    /**
     * Lookup rows stamped with who created them. Not the teacher's data, but
     * the foreign key still has to stop pointing at a retired profile.
     */
    protected const AUTHORED_TABLES = [
        'majors' => 'created_by',
        'organizations' => 'created_by',
        'positions' => 'created_by',
    ];

    /**
     * Columns worth carrying over when the surviving profile has nothing in
     * them. Only ever filled, never overwritten: the keeper is the better
     * record by definition, and its blanks are the only thing the other row
     * can safely improve.
     */
    protected const FILLABLE_GAPS = [
        'employee_id', 'joining_date', 'date_of_birth', 'phone', 'personal_phone',
        'secondary_email', 'scopus_id', 'present_address', 'permanent_address',
        'work_location', 'office_room', 'bio', 'photo', 'gender_id',
        'blood_group_id', 'country_id', 'religion_id',
    ];

    /**
     * What a merge would move, so it can be shown before it is done.
     *
     * @return array<string, int>
     */
    public function preview(Teacher $from, Teacher $into): array
    {
        if ($from->is($into)) {
            return [];
        }

        $alreadyAuthored = $this->publicationIdsFor($into);

        $counts = [
            'publications' => DB::table('publication_authors')
                ->where('authorable_type', Teacher::class)
                ->where('authorable_id', $from->id)
                ->whereNotIn('publication_id', $alreadyAuthored)
                ->count(),
            'publications already shared' => DB::table('publication_authors')
                ->where('authorable_type', Teacher::class)
                ->where('authorable_id', $from->id)
                ->whereIn('publication_id', $alreadyAuthored)
                ->count(),
            'department links' => DB::table('department_teacher')
                ->where('teacher_id', $from->id)
                ->whereNotIn('department_id', $this->departmentIdsFor($into))
                ->count(),
        ];

        foreach (self::OWNED_TABLES as $table => $column) {
            $n = DB::table($table)->where($column, $from->id)->count();

            if ($n > 0) {
                $counts[$table] = $n;
            }
        }

        $counts['fields filled in'] = count($this->gapsToFill($from, $into));

        return array_filter($counts, fn (int $n): bool => $n > 0);
    }

    /**
     * Moves everything from one profile onto another, then retires the first.
     *
     * The retired profile is soft-deleted rather than removed. Its id is quoted
     * all over the place — publication authorships that were already correct,
     * activity log entries, anything anybody bookmarked — and a soft delete
     * keeps those resolvable while taking the row out of every list.
     *
     * Its user account is deactivated rather than deleted, for the same reason
     * and one more: teachers.user_id carries a unique index, so the retired
     * profile keeps holding that user and the account cannot be handed to
     * anybody else by accident.
     *
     * @return array<string, int>  What actually moved.
     */
    public function merge(Teacher $from, Teacher $into): array
    {
        if ($from->is($into)) {
            return [];
        }

        return DB::transaction(function () use ($from, $into): array {
            $moved = [];

            /*
             * Authorships. The pivot has no unique key, so a publication both
             * profiles already author would end up on it twice and the paper
             * would list the same person as two of its authors. Those rows are
             * dropped rather than moved; the keeper is already on the paper.
             */
            $alreadyAuthored = $this->publicationIdsFor($into);

            $moved['publications'] = DB::table('publication_authors')
                ->where('authorable_type', Teacher::class)
                ->where('authorable_id', $from->id)
                ->whereNotIn('publication_id', $alreadyAuthored)
                ->update(['authorable_id' => $into->id, 'updated_at' => now()]);

            $moved['duplicate authorships dropped'] = DB::table('publication_authors')
                ->where('authorable_type', Teacher::class)
                ->where('authorable_id', $from->id)
                ->delete();

            // Department assignments, deduplicated the same way.
            $moved['department links'] = DB::table('department_teacher')
                ->where('teacher_id', $from->id)
                ->whereNotIn('department_id', $this->departmentIdsFor($into))
                ->update(['teacher_id' => $into->id, 'updated_at' => now()]);

            DB::table('department_teacher')->where('teacher_id', $from->id)->delete();

            /*
             * Academic suffixes. This pivot does carry a unique key on
             * (teacher, suffix), so an unconditional update would fail rather
             * than duplicate — the overlap has to be found first either way.
             */
            $heldSuffixes = DB::table('academic_suffix_teacher')
                ->where('teacher_id', $into->id)
                ->pluck('academic_suffix_id')
                ->all();

            $moved['academic suffixes'] = DB::table('academic_suffix_teacher')
                ->where('teacher_id', $from->id)
                ->whereNotIn('academic_suffix_id', $heldSuffixes)
                ->update(['teacher_id' => $into->id, 'updated_at' => now()]);

            DB::table('academic_suffix_teacher')->where('teacher_id', $from->id)->delete();

            foreach (self::OWNED_TABLES as $table => $column) {
                $n = DB::table($table)->where($column, $from->id)->update([$column => $into->id]);

                if ($n > 0) {
                    $moved[$table] = $n;
                }
            }

            foreach (self::AUTHORED_TABLES as $table => $column) {
                DB::table($table)->where($column, $from->id)->update([$column => $into->id]);
            }

            // Authors already pointed at the retired profile follow it.
            DB::table('authors')
                ->where('merged_into_teacher_id', $from->id)
                ->update(['merged_into_teacher_id' => $into->id]);

            $moved['fields filled in'] = $this->fillGaps($from, $into);

            /*
             * The webpage handle, which every public profile URL is built from
             * and which carries a unique index. It can only move once the
             * retired row has let go of it — a soft delete does not release a
             * unique index.
             */
            if (blank($into->webpage) && filled($from->webpage)) {
                $handle = $from->webpage;

                DB::table('teachers')->where('id', $from->id)->update(['webpage' => null]);
                DB::table('teachers')->where('id', $into->id)->update(['webpage' => $handle]);

                $moved['webpage handle'] = 1;
            }

            DB::table('users')->where('id', $from->user_id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            DB::table('teachers')->where('id', $from->id)->update([
                'is_archived' => true,
                'is_public' => false,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            return array_filter($moved, fn (int $n): bool => $n > 0);
        });
    }

    /**
     * The blank columns on the keeper that the retired profile can fill.
     *
     * @return array<string, mixed>
     */
    protected function gapsToFill(Teacher $from, Teacher $into): array
    {
        $fill = [];

        foreach (self::FILLABLE_GAPS as $column) {
            if (blank($into->getAttribute($column)) && filled($from->getAttribute($column))) {
                $fill[$column] = $from->getAttribute($column);
            }
        }

        return $fill;
    }

    protected function fillGaps(Teacher $from, Teacher $into): int
    {
        $fill = $this->gapsToFill($from, $into);

        if ($fill === []) {
            return 0;
        }

        /*
         * A query-builder update, not a save. TeacherObserver hands any edit to
         * TeacherVersionService, which would turn a data repair into a pending
         * profile change for the teacher to approve — for fields they never
         * typed and a merge they were not part of.
         */
        DB::table('teachers')->where('id', $into->id)->update($fill + ['updated_at' => now()]);

        return count($fill);
    }

    /** @return array<int, int> */
    protected function publicationIdsFor(Teacher $teacher): array
    {
        return DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->pluck('publication_id')
            ->all();
    }

    /** @return array<int, int> */
    protected function departmentIdsFor(Teacher $teacher): array
    {
        return DB::table('department_teacher')
            ->where('teacher_id', $teacher->id)
            ->pluck('department_id')
            ->all();
    }
}
