<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the name a title of its own, and takes away a column nothing reads.
 *
 * The teachers table already held four columns for one person's name —
 * first_name, middle_name, last_name and full_name — and that is the reason
 * the names are in the state they are in. This migration ends with three, not
 * five: it adds the prefix, which is genuinely separate information, and drops
 * full_name, which was never information at all.
 *
 * full_name goes because nothing has ever read it. The column is written by an
 * observer on every save, but Teacher defines getFullNameAttribute(), and an
 * accessor shadows a column of the same name — so every page, every export and
 * every email has been reading the parts rejoined, and the cleaned value in the
 * column has been sitting there unread. On 120 rows the two disagree outright:
 * the column says "Rafiqul Islam" and the screen says "Professor Rafiqul
 * Islam". Two sources of one fact, one of them invisible, is exactly the shape
 * of bug this whole exercise is about, and keeping it while fixing the rest
 * would be absurd.
 *
 * There is deliberately no `name` column here either, for the same reason. It
 * was in an earlier draft of this migration and it was a mistake: with
 * first_name, middle_name and last_name still being read by 65 files, a
 * separate `name` would be a second source that drifts the moment somebody
 * edits one of the parts on the form. The three parts stay the single stored
 * name until they are collapsed into one column — which is a change to those
 * 65 files, and belongs in its own piece of work rather than being smuggled in
 * behind a new column nobody reads yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('name_prefix_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('name_prefixes')
                ->nullOnDelete();
        });

        if (Schema::hasColumn('teachers', 'full_name')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('full_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('name_prefix_id');
            $table->string('full_name')->nullable()->after('last_name');
        });
    }
};
