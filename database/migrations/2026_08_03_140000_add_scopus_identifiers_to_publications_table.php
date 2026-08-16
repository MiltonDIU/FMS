<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two identifiers that name a paper exactly.
 *
 * Until now a Scopus record could only be matched to ours by title, which is
 * the weakest thing to match on: a trailing full stop, a subtitle written with
 * a colon instead of a dash, or a "Retraction notice to ..." prefix and the two
 * no longer look alike. Of the 1,572 records in the July export, 1,568 carry a
 * DOI and every one carries an EID.
 *
 * Nullable and not unique. 17,510 publications already exist with neither, and
 * a unique index would refuse the second one the moment two rows are left empty
 * — indexed instead, because the point is to look them up quickly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Digital Object Identifier — 10.1109/ICIPCN67432.2026.11438785
            $table->string('doi', 191)->nullable()->after('journal_link')->index();

            // Scopus's own record id — 2-s2.0-105036506446. Present even on the
            // handful of records that have no DOI.
            $table->string('scopus_eid', 64)->nullable()->after('doi')->index();
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex(['doi']);
            $table->dropIndex(['scopus_eid']);
            $table->dropColumn(['doi', 'scopus_eid']);
        });
    }
};
