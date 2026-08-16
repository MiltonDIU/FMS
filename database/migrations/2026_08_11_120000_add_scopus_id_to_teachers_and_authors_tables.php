<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Scopus identifier a person is known by, on the person's own row.
 *
 * `scopus_author_ids` already holds every identifier we have, and stays the
 * record of them: 55 of our teachers carry more than one today, because Scopus
 * splits an author across profiles when a name is spelled differently or an
 * affiliation changes. 440 identifiers sit against 374 people.
 *
 * This column is not a replacement for that table and cannot be — it holds one
 * identifier where 66 of those people have others. It is the one you would put
 * in a column on the teacher list, search a profile by, or paste into a Scopus
 * URL: the primary identifier, taken as the earliest recorded, with the rest
 * still reachable through the relation.
 *
 * Deliberately not unique. The constraint that stops two people claiming the
 * same profile lives on `scopus_author_ids.scopus_author_id`, where every route
 * into the system passes through it; a second copy of that rule here would only
 * add a second, less explicable, way for a write to be refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // A string, like the identifiers themselves: 60594470100 is a name
            // for a profile, never a number to do arithmetic on.
            $table->string('scopus_id', 32)->nullable()->after('employee_id');
            $table->index('scopus_id', 'teachers_scopus_id_index');
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->string('scopus_id', 32)->nullable()->after('email');
            $table->index('scopus_id', 'authors_scopus_id_index');
        });

        /*
         * Everything already bound, so the column arrives populated rather than
         * waiting on the next review to mean anything.
         *
         * Earliest row wins, which for the identifiers recorded so far means the
         * one the review bound first. Walked in PHP rather than done as a
         * correlated update: a few hundred rows either way, and this reads the
         * same on every driver the test suite runs against.
         */
        $owners = [
            'App\Models\Teacher' => 'teachers',
            'App\Models\Author' => 'authors',
        ];

        DB::table('scopus_author_ids')
            ->orderBy('id')
            ->get(['scopus_author_id', 'authorable_type', 'authorable_id'])
            ->groupBy(['authorable_type', 'authorable_id'])
            ->each(function ($byOwner, string $type) use ($owners) {
                if (! isset($owners[$type])) {
                    return;
                }

                foreach ($byOwner as $ownerId => $identifiers) {
                    DB::table($owners[$type])
                        ->where('id', $ownerId)
                        ->update(['scopus_id' => $identifiers->first()->scopus_author_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('teachers_scopus_id_index');
            $table->dropColumn('scopus_id');
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->dropIndex('authors_scopus_id_index');
            $table->dropColumn('scopus_id');
        });
    }
};
