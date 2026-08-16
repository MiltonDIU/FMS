<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopus author identifiers, against the person they belong to.
 *
 * A table rather than a column on teachers, because one person routinely has
 * several. Scopus builds author profiles by algorithm, and it splits people:
 * 2,057 of the names in the July export carry more than one identifier —
 * "Ahmed, Kawsar" has two, one with twenty papers and one with three. A single
 * column would hold the twenty and quietly lose the three.
 *
 * Polymorphic because the same identifier can end up against a teacher or
 * against an external author, and an external author who is later merged into a
 * teacher should not need its identifiers rewritten.
 *
 * Nothing populates this yet. It exists so that "match by Scopus author id" is
 * a real switch rather than one that can never do anything: the decisions made
 * in a review workbook are what will fill it, and from then on matching that
 * person needs no name comparison at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopus_author_ids', function (Blueprint $table) {
            $table->id();

            // Scopus writes them as long digit strings — 60594470100. Kept as a
            // string: they are identifiers, never arithmetic.
            $table->string('scopus_author_id', 32)->unique();

            $table->morphs('authorable');

            /*
             * How it came to be recorded — 'review' for a decision made in a
             * workbook, 'manual' for one typed in. Worth keeping: an identifier
             * accepted on a name guess deserves less trust than one confirmed
             * against an email, and only the source can tell them apart later.
             */
            $table->string('source', 32)->default('review');

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopus_author_ids');
    }
};
