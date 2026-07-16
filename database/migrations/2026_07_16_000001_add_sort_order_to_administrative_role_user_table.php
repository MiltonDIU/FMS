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
        Schema::table('administrative_role_user', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_active');
        });

        // Backfill existing rows with a sequential order per administrative role
        \Illuminate\Support\Facades\DB::statement(
            'SET @row := 0, @role := ""'
        );
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE administrative_role_user
             SET sort_order = (@row := IF(@role = administrative_role_id, @row + 1, 1))
             WHERE (@role := administrative_role_id) IS NOT NULL
             ORDER BY administrative_role_id, id'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('administrative_role_user', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
