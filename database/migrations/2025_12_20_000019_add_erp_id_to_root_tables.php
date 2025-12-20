<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add ERP reference IDs to root tables for future ERP sync
     */
    public function up(): void
    {
        // Add erp_id to faculties table
        Schema::table('faculties', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_id')->nullable()->unique()->after('id');
        });

        // Add erp_id to departments table
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_id')->nullable()->unique()->after('id');
        });

        // Add erp_id to designations table
        Schema::table('designations', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_id')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faculties', function (Blueprint $table) {
            $table->dropColumn('erp_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('erp_id');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('erp_id');
        });
    }
};
