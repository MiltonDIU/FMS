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
        Schema::table('employment_statuses', function (Blueprint $table) {
            $table->boolean('allow_login')->default(true)->after('check_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_statuses', function (Blueprint $table) {
            $table->dropColumn('allow_login');
        });
    }
};
