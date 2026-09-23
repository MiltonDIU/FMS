<?php

use App\Support\PublicationTypeRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the publication types the PD export needs, and a Not Assigned row.
 *
 * publication_types was seeded with eight kinds in the university's own
 * wording. The PD export speaks Scopus, and four of the kinds it records —
 * Letter, Data Paper, Editorial, Erratum, and a few rarer ones — had nowhere
 * to go. Together with the Scopus names for kinds we did have, that left 7,203
 * of 17,762 publications with no type: every one of them from PD.
 *
 * The synonyms are handled by PublicationTypeRule rather than added here.
 * "Article" is a Journal Article and "Conference paper" a Conference
 * Proceeding; giving them rows would split one kind of output in two.
 *
 * A migration as well as a seeder entry because deploys run migrations and
 * never seeders, so a row added only to PublicationLookupSeeder would never
 * reach a database that already exists. Both read the same list.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PublicationTypeRule::additionalTypes() as $type) {
            if (DB::table('publication_types')->where('slug', $type['slug'])->exists()) {
                continue;
            }

            DB::table('publication_types')->insert($type + [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $slugs = array_column(PublicationTypeRule::additionalTypes(), 'slug');

        $ids = DB::table('publication_types')->whereIn('slug', $slugs)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Publications filed under a type that is going away go back to having
        // none, which is what they had before it existed.
        DB::table('publications')->whereIn('publication_type_id', $ids)->update([
            'publication_type_id' => null,
            'updated_at' => now(),
        ]);

        DB::table('publication_types')->whereIn('id', $ids)->delete();
    }
};
