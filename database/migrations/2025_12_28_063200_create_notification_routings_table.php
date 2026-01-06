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
        Schema::create('notification_routings', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_type'); // 'teacher_profile_update', 'publication_added', etc.
            $table->string('trigger_section')->nullable(); // 'education', 'publications', etc.
            $table->enum('recipient_type', ['role', 'user', 'department_head', 'custom']);
            $table->string('recipient_identifier')->nullable(); // role name, user id, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['trigger_type', 'trigger_section']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_routings');
    }
};
