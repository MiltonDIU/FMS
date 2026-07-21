<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds profile_score and profile_score_synced_at columns to teachers table.
     * profile_score is cached from ProfileGapEvaluator — updated via cron job.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Cached profile completion score (0–100), nullable until first sync
            $table->unsignedTinyInteger('profile_score')
                  ->nullable()
                  ->default(null)
                  ->after('is_archived')
                  ->comment('Cached profile completion % from ProfileGapEvaluator');

            // Last sync timestamp — shows when the score was last calculated
            $table->timestamp('profile_score_synced_at')
                  ->nullable()
                  ->default(null)
                  ->after('profile_score')
                  ->comment('Timestamp of last profile_score calculation');

            // Index for fast ORDER BY profile_score on dashboard
            $table->index('profile_score', 'teachers_profile_score_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('teachers_profile_score_idx');
            $table->dropColumn(['profile_score', 'profile_score_synced_at']);
        });
    }
};
