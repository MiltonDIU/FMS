<?php

namespace App\Support;

use App\Models\Teacher;

/**
 * Who wrote the paper, expressed as a research collaboration.
 *
 * The source data never records this. PD has no column for it, and the old
 * site had none either, so it has to be read off the author list — which does
 * say it, once every author has been resolved to either one of our own
 * teachers or a row in the authors table.
 *
 * Three answers, from the mix:
 *  - every author is one of ours          → DIU Researcher
 *  - some ours, some not                  → Visiting Faculty + DIU Researcher
 *  - none of ours                         → Collaboration (External)
 *
 * The middle case is the reason this is a class rather than two lines inside
 * the importer. It used to collapse into the first — any paper with a DIU
 * teacher on it was called DIU Researcher regardless of who else was on it,
 * which left 'Visiting Faculty + DIU Researcher' seeded and never used while
 * 1,781 papers that are exactly that were filed as pure DIU research.
 *
 * Both publications:convert-pipeline and publications:import-pipeline decide
 * this, at different stages and from different files, so the rule lives in one
 * place where they cannot disagree about it.
 */
class ResearchCollaborationRule
{
    /** Everyone on the paper is a DIU teacher. */
    public const DIU = 'diu-researcher';

    /** Nobody on the paper is. */
    public const EXTERNAL = 'collaboration-external';

    /** Both, on the same paper. */
    public const VISITING_AND_DIU = 'visiting-faculty-diu';

    /**
     * The collaboration slug for a resolved author list, or null when the list
     * cannot answer the question.
     *
     * Null rather than a guess for a paper with no authors, or none that were
     * resolved to anything: "external collaboration" is a claim about a paper,
     * and it is not one we can make about a paper we know nothing about. Two
     * rows in the current export are like that.
     *
     * @param  array<int, array<string, mixed>>  $authors  Authors carrying `authorable_type`.
     */
    public static function slugFor(array $authors): ?string
    {
        $ours = 0;
        $outside = 0;

        foreach ($authors as $author) {
            $type = $author['authorable_type'] ?? null;

            if ($type === Teacher::class) {
                $ours++;
            } elseif (filled($type)) {
                // Anything resolved but not a teacher is a row in the authors
                // table — visiting faculty, a student, somebody at another
                // institution. The distinction between those is the author
                // type's job, not this one's.
                $outside++;
            }
        }

        if ($ours === 0 && $outside === 0) {
            return null;
        }

        if ($ours === 0) {
            return self::EXTERNAL;
        }

        return $outside === 0 ? self::DIU : self::VISITING_AND_DIU;
    }
}
