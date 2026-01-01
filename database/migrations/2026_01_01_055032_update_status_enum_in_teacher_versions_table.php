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
            // Using raw SQL to ensure it works without doctrine/dbal
            \DB::statement("ALTER TABLE teacher_versions MODIFY COLUMN status VARCHAR(50) DEFAULT 'draft'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_versions', function (Blueprint $table) {
            // Revert to enum (approximate)
             \DB::statement("ALTER TABLE teacher_versions MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft'");
        });
    }
};
