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
            $table->string('webpage')->nullable()->unique()->after('employee_id');
            $table->string('employment_status')->default('active')->after('is_active');
            $table->boolean('is_archived')->default(false)->after('employment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['webpage', 'employment_status', 'is_archived']);
        });
    }
};
