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
        Schema::table('teachers', function (Blueprint $table) {

            // Add new Foreign Keys
            if (!Schema::hasColumn('teachers', 'gender_id')) {
                $table->foreignId('gender_id')->nullable()->after('date_of_birth')->constrained('genders')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'blood_group_id')) {
                $table->foreignId('blood_group_id')->nullable()->after('gender_id')->constrained('blood_groups')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'country_id')) {
                $table->foreignId('country_id')->nullable()->after('blood_group_id')->constrained('countries')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'religion_id')) {
                $table->foreignId('religion_id')->nullable()->after('country_id')->constrained('religions')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Drop FKs
            $table->dropForeign(['gender_id']);
            $table->dropForeign(['blood_group_id']);
            $table->dropForeign(['country_id']);
            $table->dropForeign(['religion_id']);
            $table->dropColumn(['gender_id', 'blood_group_id', 'country_id', 'religion_id']);
        });
    }
};
