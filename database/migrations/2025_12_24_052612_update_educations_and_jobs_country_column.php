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
        $tables = ['educations', 'job_experiences', 'training_experiences'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'country')) {
                    $table->dropColumn('country');
                }
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['educations', 'job_experiences', 'training_experiences'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['country_id']);
                $table->dropColumn('country_id');
                $table->string('country')->default('Bangladesh')->nullable();
            });
        }
    }
};
