<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether each author on a paper has agreed to be on it.
 *
 * Until now naming somebody as an author was enough: a teacher adding a paper
 * could put any colleague on it, and it appeared on that colleague's public
 * profile, in their counts and in incentive reports at once, without their
 * knowing. That is a way to attach somebody's name to a paper they never
 * wrote. A teacher named by another teacher now starts as `pending` and is
 * credited only once they confirm; `declined` keeps the refusal, so the same
 * person cannot simply be named again.
 *
 * The column defaults to `confirmed`. Every row already here is therefore
 * confirmed — nobody's profile loses a paper — and every writer that is not a
 * teacher naming a colleague (the imports, Scopus, an administrator) goes on
 * writing confirmed rows without having to know the column exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->string('confirmation_status', 16)->default('confirmed')->after('author_role');
            $table->foreignId('added_by')->nullable()->after('confirmation_status')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('added_by');

            $table->index(['authorable_type', 'authorable_id', 'confirmation_status'], 'pub_authors_confirmation_index');
        });
    }

    public function down(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->dropIndex('pub_authors_confirmation_index');
            $table->dropConstrainedForeignId('added_by');
            $table->dropColumn(['confirmation_status', 'confirmed_at']);
        });
    }
};
