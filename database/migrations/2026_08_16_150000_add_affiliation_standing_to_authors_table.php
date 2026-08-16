<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an external author ever wrote under our own affiliation.
 *
 * The authors table holds 7,347 rows and nothing tells them apart: every one is
 * a GA with a placeholder address, no Scopus id and no merge. Two very different
 * kinds of people are in there —
 *
 *   - genuine outsiders, a co-author at Southeast or BRAC, who belong there
 *     permanently and should never be offered as one of ours;
 *   - people who are ours, whose name was written differently enough that the
 *     matcher could not place them, and who ought to be merged into a teacher.
 *
 * Telling them apart is guesswork on the name alone, which is how "Hossain
 * Mohammad Reyad" ended up as an author rather than as Mohammad Reyad Hossain.
 * The export knows the answer — it prints an affiliation against every author —
 * and until now the import threw it away.
 *
 * Three states, and the third is the honest one for anything already here:
 * true (seen under our affiliation), false (only ever under somebody else's),
 * null (never established — no run has said either way).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->boolean('used_our_affiliation')
                ->nullable()
                ->after('scopus_id');

            // What the export named instead, so the answer to "then who were
            // they with?" is on the row rather than a run away.
            $table->string('affiliation')->nullable()->after('used_our_affiliation');

            // The column exists to be filtered on: "show me the authors who
            // never carried our name" is the whole point of it.
            $table->index('used_our_affiliation');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropIndex(['used_our_affiliation']);
            $table->dropColumn(['used_our_affiliation', 'affiliation']);
        });
    }
};
