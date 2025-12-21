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
        $tables = ['genders', 'blood_groups', 'nationalities', 'religions'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['genders', 'blood_groups', 'nationalities', 'religions'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'sort_order')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('sort_order');
                });
            }
        }
    }
};
