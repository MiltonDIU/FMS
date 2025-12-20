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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designation_id')->constrained();

            // Personal Information (ERP Aligned)
            $table->string('employee_id')->unique()->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('personal_phone')->nullable();
            $table->string('secondary_email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('blood_group')->nullable();
            $table->string('nationality')->default('Bangladeshi');
            $table->string('religion')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Professional Information
            $table->date('joining_date')->nullable();
            $table->string('work_location')->nullable();
            $table->string('office_room')->nullable();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->text('research_interest')->nullable();
            $table->string('personal_website')->nullable();
            $table->string('google_scholar')->nullable();
            $table->string('research_gate')->nullable();
            $table->string('orcid')->nullable();

            // Profile Status
            $table->enum('profile_status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'designation_id']);
            $table->index('profile_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
