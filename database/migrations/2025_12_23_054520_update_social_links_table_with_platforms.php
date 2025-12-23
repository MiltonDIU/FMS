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
        Schema::table('social_links', function (Blueprint $table) {
            $table->foreignId('social_media_platform_id')
                  ->nullable()
                  ->after('teacher_id')
                  ->constrained('social_media_platforms')
                  ->cascadeOnDelete();

             $table->dropColumn('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_links', function (Blueprint $table) {
             $table->string('platform')->nullable();
             $table->dropForeign(['social_media_platform_id']);
             $table->dropColumn('social_media_platform_id');
        });
    }
};
