<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's research interests, one to a row.
 *
 * Built like teaching_areas, because it is the same shape of thing: a list a
 * teacher keeps, each entry standing on its own, ordered how they want it read.
 * It replaces a single comma-separated text column on `teachers`, which could
 * hold the words and nothing else — no description, no order anybody chose, and
 * no way to say that "Machine Learning, Vision" is two interests rather than
 * one oddly punctuated one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            $table->text('interest');
            $table->text('description')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_interests');
    }
};
