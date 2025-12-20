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
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // ERP Aligned Fields
            $table->string('title');
            $table->string('awarding_body')->nullable();
            $table->enum('type', ['award', 'scholarship', 'recognition', 'appreciation', 'other'])->default('award');
            $table->date('date')->nullable();
            $table->year('year')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();

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
        Schema::dropIfExists('awards');
    }
};
