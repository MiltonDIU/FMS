<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * How an incoming record is matched against one already on file.
 *
 * Most of a teacher's detail started life in the old ERP, is richer than what
 * the HR API returns, and gets corrected and added to by hand. So an import
 * matches row by row and never deletes: a match is refreshed, anything else is
 * added, and what the API does not mention stays.
 *
 * Shared by the bulk seeder and the merge that fills the edit form, because the
 * two disagreeing about what counts as "the same education" is how duplicates
 * appear.
 */
class RelationMerge
{
    /**
     * What identifies a record of each kind in real life.
     *
     * Keyed by the relation name on Teacher.
     */
    public const MATCH_ON = [
        'educations' => ['educational_institution_id', 'degree_type_id', 'passing_year'],
        'trainingExperiences' => ['title', 'organization'],
        'certifications' => ['title', 'issuing_authority'],
        'skills' => ['name'],
        'teachingAreas' => ['area'],
        'memberships' => ['membership_organization_id', 'membership_id'],
        'awards' => ['title', 'year'],
        'jobExperiences' => ['organization', 'position', 'start_date'],
        'socialLinks' => ['social_media_platform_id'],
    ];

    /**
     * @return array<int,string>
     */
    public static function keysFor(string $relation): array
    {
        return self::MATCH_ON[$relation] ?? [];
    }

    /**
     * Whether a candidate describes the same thing as an incoming row.
     *
     * Only the key columns the incoming row actually carries are compared. A
     * key it cannot fill identifies nothing, so the row counts as new rather
     * than overwriting an unrelated record — erring towards keeping data.
     *
     * @param array<int,string> $keys
     * @param array<string,mixed>|Model $candidate
     * @param array<string,mixed> $row
     */
    public static function matches(array $keys, array|Model $candidate, array $row): bool
    {
        $usable = array_values(array_filter($keys, fn ($column) => array_key_exists($column, $row)));

        if ($usable === []) {
            return false;
        }

        foreach ($usable as $column) {
            $stored = $candidate instanceof Model
                ? $candidate->getAttribute($column)
                : ($candidate[$column] ?? null);

            if (! self::sameValue($stored, $row[$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether two column values describe the same thing.
     *
     * Dates arrive as Carbon from the model and as strings from the payload,
     * and a year may be an int on one side and a string on the other. A strict
     * comparison would never match, and every import would add duplicates.
     */
    public static function sameValue(mixed $stored, mixed $incoming): bool
    {
        if ($stored instanceof \DateTimeInterface) {
            $stored = $stored->format('Y-m-d');
            $incoming = is_string($incoming) ? substr($incoming, 0, 10) : $incoming;
        }

        if (is_numeric($stored) && is_numeric($incoming)) {
            return (string) $stored === (string) $incoming;
        }

        if (is_string($stored) && is_string($incoming)) {
            return mb_strtolower(trim($stored)) === mb_strtolower(trim($incoming));
        }

        return $stored == $incoming;
    }

    /**
     * Merge incoming rows into rows already on screen, for the edit form.
     *
     * Matched rows are overlaid with the incoming values, keeping any column
     * the payload has nothing to say about — an id, a hand-written note. Rows
     * only the API knows about are appended. Rows only the form knows about are
     * left untouched, which is what keeps hand-added detail alive.
     *
     * @param array<int,array<string,mixed>> $existing
     * @param array<int,array<string,mixed>> $incoming
     * @param array<int,string> $keys
     * @return array{rows:array<int,array<string,mixed>>,updated:int,added:int}
     */
    public static function mergeRows(array $existing, array $incoming, array $keys): array
    {
        $rows = array_values($existing);
        $updated = $added = 0;

        foreach ($incoming as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $matchedAt = null;

            foreach ($rows as $index => $candidate) {
                if (is_array($candidate) && self::matches($keys, $candidate, $row)) {
                    $matchedAt = $index;
                    break;
                }
            }

            if ($matchedAt === null) {
                $rows[] = $row;
                $added++;

                continue;
            }

            $rows[$matchedAt] = array_merge($rows[$matchedAt], $row);
            $updated++;
        }

        return ['rows' => $rows, 'updated' => $updated, 'added' => $added];
    }
}
