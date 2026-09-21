<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates the academic ranks from the rows that are not ranks at all.
 *
 * "Adjunct Faculty" sits in this table beside Professor and Lecturer, but it is
 * not a grade the university awards — it is how somebody is engaged, and
 * job_types already carries it (Regular, Part Time, Adjunct Faculty,
 * Contractual, Visiting Faculty, Emeritus). The same fact was being stored in
 * two columns, and a teacher could end up with designation "Adjunct Faculty"
 * and job type "Visiting Faculty" at once, which is a contradiction the data
 * had no way to refuse.
 *
 * The row cannot simply be deleted: teachers point at it with a foreign key,
 * designation ids are quoted in the public API, and somebody whose old record
 * named no rank genuinely has none to put there. So it stays as a placeholder
 * and is marked as not-a-rank, which is what tells the rest of the system to
 * show such a teacher by their job type instead.
 *
 * is_active is deliberately left alone. Deactivating is for a grade no longer
 * awarded — Senior Lecturer will be that one day — and those must keep showing
 * on the profiles of the people who hold them.
 */
return new class extends Migration
{
    private const NOT_RANKS = [
        'Adjunct Faculty',
        'System - Unassigned Designation',
    ];

    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->boolean('is_rank')->default(true)->after('rank');
        });

        DB::table('designations')
            ->whereIn('name', self::NOT_RANKS)
            ->update(['is_rank' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('is_rank');
        });
    }
};
