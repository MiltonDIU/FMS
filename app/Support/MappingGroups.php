<?php

namespace App\Support;

use App\Support\LookupResolver;

/**
 * Converts a mapping's flat rule list to and from the grouped shape the editing
 * screen uses.
 *
 * Stored, a mapping is one flat array of rules whose source_field carries the
 * full path — "employee_id", "employeeEducationalInformations.instituteName".
 * That is what IntegrationService::transform() reads, and it does not change.
 *
 * Edited, that same list is far easier to work with grouped by section: a
 * profile response has a dozen collections beside the core fields, and a single
 * flat list of every column of every one of them runs to hundreds of rows. So
 * the screen splits it by the first path segment, shows one accordion per
 * section, and inside a section shows only the leaf column name.
 */
class MappingGroups
{
    /**
     * The pseudo-section holding rules whose source has no dot — the scalar
     * columns that sit at the top of a record.
     */
    public const CORE = '';

    /**
     * Flat rules → sections.
     *
     * @param array<int,array<string,mixed>> $rules
     * @return array<int,array{section:string,rules:array<int,array<string,mixed>>}>
     */
    public static function group(array $rules): array
    {
        $sections = [];

        foreach ($rules as $rule) {
            $source = (string) ($rule['source_field'] ?? '');

            if ($source === '') {
                continue;
            }

            [$section, $leaf] = self::split($source);

            $sections[$section][] = [
                'field' => $leaf,
                'target_model' => $rule['target_model'] ?? null,
                'target_field' => $rule['target_field'] ?? null,
                'is_identifier' => (bool) ($rule['is_identifier'] ?? false),
                // Rules saved before the flag existed get it worked out from the
                // column, so opening an old mapping shows the relation boxes
                // already ticked where they belong.
                'is_relation' => array_key_exists('is_relation', $rule)
                    ? (bool) $rule['is_relation']
                    : LookupResolver::handles((string) ($rule['target_field'] ?? '')),
                'match_column' => $rule['match_column'] ?? null,
            ];
        }

        // Core first, then the collections in the order they were met, so the
        // screen opens on the fields somebody is most likely to want.
        uksort($sections, fn ($a, $b) => ($a === self::CORE ? -1 : ($b === self::CORE ? 1 : 0)));

        return array_map(
            fn ($section, $rules) => ['section' => $section, 'rules' => $rules],
            array_keys($sections),
            $sections,
        );
    }

    /**
     * Sections → flat rules.
     *
     * @param array<int,array<string,mixed>> $groups
     * @return array<int,array<string,mixed>>
     */
    public static function flatten(array $groups): array
    {
        $rules = [];

        foreach ($groups as $group) {
            $section = trim((string) ($group['section'] ?? ''));

            foreach ((array) ($group['rules'] ?? []) as $rule) {
                $leaf = trim((string) ($rule['field'] ?? ''));

                // A half-filled row is a row somebody is still working on, not
                // something to persist as a broken rule.
                if ($leaf === '' || blank($rule['target_model'] ?? null) || blank($rule['target_field'] ?? null)) {
                    continue;
                }

                $rules[] = [
                    'source_field' => $section === '' ? $leaf : "{$section}.{$leaf}",
                    'target_model' => $rule['target_model'],
                    'target_field' => $rule['target_field'],
                    'is_identifier' => (bool) ($rule['is_identifier'] ?? false),
                    'is_relation' => (bool) ($rule['is_relation'] ?? false),
                    'match_column' => filled($rule['match_column'] ?? null) ? $rule['match_column'] : null,
                ];
            }
        }

        return $rules;
    }

    /**
     * Build sections straight from detected field paths, with nothing mapped
     * yet — what the "Fetch Data" and "Parse JSON" buttons produce.
     *
     * @param array<int,string> $paths
     * @return array<int,array{section:string,rules:array<int,array<string,mixed>>}>
     */
    public static function fromDetectedPaths(array $paths): array
    {
        return self::group(array_map(
            fn (string $path) => ['source_field' => $path],
            $paths,
        ));
    }

    /**
     * Sections present in a record, including the ones that came back empty.
     *
     * A teacher with no awards returns `"employeeAwards": []`, which names no
     * fields at all. The section still has to appear, or there is nowhere to
     * add its columns by hand — and on this API most sections are empty for
     * most people.
     *
     * @param array<string,mixed> $record a single normalised record
     * @return array<int,string>
     */
    public static function sectionsIn(array $record): array
    {
        return array_values(array_keys(array_filter($record, 'is_array')));
    }

    /**
     * Merge newly detected paths into what is already on screen, keeping every
     * row an administrator has already filled in.
     *
     * @param array<int,array<string,mixed>> $existing
     * @param array<int,string> $paths
     * @param array<int,string> $sections sections to create even when empty
     * @return array<int,array<string,mixed>>
     */
    public static function mergeDetected(array $existing, array $paths, array $sections = []): array
    {
        $bySection = [];

        foreach ($existing as $group) {
            $bySection[(string) ($group['section'] ?? '')] = (array) ($group['rules'] ?? []);
        }

        foreach ($sections as $section) {
            $bySection[$section] ??= [];
        }

        foreach ($paths as $path) {
            [$section, $leaf] = self::split($path);

            $bySection[$section] ??= [];

            $known = array_column($bySection[$section], 'field');

            if (in_array($leaf, $known, true)) {
                continue;
            }

            $bySection[$section][] = [
                'field' => $leaf,
                'target_model' => null,
                'target_field' => null,
                'is_identifier' => false,
                'is_relation' => false,
                'match_column' => null,
            ];
        }

        uksort($bySection, fn ($a, $b) => ($a === self::CORE ? -1 : ($b === self::CORE ? 1 : 0)));

        return array_map(
            fn ($section, $rules) => ['section' => $section, 'rules' => $rules],
            array_keys($bySection),
            $bySection,
        );
    }

    /**
     * "employeeEducationalInformations.degree.name" → section and leaf.
     *
     * Only the first segment names the section: the rest stays part of the leaf
     * so a nested object inside a collection item — degree.name, country.code —
     * survives the round trip intact.
     *
     * @return array{0:string,1:string}
     */
    protected static function split(string $source): array
    {
        $dot = strpos($source, '.');

        return $dot === false
            ? [self::CORE, $source]
            : [substr($source, 0, $dot), substr($source, $dot + 1)];
    }
}
