<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads an administrative post out of the words the old system wrote it in.
 *
 * Old designations were one string holding two facts: "Professor & Director,
 * MBA Program" is a rank and a post. The rank becomes designation_id; this
 * turns the other half into an administrative role, which is where a post
 * belongs — a lookup rather than free text, several at a time, scoped to the
 * department or faculty it is held over, with the dates it was held.
 *
 * Used by two callers that must agree: the migration that moved the existing
 * teachers.extra_designation values across, and the import, which meets the
 * same strings every time it runs. Without the second, a database rebuilt from
 * scratch would keep the ranks and quietly lose every directorship and
 * associate headship — the migration only ever sees rows that already exist.
 */
final class AdministrativePostMap
{
    /**
     * Words to look for, and the role they name.
     *
     * Ordered, and read in order: "Program Director, M.Sc." has to be found by
     * "director" as a coordinator, and "Associate Head" must be matched before
     * anything looks for "head".
     *
     * @var array<string, string>
     */
    private const MAPPING = [
        'associate head' => 'Associate Head',
        'associate dean' => 'Associate Dean',
        'head of department' => 'Head of Department',
        'proctor' => 'Proctor',
        'advisor' => 'Advisor',
        'adviser' => 'Advisor',
        'coordinator' => 'Program Coordinator',
        'director' => 'Program Coordinator',
        'dean' => 'Dean',
    ];

    /**
     * Posts the roles table has never listed but the old data holds.
     *
     * @var array<string, int>  name => sort_order
     */
    public const MISSING_ROLES = [
        'Proctor' => 12,
        'Advisor' => 13,
    ];

    /**
     * Posts held over a faculty rather than over one department.
     *
     * @var array<int, string>
     */
    public const FACULTY_SCOPED = ['Dean', 'Associate Dean'];

    /**
     * The role this text names, or null when it names none.
     *
     * A value that is only a rank returns null: the old string read "Associate
     * Dean & Professor" and the split handed back the rank twice, which is how
     * three teachers came to read "Professor & Professor". Those people already
     * hold the role the other half named.
     *
     * @param  array<int, string>  $rankNames  Designation names, to recognise a rank.
     */
    public static function roleFor(?string $text, array $rankNames = []): ?string
    {
        $value = trim((string) $text);

        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);

        foreach ($rankNames as $rank) {
            if ($lower === mb_strtolower(trim($rank))) {
                return null;
            }
        }

        foreach (self::MAPPING as $needle => $role) {
            if (str_contains($lower, $needle)) {
                return $role;
            }
        }

        return null;
    }

    /** Whether this role is held over a faculty rather than a department. */
    public static function isFacultyScoped(string $role): bool
    {
        return in_array($role, self::FACULTY_SCOPED, true);
    }
}
