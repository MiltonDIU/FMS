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
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique(); // 'personal_info', 'education', etc.
            $table->string('section_label'); // 'Personal Information', 'Education Records'
            $table->boolean('requires_approval')->default(false);
            $table->text('description')->nullable();
            $table->json('fields')->nullable(); // Specific fields in this section
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('section_key');
            $table->index(['requires_approval', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_settings');
    }
};
