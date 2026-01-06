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
            $table->string('code'); // degree_code -> code (Not globally unique)
            $table->string('name'); // degree_full_name -> name
            $table->string('slug'); // degree_full_name -> name

            // Scoped Uniqueness
            $table->unique(['degree_level_id', 'code']);
            $table->unique(['degree_level_id', 'name']);
            $table->unique(['degree_level_id', 'slug']);

            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('degree_types');
    }
};
