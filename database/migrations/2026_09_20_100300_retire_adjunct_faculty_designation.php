<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes "Adjunct Faculty" out of the designations table.
 *
 * It is not an academic grade. The university awards Professor, Associate
 * Professor, Assistant Professor, Lecturer (Senior Scale) and Lecturer; being
 * adjunct is how somebody is engaged, and job_types has carried exactly that
 * since it was created — Regular, Part Time, Adjunct Faculty, Contractual,
 * Visiting Faculty, Emeritus. The same fact sat in two columns, and nothing
 * stopped a teacher from being designated Adjunct Faculty while their job type
 * said Visiting Faculty.
 *
 * Order matters here, because the point is to move the information rather than
 * delete it:
 *
 *  1. Anybody designated Adjunct Faculty whose job type says nothing definite
 *     — null, Regular, or the system placeholder — gets the Adjunct Faculty job
 *     type. The designation was the only record that they are adjunct, and
 *     Regular is what the importer fills in when it has nothing better, so the
 *     designation is the stronger evidence of the two.
 *  2. Their designation moves to the unassigned placeholder. Their old record
 *     named no grade and inventing one would be worse than admitting that.
 *  3. Only then is the row retired, and softly: teacher_versions payloads quote
 *     designation ids, the public API exposes them in filter URLs, and a
 *     soft-deleted row keeps both harmless while leaving the list clean.
 *
 * Reversing restores the row but cannot put the teachers back on it — by then
 * their job type says what they are, which is where it belonged all along.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adjunct = DB::table('designations')->where('name', 'Adjunct Faculty')->first();

        if (! $adjunct) {
            return;
        }

        $placeholder = DB::table('designations')
            ->where('name', 'System - Unassigned Designation')
            ->value('id');

        if (! $placeholder) {
            throw new RuntimeException(
                'Cannot retire the Adjunct Faculty designation: the '
                . '"System - Unassigned Designation" row is missing, and teachers.designation_id '
                . 'cannot be null. Run DesignationSeeder first.'
            );
        }

        $adjunctJobType = DB::table('job_types')->where('name', 'Adjunct Faculty')->value('id');

        if ($adjunctJobType) {
            $undecided = DB::table('job_types')
                ->whereIn('name', ['Regular', 'System - Unassigned'])
                ->pluck('id')
                ->all();

            DB::table('teachers')
                ->where('designation_id', $adjunct->id)
                ->where(function ($query) use ($undecided) {
                    $query->whereNull('job_type_id');

                    if ($undecided !== []) {
                        $query->orWhereIn('job_type_id', $undecided);
                    }
                })
                ->update(['job_type_id' => $adjunctJobType, 'updated_at' => now()]);
        }

        DB::table('teachers')
            ->where('designation_id', $adjunct->id)
            ->update(['designation_id' => $placeholder, 'updated_at' => now()]);

        DB::table('designations')
            ->where('id', $adjunct->id)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('designations')
            ->where('name', 'Adjunct Faculty')
            ->update(['deleted_at' => null, 'updated_at' => now()]);
    }
};
