<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update allow_login to false for all inactive employment statuses
        DB::table('employment_statuses')
            ->where('check_active', false)
            ->update(['allow_login' => false]);

        // 2. Update login_allowed to false for all teachers with inactive employment status
        DB::table('teachers')
            ->whereIn('employment_status_id', function ($query) {
                $query->select('id')
                    ->from('employment_statuses')
                    ->where('allow_login', false);
            })
            ->update(['login_allowed' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
