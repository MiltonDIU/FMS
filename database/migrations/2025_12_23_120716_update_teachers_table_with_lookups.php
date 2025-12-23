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
            // Drop old columns if they exist
            if (Schema::hasColumn('teachers', 'gender')) $table->dropColumn('gender');
            if (Schema::hasColumn('teachers', 'blood_group')) $table->dropColumn('blood_group');
            if (Schema::hasColumn('teachers', 'nationality')) $table->dropColumn('nationality');
            if (Schema::hasColumn('teachers', 'religion')) $table->dropColumn('religion');

            // Add new Foreign Keys
            if (!Schema::hasColumn('teachers', 'gender_id')) {
                $table->foreignId('gender_id')->nullable()->after('date_of_birth')->constrained('genders')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'blood_group_id')) {
                $table->foreignId('blood_group_id')->nullable()->after('gender_id')->constrained('blood_groups')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'nationality_id')) {
                $table->foreignId('nationality_id')->nullable()->after('blood_group_id')->constrained('nationalities')->nullOnDelete();
            }
            if (!Schema::hasColumn('teachers', 'religion_id')) {
                $table->foreignId('religion_id')->nullable()->after('nationality_id')->constrained('religions')->nullOnDelete();
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
            $table->dropForeign(['nationality_id']);
            $table->dropForeign(['religion_id']);
            $table->dropColumn(['gender_id', 'blood_group_id', 'nationality_id', 'religion_id']);

            // Add back old columns
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('blood_group')->nullable();
            $table->string('nationality')->default('Bangladeshi');
            $table->string('religion')->nullable();
        });
    }
};
