<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep what a profile looked like before each change.
 *
 * A version stored only the new data, and the comparison screen read the "old"
 * side from the live profile. For a pending version that is right; once the
 * version was approved the live profile *is* the new data, so both sides were
 * the same and what was there before was gone. Older versions compared against
 * whatever the profile had become since.
 *
 * previous_data — the changed sections as they stood when the change was
 *                 submitted (or applied, for a direct change).
 * replaced_data — each section as it stood at the moment it was approved or
 *                 rejected, which is what an approval actually replaced.
 *
 * "applied_directly" records a change that was published without approval —
 * an administrator's edit, or a section that does not require approval — so
 * those changes have a history too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_versions', function (Blueprint $table) {
            $table->json('previous_data')->nullable()->after('data');
            $table->json('replaced_data')->nullable()->after('previous_data');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE teacher_versions MODIFY status ENUM('draft', 'pending', 'approved', 'rejected', 'partially_approved', 'completed', 'applied_directly') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('teacher_versions')->where('status', 'applied_directly')->delete();

            DB::statement("ALTER TABLE teacher_versions MODIFY status ENUM('draft', 'pending', 'approved', 'rejected', 'partially_approved', 'completed') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('teacher_versions', function (Blueprint $table) {
            $table->dropColumn(['previous_data', 'replaced_data']);
        });
    }
};
