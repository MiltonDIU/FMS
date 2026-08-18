<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether this author wrote under our own affiliation on this paper.
     *
     * The authors table already carries a standing, but it is the answer across
     * everything that author ever wrote: "has this person ever published as one
     * of ours". That is the wrong question for a publication page. A teacher who
     * joined in 2024 has papers from a previous employer, and counting those as
     * the university's output overstates it — the affiliation printed on the
     * paper is what decides, and it is per paper, not per person.
     *
     * Nullable on purpose. False means the export named somebody else's
     * institution against them; null means nothing has established it either way
     * — an older row, or an export whose author and affiliation columns did not
     * line up — and the two must not be read as the same thing.
     */
    public function up(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->boolean('used_our_affiliation')->nullable()->after('affiliation');
            $table->index(['publication_id', 'used_our_affiliation'], 'pub_authors_affiliation_index');
        });
    }

    public function down(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->dropIndex('pub_authors_affiliation_index');
            $table->dropColumn('used_our_affiliation');
        });
    }
};
