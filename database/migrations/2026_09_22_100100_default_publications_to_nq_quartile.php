<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Files every publication with no quartile under N/Q.
 *
 * N/Q already exists and already means this: the journal is not ranked in a
 * quartile. It read zero while 5,629 of 7,362 publications sat on a null
 * instead, because publications:import-pipeline only wrote the column when its
 * text lookup hit, and most rows have an empty Q-Index cell. The quartile
 * widget showed four slices for 1,733 publications and silently omitted the
 * other three quarters of the library.
 *
 * The importer no longer produces those nulls. This is for the ones already in
 * the table, so the fix does not depend on re-running a 7,378-row import.
 *
 * It only ever touches nulls. A publication somebody has put in Q1 by hand
 * stays in Q1.
 *
 * down() deliberately does nothing. Once these rows say N/Q they are
 * indistinguishable from the publications that always said N/Q, and guessing
 * which were which would put real data back to null.
 */
return new class extends Migration
{
    public function up(): void
    {
        // n-a is the current spelling, n-q what the seeder created before the
        // row was renamed. A database may hold either, so both are accepted.
        $nq = DB::table('publication_quartiles')
            ->whereIn('slug', ['n-a', 'n-q'])
            ->orderByRaw("FIELD(slug, 'n-a', 'n-q')")
            ->value('id');

        /*
         * Created rather than demanded when it is missing. Deploys run
         * migrations and never seeders, so a database that has not had
         * PublicationLookupSeeder run against it would fail the deploy on a
         * row this migration can perfectly well add itself — and it is the row
         * every unranked publication is about to point at.
         */
        if (! $nq) {
            $nq = DB::table('publication_quartiles')->insertGetId([
                'name' => 'N/A',
                'slug' => 'n-a',
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('publications')
            ->whereNull('publication_quartile_id')
            ->update(['publication_quartile_id' => $nq, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally empty — see the class comment.
    }
};
