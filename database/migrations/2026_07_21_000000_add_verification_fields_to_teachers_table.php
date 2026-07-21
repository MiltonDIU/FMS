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
            if (!Schema::hasColumn('teachers', 'verification_status')) {
                $table->string('verification_status')->default('unverified')->after('profile_status');
            }
            if (!Schema::hasColumn('teachers', 'verification_token')) {
                $table->string('verification_token')->nullable()->after('verification_status');
            }
            if (!Schema::hasColumn('teachers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_token', 'verified_at']);
        });
    }
};
