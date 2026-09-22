<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Not Assigned" to grant_types.
 *
 * The other four name a funder. This one says we do not know of one, which is
 * a different statement and needs somewhere to live.
 *
 * Until now that state was a null grant_type_id, and a null is invisible: the
 * distribution widget filters on whereNotNull, so 937 of the 2,678 publications
 * then imported simply were not in the chart, and the chart's own total did not
 * add up to the number of publications. "Where did the rest go" is not a
 * question a dashboard should leave anybody asking.
 *
 * It goes in as a migration rather than only in PublicationLookupSeeder because
 * deploys run migrations and do not run seeders, so a row added only to the
 * seeder would never reach an existing database. The seeder carries it too, for
 * installs built from scratch.
 *
 * sort_order 99 keeps it last in every dropdown: it is where a publication ends
 * up, not something anybody should be picking first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('grant_types')->where('slug', 'not-assigned')->exists()) {
            return;
        }

        DB::table('grant_types')->insert([
            'name' => 'Not Assigned',
            'slug' => 'not-assigned',
            'is_active' => true,
            'sort_order' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('grant_types')->where('slug', 'not-assigned')->value('id');

        if (! $id) {
            return;
        }

        // The publications pointing here were the ones with no funding on
        // record, so they go back to being nulls rather than to another type.
        DB::table('publications')->where('grant_type_id', $id)->update([
            'grant_type_id' => null,
            'updated_at' => now(),
        ]);

        DB::table('grant_types')->where('id', $id)->delete();
    }
};
