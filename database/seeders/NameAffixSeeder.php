<?php

namespace Database\Seeders;

use App\Models\AcademicSuffix;
use App\Models\NamePrefix;
use Illuminate\Database\Seeder;

/**
 * The titles a teacher's name is written with, before it and after it.
 *
 * Every row here was read out of the old database rather than invented. All
 * 2,129 legacy names were parsed, and what came back was twenty-five different
 * spellings covering twelve actual titles: "Professor Dr.", "Prof. Dr.",
 * "Professor Dr" and "Prof.Dr." are one title written four ways, and "PhD",
 * "Ph.D" and "PhD." are one qualification written three. The spellings are
 * collapsed; the titles are all here, including the ones nobody would have
 * thought to list.
 *
 * Nothing speculative is seeded. An earlier draft of this file offered MBBS,
 * FCPS, LLB and a dozen more on the grounds that a university will need them
 * one day — but none of them appear on anybody, and a lookup list padded with
 * guesses is a list nobody trusts. When the medical faculty arrives, somebody
 * adds MBBS from the lookup screen in ten seconds, which is what the screen is
 * for. The import also creates anything it parses and cannot find, so a title
 * that turns up in data can never be silently dropped for want of a row here.
 *
 * The counts in the comments are how many of the 2,129 legacy names carry each
 * one; they are there so the next person can see at a glance which of these are
 * real and which are a long tail.
 */
class NameAffixSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Ordered by standing, not by frequency. This is a list a person reads
         * down to find their own title, and a professor should not have to
         * scroll past "Mr." to reach "Professor Dr.".
         *
         * "Ms. Mst." (4) and "Dr. Mst." (2) are two courtesy titles stacked on
         * one person. They look like somebody filling the same field twice, but
         * they are in the data on six real people, so they are listed rather
         * than quietly dropped — if they are wrong they should be corrected on
         * those six profiles, not hidden by leaving them out of the list.
         */
        $prefixes = [
            'Professor Dr. Engr.',  //   4
            'Professor Dr.',        //  82
            'Professor',            //  22
            'Dr. Engr.',            //   5
            'Dr. Mst.',             //   2
            'Dr.',                  // 273
            'Engr.',                //   3
            'Major',                //   1
            'Mr.',                  // 915
            'Mrs.',                 //   0 — no legacy name uses it, but Ms./Mst.
                                    //       are both here and its absence would
                                    //       read as an oversight on the form.
            'Ms. Mst.',             //   4
            'Ms.',                  // 526
            'Mst.',                 //   8
        ];

        foreach ($prefixes as $order => $name) {
            NamePrefix::updateOrCreate(
                ['name' => $name],
                ['sort_order' => ($order + 1) * 10, 'is_active' => true],
            );
        }

        /*
         * Two, because two is what the old database holds — sixteen people with
         * a PhD and one of them with an MBA as well.
         */
        $suffixes = [
            'PhD',  // 16
            'MBA',  //  1
        ];

        foreach ($suffixes as $order => $name) {
            AcademicSuffix::updateOrCreate(
                ['name' => $name],
                ['sort_order' => ($order + 1) * 10, 'is_active' => true],
            );
        }

        $this->command?->info('✔ ' . count($prefixes) . ' name prefixes, ' . count($suffixes) . ' academic suffixes');
    }
}
