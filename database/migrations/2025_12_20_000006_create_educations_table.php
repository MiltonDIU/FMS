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
        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // ERP Aligned Fields
            $table->string('level_of_education')->nullable();
            $table->string('degree');
            $table->string('field_of_study');
            $table->string('institution');
            $table->string('board')->nullable();
            $table->string('country')->default('Bangladesh');
            $table->year('passing_year')->nullable();
            $table->string('duration')->nullable();
            $table->string('result_type')->nullable();
            $table->decimal('cgpa', 4, 2)->nullable();
            $table->decimal('scale', 3, 1)->nullable();
            $table->decimal('marks', 5, 2)->nullable();
            $table->text('thesis_title')->nullable();
            $table->text('description')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('educations');
    }
};
