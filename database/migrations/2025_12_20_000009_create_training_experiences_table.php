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
        Schema::create('training_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // ERP Aligned Fields
            $table->string('title');
            $table->string('organization');
            $table->string('category')->nullable();
            $table->integer('duration_days')->nullable();
            $table->date('completion_date')->nullable();
            $table->year('year')->nullable();
            $table->string('country')->default('Bangladesh');
            $table->string('certificate_url')->nullable();
            $table->boolean('is_online')->default(false);
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
        Schema::dropIfExists('training_experiences');
    }
};
