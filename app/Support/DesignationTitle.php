<?php

namespace App\Support;

/**
 * Splits a compound job title into the academic rank and whatever else it carries.
 *
 * The old faculty database keeps one string per person, and that string often
 * holds two different facts at once:
 *
 *   "Professor & Advisor"
 *   "Associate Professor & Director, M.Sc in CSE"
 *   "Professor & Director MBA Program"
 *
 * Only the first half is a rank the designations table can hold. The second is a
 * standing title belonging to the person rather than to the grade, and it has no
 * fixed list behind it — every programme has its own director. Giving each one an
 * administrative_roles row would also file the holder under Administration on the
 * department page, above everybody else, which is not where these people belong.
 *
 * So the rank goes to teachers.designation_id and the remainder to
 * teachers.extra_designation, and compose() puts the two back together wherever a
 * title is displayed. Before this existed the second half was simply dropped:
 * matchDesignation() read the rank out of the string and nothing kept the rest.
 */
class DesignationTitle
{
    /** The separator the old data uses, and the one display re-joins with. */
    public const SEPARATOR = ' & ';

    /**
     * Administrative role names under the spellings the old database uses.
     *
     * "Professor & Head" must not also produce the extra title "Head": the head
     * flag on the same row already creates a Head of Department assignment, and
     * the card would then say the same thing twice — once in the title and once
     * in the badge beside it.
     */
    private const ROLE_ALIASES = [
        'hod'                    => 'head of department',
        'head'                   => 'head of department',
        'head of dept'           => 'head of department',
        'head of the department'  => 'head of department',
        'dean'                   => 'dean',
        'associate dean'         => 'associate dean',
        'associate head'         => 'associate head',
        'coordinator'            => 'program coordinator',
        'programme coordinator'  => 'program coordinator',
        'program coordinator'    => 'program coordinator',
    ];

    /**
     * Pull the rank half and the extra half apart.
     *
     * @param callable(string):bool|null $isRank Answers whether a half names a
     *        real designation. Optional: the caller owns the lookup table, and
     *        without it the left half is assumed to be the rank.
     *
     * @return array{0:string, 1:string|null} [rank text, extra title or null]
     */
    public static function split(?string $raw, ?callable $isRank = null): array
    {
        $raw = trim((string) preg_replace('/\s+/', ' ', (string) $raw));

        if ($raw === '' || ! str_contains($raw, '&')) {
            return [$raw, null];
        }

        [$left, $right] = array_map('trim', explode('&', $raw, 2));

        // "& Director" or "Professor &" — a stray separator, not two halves.
        // The side that has something on it is the whole title.
        if ($left === '' || $right === '') {
            return [$left === '' ? $right : $left, null];
        }

        /*
         * The rank comes first in almost every row. "Dean & Professor" does
         * exist though, so when the caller can tell us which half is a rank and
         * only the right one is, the halves are swapped — otherwise "Professor"
         * would be exported as somebody's extra title.
         */
        if ($isRank !== null && ! $isRank($left) && $isRank($right)) {
            return [$right, $left];
        }

        return [$left, $right];
    }

    /**
     * Whether an extra title only repeats an administrative role the teacher is
     * already being given.
     *
     * @param array<int,string> $roleNames role names assigned to this teacher
     */
    public static function repeatsAdministrativeRole(?string $extra, array $roleNames): bool
    {
        $needle = strtolower(trim((string) $extra));

        if ($needle === '') {
            return false;
        }

        $needle = self::ROLE_ALIASES[$needle] ?? $needle;

        $held = array_map(
            fn ($name) => strtolower(trim((string) $name)),
            $roleNames,
        );

        return in_array($needle, $held, true);
    }

    /**
     * The job type that says nothing worth printing: everybody else's default.
     */
    private const REGULAR_JOB_TYPE = 'regular';

    /**
     * The job type when it is worth showing beside a designation, else null.
     *
     * A grade and an engagement are two different facts, and the designation is
     * the one that belongs in the title — it is what the person is. The terms
     * ride alongside as a label, the same way an administrative role does, so a
     * reader can tell an adjunct from permanent staff without the title having
     * to be rewritten into something like "Adjunct Professor".
     *
     * Regular is left out because it is everybody's default and says nothing.
     * The system placeholder is left out because it means the terms were never
     * recorded, which is not a fact to publish.
     */
    public static function engagementLabel(?string $jobTypeName): ?string
    {
        $name = trim((string) $jobTypeName);

        if ($name === ''
            || strtolower($name) === self::REGULAR_JOB_TYPE
            || self::isSystemPlaceholder($name)) {
            return null;
        }

        return $name;
    }

    /**
     * Whether a name is one of the system's own placeholder rows.
     *
     * Both tables keep one: "System - Unassigned Designation" and "System -
     * Unassigned". They exist because designation_id and job_type_id have to
     * hold something for a teacher whose old record named neither, and they are
     * never a title to put in front of a visitor.
     */
    public static function isSystemPlaceholder(?string $name): bool
    {
        return str_starts_with(strtolower(trim((string) $name)), 'system - ');
    }

    /**
     * "Professor" + "Director, MBA Program" → "Professor & Director, MBA Program"
     */
    public static function compose(?string $rank, ?string $extra): ?string
    {
        $rank = trim((string) $rank);
        $extra = trim((string) $extra);

        if ($rank === '') {
            return $extra === '' ? null : $extra;
        }

        if ($extra === '') {
            return $rank;
        }

        return $rank . self::SEPARATOR . $extra;
    }
}
