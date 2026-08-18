<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the single institution name kept against each author.
 *
 * It was written once and never revised — `$namedInstitution ?: $this->affiliation`
 * — so it held whichever institution an author was first seen at, on people who
 * are routinely at several. Shown as the subtitle of the affiliation badge on
 * the authors list, it read as a statement of where somebody works, which it
 * was not and could not be.
 *
 * `publication_authors.affiliation` now carries the line the export printed for
 * each author on each paper, which is where a multi-affiliation fact belongs.
 * Deriving the list from there gives every institution an author has written
 * under, with the papers to go with them, instead of the first one recorded.
 *
 * `used_our_affiliation` on this table stays. It answers a question the pivot
 * cannot: whether this row is one of our own people we failed to match by name,
 * or a collaborator who belongs in the authors table permanently. It is a
 * derived cache, rebuildable at any time with `scopus:backfill-affiliations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn('affiliation');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('affiliation')->nullable()->after('used_our_affiliation');
        });
    }
};
