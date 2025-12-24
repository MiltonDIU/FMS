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
            // Degree & Field
            $table->foreignId('degree_type_id')->nullable()->constrained('degree_types')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('result_type_id')->nullable()->constrained('result_types')->nullOnDelete();
            // Conditional Result Fields (based on result_type_id)
            $table->decimal('cgpa', 4, 2)->nullable(); // For CGPA/GPA (e.g., 3.75)
            $table->decimal('scale', 3, 1)->nullable(); // For CGPA/GPA Scale (e.g., 4.0, 5.0)
            $table->decimal('marks', 5, 2)->nullable(); // For Percentage (e.g., 85.50)
            $table->string('grade', 50)->nullable(); // For Grade/Division (e.g., "First Class", "A+")
            // Institution Details
            $table->string('institution');
            // Timeline
            $table->year('passing_year')->nullable();
            $table->string('duration', 50)->nullable(); // e.g., "4 years"
            // Result Details

            // Additional Details
            $table->text('major')->nullable(); // For Masters/PhD
            // Sorting
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            // Indexes
            $table->index('teacher_id');
            $table->index('degree_type_id');
            $table->index('result_type_id');
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
