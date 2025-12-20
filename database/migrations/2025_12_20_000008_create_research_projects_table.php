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
        Schema::create('research_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // ERP Aligned Fields
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project_leader')->nullable();
            $table->string('funding_agency')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency')->default('BDT');
            $table->enum('role', ['pi', 'co_pi', 'researcher', 'consultant'])->default('researcher');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['ongoing', 'completed', 'pending'])->default('ongoing');
            $table->text('outcome')->nullable();

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
        Schema::dropIfExists('research_projects');
    }
};
