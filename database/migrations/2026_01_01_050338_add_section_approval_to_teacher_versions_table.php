<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teacher_versions', function (Blueprint $table) {
            // Section-level approval tracking
            $table->json('approved_sections')->nullable()->after('is_active');
            $table->json('pending_sections')->nullable()->after('approved_sections');
            $table->json('rejected_sections')->nullable()->after('pending_sections');
            $table->json('section_remarks')->nullable()->after('rejected_sections');
            
            // Changed sections - what sections were modified in this version
            $table->json('changed_sections')->nullable()->after('section_remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_versions', function (Blueprint $table) {
            $table->dropColumn([
                'approved_sections',
                'pending_sections',
                'rejected_sections',
                'section_remarks',
                'changed_sections',
            ]);
        });
    }
};

