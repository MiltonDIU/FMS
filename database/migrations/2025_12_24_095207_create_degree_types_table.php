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
        // 3. Degree Types Lookup Table
        Schema::create('degree_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('degree_level_id')->constrained('degree_levels')->CascadeOnDelete(); // level_id -> degree_level_id
            $table->string('code')->unique(); // degree_code -> code
            $table->string('name'); // degree_full_name -> name
            $table->string('slug'); // degree_full_name -> name
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degree_types');
    }
};
