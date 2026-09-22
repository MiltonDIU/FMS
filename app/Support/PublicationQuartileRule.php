<?php

namespace App\Support;

/**
 * Which quartile a publication sits in, read from whatever the source wrote.
 *
 * There are five rows in publication_quartiles and only four of them are
 * quartiles. N/Q is the fifth, and it is not a gap in the data — it is the
 * answer for a journal that is not ranked in one, which is most of them. So a
 * publication always has a quartile, and "not ranked" is a thing we know rather
 * than a thing we failed to record.
 *
 * publications:import-pipeline did not see it that way. It looked the text up
 * and left publication_quartile_id null whenever the lookup missed, which put
 * 5,629 of 7,362 publications outside all five rows: the N/Q row read zero
 * while three quarters of the library belonged in it. Two things landed there:
 *
 *  - 5,606 rows whose Q-Index cell is simply empty.
 *  - 38 rows that say "N/A". That is N/Q written differently, but the importer
 *    slugged it to "n-a", found no such row, and gave up.
 *
 * The other two publication importers already default to N/Q — see
 * ImportOldTeachersPublicationsCommand, which ends its lookup with
 * `?? $quartileMap['n/q']`, and ImportPublicationsCommand, which starts from it
 * and only moves off when it reads a Q. This pipeline was the one that did not,
 * so the rule here is theirs, written down once.
 *
 * Hence: Q1 to Q4 when the text says so, and N/Q for everything else — empty,
 * "N/A", "None", or anything unrecognised. Nothing is invented from citescore.
 * A citescore is a citation rate and a quartile is a ranking within a subject
 * field; 16 of the empty rows carry one, and deriving Q1 from it would be
 * putting a number in a column that nobody measured.
 */
class PublicationQuartileRule
{
    public const Q1 = 'q1';
    public const Q2 = 'q2';
    public const Q3 = 'q3';
    public const Q4 = 'q4';

    /** Not ranked in a quartile. The default, and a real answer. */
    public const NOT_QUARTILED = 'n-a';

    /**
     * Both spellings of that row, newest first.
     *
     * PublicationLookupSeeder created it as "N/Q" (slug n-q) and it was renamed
     * to "N/A" (slug n-a) to match the source, which writes N/A. Databases
     * seeded before the rename still hold n-q, so the id is looked up through
     * this list rather than through one hard-coded slug — otherwise the rename
     * silently turns every unranked publication back into a null, which is the
     * failure this class exists to prevent.
     */
    public const NOT_QUARTILED_SLUGS = ['n-a', 'n-q'];

    /**
     * The id of the not-ranked row, whichever spelling this database uses.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $bySlug  Quartiles keyed by slug.
     */
    public static function notQuartiledId($bySlug): ?int
    {
        foreach (self::NOT_QUARTILED_SLUGS as $slug) {
            if (isset($bySlug[$slug])) {
                return (int) $bySlug[$slug]->id;
            }
        }

        return null;
    }

    /**
     * The quartile slug for a raw Q-Index cell. Always returns a slug.
     *
     * Matched on the digit rather than on equality, so "Q1", "q1", " Q1 " and
     * "Quartile 1" all land on Q1 while anything without a 1-4 in it falls to
     * N/Q. The export currently holds only "Q1".."Q4", "N/A" and blanks, but
     * this cell is typed by hand and has already produced four spellings of
     * "External" in the neighbouring Funding column.
     */
    public static function slugFor(?string $qIndex): string
    {
        $text = strtolower(trim((string) $qIndex));

        if ($text === '') {
            return self::NOT_QUARTILED;
        }

        // Only ever Q (or the word) followed by the number, so that "n/a" and
        // "A1" cannot be read as a quartile.
        if (preg_match('/\bq(?:uartile)?\s*\.?\s*([1-4])\b/', $text, $m) === 1) {
            return 'q' . $m[1];
        }

        return self::NOT_QUARTILED;
    }
}
