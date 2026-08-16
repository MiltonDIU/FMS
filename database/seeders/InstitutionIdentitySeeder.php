<?php

namespace Database\Seeders;

use App\Helpers\Institution;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Writes the institution profile the Scopus matcher runs on.
 *
 * Strictly speaking the system does not need these rows: Institution::get falls
 * back to the same values in code, so a database that has never been seeded
 * matches exactly as one that has. What the rows buy is visibility — after a
 * migrate:refresh the Institution Identity page shows real saved values rather
 * than placeholders, and the profile can be read out of the database like any
 * other setting.
 *
 * The values are taken from Institution::DEFAULTS rather than repeated here, so
 * the seeded rows and the code fallback cannot drift apart.
 *
 * Unlike BrandingSettingsSeeder this does NOT overwrite what is already there.
 * The whole point of the page is that another university changes these, and a
 * db:seed on a live installation must not quietly reset their matching rules
 * back to Daffodil's — which would not error, it would simply stop recognising
 * their own people.
 */
class InstitutionIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        foreach (Institution::DEFAULTS as $key => $value) {
            $isList = is_array($value);

            /*
             * Everything about the row except what it says.
             *
             * Saving the page goes through Setting::set, which files every key
             * under group "system" and leaves the type at its column default —
             * so a row written that way is findable only by its key and reads
             * back as a raw string. Correcting the metadata on an existing row
             * is safe; correcting its value would not be, so the value is only
             * ever supplied when the row is being created.
             */
            $metadata = [
                'group' => 'institution',
                'type' => $isList ? 'json' : 'string',
                'label' => ucwords(str_replace('_', ' ', substr($key, strlen(Institution::PREFIX)))),
                'description' => static::DESCRIPTIONS[$key] ?? null,
                'is_public' => false,
                'sort_order' => $sort++,
            ];

            $existing = Setting::where('key', $key)->first();

            if ($existing) {
                $existing->update($metadata);

                continue;
            }

            Setting::create($metadata + [
                'key' => $key,
                'value' => $isList ? json_encode($value) : (string) $value,
            ]);
        }
    }

    /**
     * Why each row exists, for anybody reading the settings table directly
     * rather than the page.
     */
    protected const DESCRIPTIONS = [
        Institution::PREFIX . 'name' => 'Used to derive an affiliation pattern when none is set. Falls back to the branding site name.',
        Institution::PREFIX . 'short_name' => 'What our own authors are called in headings and workbook columns.',
        Institution::PREFIX . 'match_patterns' => 'Regular expressions an affiliation line counts as ours by. Empty derives one from the name.',
        Institution::PREFIX . 'not_us' => 'Institutions carrying our name that are not us. Counted only when a run says to.',
        Institution::PREFIX . 'email_domains' => 'Our own email domains. The student rule is applied only to these.',
        Institution::PREFIX . 'student_email_pattern' => 'Tested against the part before the @ to tell a student address from a staff one.',
        Institution::PREFIX . 'unit_aliases' => 'Scopus faculty and department wording that matches nothing in our own tables.',
    ];
}
