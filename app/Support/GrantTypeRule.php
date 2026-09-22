<?php

namespace App\Support;

/**
 * Who paid for the paper, expressed as a grant type.
 *
 * grant_type_id was arriving null for all 7,378 publications, because the
 * pipeline never wrote it and the importer read a key the file did not have.
 * The CSV does carry the answer, in two separate places that turn out not to
 * overlap at all:
 *
 *  - An award the university paid. Either a figure in "Award Money" or the
 *    word "paid" in it — that is DIU's own incentive scheme, so the paper sits
 *    under a DIU project. 1,760 rows.
 *  - The "Funding" column, where it is written in words: External (1,360),
 *    DIU (228), Self (4).
 *
 * Every row with an award has an empty Funding column and every row with a
 * Funding value has no award, so the two never contradict each other. The
 * award is still checked first: money DIU actually paid out is a fact about
 * this university, and the funding column is somebody's note.
 *
 * Neither cell says anything on ~4,000 rows, and for those the author list
 * answers instead: a paper with one of our own teachers on it is DIU research
 * whether or not the incentive has been paid. Plenty of it has not been paid
 * yet — an award is applied for, approved and disbursed long after the paper
 * appears — and the older rule read that delay as an absence of funding.
 *
 * It is the last thing tried, never the first. Award Money and the Funding
 * column are what the university wrote down; a teacher's name is an inference
 * from it. So a paper whose Funding column says External stays External even
 * with five DIU teachers on it: somebody recorded who paid, and that beats a
 * guess drawn from the byline.
 *
 * Whatever is left over is Not Assigned, and Not Assigned is a real row in
 * grant_types rather than a null. The distribution widget filters on
 * whereNotNull, so every null was silently missing from the chart and the chart
 * did not total the number of publications.
 *
 * On the 489 papers that come out DIU Project while their collaboration comes
 * out Collaboration (External): that pairing is correct and was checked before
 * it was left standing. 407 of them are marked VF in the CSV's "Researcher Name
 * with Designation" — visiting faculty, who are external people by definition
 * and live in the authors table rather than among our teachers. DIU still paid
 * them, and a paper DIU paid for is a DIU project whoever wrote it. Asked and
 * confirmed; do not "fix" it by making a DIU award conditional on a DIU teacher
 * being on the paper, which would drop 487 rows to null rather than move them
 * anywhere useful — their Funding column is empty too.
 */
class GrantTypeRule
{
    public const DIU = 'diu-project';
    public const EXTERNAL = 'external-project';
    public const SELF = 'self-funded';

    /** Nothing on record names a funder. A real row, not a null. */
    public const NOT_ASSIGNED = 'not-assigned';

    /**
     * The grant type slug for one publication row. Always returns a slug.
     *
     * In order, strongest evidence first:
     *  1. DIU paid an award for it              → DIU Project
     *  2. The Funding column names a source     → DIU / External / Self
     *  3. One of our teachers is an author      → DIU Project (incentive may
     *                                             simply not be paid yet)
     *  4. Nothing above                         → Not Assigned
     *
     * @param  string|null  $awardMoney  The raw "Award Money" cell.
     * @param  string|null  $funding  The raw "Funding" cell.
     * @param  array<int, array<string, mixed>>  $authors  Authors carrying
     *         `authorable_type`, as resolved by step 3 of the convert pipeline.
     *         Passing an unresolved list only means step 3 is skipped.
     */
    public static function slugFor(?string $awardMoney, ?string $funding, array $authors = []): string
    {
        if (static::isDiuAward($awardMoney)) {
            return self::DIU;
        }

        $fromNote = static::fromFundingNote($funding);

        if ($fromNote !== null) {
            return $fromNote;
        }

        return static::hasOurTeacher($authors) ? self::DIU : self::NOT_ASSIGNED;
    }

    /**
     * The grant type for a publication carried over from the old site.
     *
     * That export has no Award Money and no Funding column — only a title, a
     * year and the teacher it belongs to. What it does have is the teacher, and
     * a teacher has a joining date, so the question it can answer is whether
     * the paper was written while they were here:
     *
     *  - published in or after their joining year  → DIU Project
     *  - published before it                       → Self Funded
     *  - either date missing                       → Not Assigned
     *
     * The joining year counts as inside. The export records a year and not a
     * date, so for a paper published in the year somebody joined there is no
     * way to tell which side of the joining date it fell; they were at DIU for
     * part of that year, and it is credited to DIU. 1,141 papers turn on this.
     *
     * Self Funded rather than External Project for the earlier ones: all that
     * is actually known is that DIU did not pay for it. Who did — a previous
     * employer, a grant, the researcher — is not in the file, and Self Funded
     * is the reading chosen for it.
     *
     * @param  int|null  $publicationYear
     * @param  \Carbon\CarbonInterface|null  $joiningDate
     */
    public static function slugForOldSitePublication(?int $publicationYear, $joiningDate): string
    {
        if (! $publicationYear || $publicationYear <= 0 || blank($joiningDate)) {
            return self::NOT_ASSIGNED;
        }

        return $publicationYear >= (int) $joiningDate->format('Y')
            ? self::DIU
            : self::SELF;
    }

    /**
     * Whether any author on the paper is one of our own teachers.
     *
     * Asked of ResearchCollaborationRule rather than counted here, so that
     * "is a DIU teacher on this paper" means precisely the same thing to the
     * grant type as it does to the collaboration. Both of the collaborations
     * that involve us — pure DIU, and visiting faculty alongside DIU — count.
     *
     * @param  array<int, array<string, mixed>>  $authors
     */
    public static function hasOurTeacher(array $authors): bool
    {
        return in_array(
            ResearchCollaborationRule::slugFor($authors),
            [ResearchCollaborationRule::DIU, ResearchCollaborationRule::VISITING_AND_DIU],
            true,
        );
    }

    /**
     * Whether the award cell means DIU paid for this one.
     *
     * The same two tests the pipeline already uses to decide whether to build
     * an incentive at all, so a publication carrying a DIU incentive and a
     * publication under a DIU project are always the same set.
     */
    public static function isDiuAward(?string $awardMoney): bool
    {
        $text = trim((string) $awardMoney);

        if ($text === '') {
            return false;
        }

        $digits = preg_replace('/[^0-9]/', '', $text);

        return ((int) $digits) > 0 || str_contains(strtolower($text), 'paid');
    }

    /**
     * The funding column, read loosely enough to survive how it was typed.
     *
     * The cell is free text that people filled in by hand over several years,
     * so it holds "External", "EXternal", "Esternal" and "Extrernal", and it
     * carries author markers — "External (VF)", or a bare "(VF)" — that are
     * about who wrote the paper and not about who paid. The parentheses come
     * off first; the collaboration is worked out from the author list instead,
     * by ResearchCollaborationRule.
     *
     * Values that name neither a source nor anything recognisable ("NA", "22",
     * "Awarded in Award Giving Ceremony 2019") return null.
     */
    protected static function fromFundingNote(?string $funding): ?string
    {
        $text = strtolower(trim((string) $funding));

        // "(VF)" and friends: an author note living in the funding column.
        $text = trim(preg_replace('/\(.*?\)/', '', $text));

        if ($text === '') {
            return null;
        }

        if (str_contains($text, 'diu')) {
            return self::DIU;
        }

        if (str_contains($text, 'self')) {
            return self::SELF;
        }

        // Spelt four different ways across the export, so matched by shape
        // rather than by equality.
        if (str_starts_with($text, 'ex') || levenshtein($text, 'external') <= 2) {
            return self::EXTERNAL;
        }

        return null;
    }
}
