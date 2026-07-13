<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Fetch all teachers from old database that have study_leave = 1 or 2
            $oldLeaves = DB::connection('old_db')
                ->table('teacher')
                ->whereIn('study_leave', [1, 2])
                ->whereNotNull('employeeID')
                ->where('employeeID', '!=', '')
                ->get(['employeeID', 'study_leave']);

            foreach ($oldLeaves as $old) {
                $employeeId = trim($old->employeeID);
                $studyLeaveVal = (int) $old->study_leave;

                $statusId = $studyLeaveVal === 1 ? 3 : 2; // 1 -> 3 (Study Leave), 2 -> 2 (On Leave)

                // Update the teacher if they exist in the new DB and are not archived
                DB::table('teachers')
                    ->where('employee_id', $employeeId)
                    ->where('is_archived', false)
                    ->update([
                        'employment_status_id' => $statusId
                    ]);
            }
        } catch (\Throwable $e) {
            // Log or ignore if old_db is not accessible during migrations in other environments
            info("Skipped old_db sync in migration: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
