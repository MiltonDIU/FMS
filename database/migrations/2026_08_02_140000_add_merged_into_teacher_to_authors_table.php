<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that an external author turned out to be one of our teachers.
 *
 * The import matched publications to teachers by name, and a name written
 * differently — "Hossain Mohammad Reyad" for Mohammad Reyad Hossain, "K. A.
 * Momin" for Khondhaker Al Momin — landed in the authors table instead. 1,806
 * publications still have no teacher attached for that reason.
 *
 * Merging moves the pivot rows, so the papers reach the right profile. These
 * two columns are what remains afterwards: the author row is kept, retired
 * rather than deleted, and points at the teacher it became. Without that the
 * merge would be invisible — you could not see who had been folded into whom,
 * or tell a name that was merged from one that was never dealt with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->foreignId('merged_into_teacher_id')
                ->nullable()
                ->after('is_active')
                // A teacher who is removed leaves the author row behind rather
                // than taking it: the publications would still need an owner.
                ->constrained('teachers')
                ->nullOnDelete();

            // The foreign key brings its own index on the column, so looking up
            // "who was merged into this teacher" is covered without a second one.

            $table->timestamp('merged_at')->nullable()->after('merged_into_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            // Order matters: the constraint has to go before the column, and the
            // column takes its index with it.
            $table->dropConstrainedForeignId('merged_into_teacher_id');
            $table->dropColumn('merged_at');
        });
    }
};
