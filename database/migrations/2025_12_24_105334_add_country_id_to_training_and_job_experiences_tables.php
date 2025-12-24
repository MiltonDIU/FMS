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
        Schema::table('training_experiences', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('id'); // adjust position if needed
        });

        Schema::table('job_experiences', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('id'); // adjust position if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_experiences', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });

        Schema::table('job_experiences', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
