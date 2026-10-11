<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an author answered being put on a paper, either way.
 *
 * confirmed_at only says when somebody agreed; a refusal left no time at all.
 * A teacher's list of the papers others put them on shows who did it, when,
 * and when they answered, so that months later they can still see how a paper
 * came to be on — or kept off — their profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->dropColumn('answered_at');
        });
    }
};
