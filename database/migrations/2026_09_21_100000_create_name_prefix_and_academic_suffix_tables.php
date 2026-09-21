<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two lookups a teacher's name is written with.
 *
 * Both are tables rather than free text for the same reason: the old database
 * spells one title seven ways. "Professor Dr.", "Prof. Dr.", "Professor Dr" and
 * "Prof. Dr. Engr." are four of twenty-five spellings covering seven actual
 * titles, and "PhD", "Ph.D" and "PhD." are three spellings among sixteen people.
 * A directory that prints all of them looks careless, and no amount of care at
 * the keyboard fixes it — the next person types it their own way.
 *
 * A select does fix it, and it puts the list where a super admin can add to it
 * without a deployment, which is how every other lookup in this system works.
 *
 * Prefixes are a single choice holding the whole stack — "Professor Dr. Engr."
 * is one row, not three. The alternative, picking three atoms and composing
 * them, means deciding the order every time it is drawn; baking the order into
 * the row decides it once. There are only ever a handful of real combinations:
 * across 2,129 legacy names, twelve.
 *
 * Suffixes are many per teacher, because "PhD, MBA" is a real pair and the
 * medical faculty will bring MBBS and FCPS alongside each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('name_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_suffixes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_suffix_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_suffix_id')->constrained()->cascadeOnDelete();
            // The order this teacher writes them in: "PhD, MBA" is not "MBA, PhD".
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['teacher_id', 'academic_suffix_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_suffix_teacher');
        Schema::dropIfExists('academic_suffixes');
        Schema::dropIfExists('name_prefixes');
    }
};
