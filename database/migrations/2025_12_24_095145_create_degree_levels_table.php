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
        Schema::create('degree_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // level_name -> name for Laravel convention
            $table->string('slug')->unique(); // level_order -> slug
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_report')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degree_levels');
    }
};
