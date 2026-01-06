<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Drop old column if exists, careful with data, but for now we follow the plan to refactor
            // We'll keep the old column for now to allow data migration if needed, but make it nullable
            // $table->dropColumn('employment_status'); 
            
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->nullOnDelete();
            $table->foreignId('job_type_id')->nullable()->constrained('job_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign(['employment_status_id']);
            $table->dropForeign(['job_type_id']);
            $table->dropColumn(['employment_status_id', 'job_type_id']);
        });
    }
};
