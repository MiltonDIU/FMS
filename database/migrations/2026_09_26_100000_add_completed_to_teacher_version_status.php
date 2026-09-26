<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "completed" for a teacher version whose sections were decided both ways.
 *
 * TeacherVersionService writes it when some sections of a change are approved
 * and others rejected, and the versions table and TeacherVersion::scopeApproved
 * both read it — but the column was created without it. MySQL refused the
 * value, so the approval that settled the last section of a mixed decision
 * failed, and the version stayed pending with part of it already applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE teacher_versions MODIFY status ENUM('draft', 'pending', 'approved', 'rejected', 'partially_approved', 'completed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('teacher_versions')->where('status', 'completed')->update(['status' => 'approved']);

        DB::statement("ALTER TABLE teacher_versions MODIFY status ENUM('draft', 'pending', 'approved', 'rejected', 'partially_approved') NOT NULL DEFAULT 'draft'");
    }
};
