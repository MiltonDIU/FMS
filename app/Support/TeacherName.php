<?php

namespace App\Support;

/**
 * How a teacher's display name is built from its parts.
 *
 * Lifted out of FillTeacherFullNameCommand so that the one-off backfill and the
 * observer that keeps full_name current cannot drift apart on what counts as a
 * title or how spacing is normalised.
 */
class TeacherName
{
    /**
     * Academic and courtesy titles stripped from the front of a name.
     *
     * "Md." is deliberately absent: it is part of the name here, not a title.
     */
    protected const PREFIXES = [
        'professors\s+dr',
        'professor',
        'prof',
        'engr',
        'mst',
        'mr',
        'ms',
        'dr',
    ];

    /**
     * Remove leading titles and normalise spacing.
     *
     * Loops because names arrive with several stacked — "Prof. Dr. …".
     */
    public static function clean(string $name): string
    {
        $cleaned = $name;
        $matched = true;

        while ($matched) {
            $matched = false;

            foreach (self::PREFIXES as $prefix) {
                // The title, an optional full stop, then whitespace.
                $pattern = '/^' . $prefix . '\b\.?\s+/i';

                if (preg_match($pattern, $cleaned)) {
                    $cleaned = preg_replace($pattern, '', $cleaned);
                    $matched = true;
                    break;
                }
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Break a name that arrived in one field into first and last.
     *
     * The last word is the surname and everything before it is the given name.
     * Nothing goes to middle_name: it is optional on the teacher record, and
     * treating the second word as a middle name is what turned
     * "Md. Fokhray Hossain" into a first name of "Md." — the honorific alone.
     *
     * Titles are left in place here. They belong to how the person writes their
     * name; full_name is where they get stripped, and that is a separate field.
     *
     * @return array{first_name:string,middle_name:null,last_name:string|null}
     */
    public static function split(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return ['first_name' => '', 'middle_name' => null, 'last_name' => null];
        }

        // A single word cannot be divided, so it stays the given name and the
        // surname is left for someone to fill in.
        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => null];
        }

        $last = array_pop($parts);

        return [
            'first_name' => implode(' ', $parts),
            'middle_name' => null,
            'last_name' => $last,
        ];
    }

    /**
     * The display name for a teacher, from their name parts.
     */
    public static function fromParts(?string $first, ?string $middle, ?string $last): string
    {
        return self::clean(implode(' ', array_filter([
            trim((string) $first),
            trim((string) $middle),
            trim((string) $last),
        ], 'strlen')));
    }
}
