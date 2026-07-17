<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('is_archived');
            $table->timestamp('last_viewed_at')->nullable()->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'last_viewed_at']);
        });
    }
};
