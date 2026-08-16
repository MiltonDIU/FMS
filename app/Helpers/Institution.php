<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Who "we" are, for the purpose of reading somebody else's data about us.
 *
 * The Scopus matcher has to answer one question over and over — is this
 * affiliation line ours? — and every part of that answer used to be a constant
 * with Daffodil written into it. That is fine until the same system is stood up
 * at another university, at which point the matcher confidently recognises
 * nobody.
 *
 * Everything here resolves from the `settings` table (prefixed `institution_`)
 * and falls back to the Daffodil values below, so an existing install behaves
 * exactly as it did before anybody touches the new settings tab.
 *
 * Kept separate from [[Branding]] on purpose: branding is what the site shows a
 * visitor, this is what the matcher believes about the world. They share a name
 * and nothing else — the name here defaults to the branding one so a fresh
 * install has a single place to set it.
 */
class Institution
{
    public const PREFIX = 'institution_';

    /**
     * The Daffodil values, which are also the shape of the setting.
     *
     * Written as the defaults rather than as seeded rows so an install that has
     * never opened the settings tab still matches, and so upgrading cannot
     * silently change what a run concludes.
     */
    public const DEFAULTS = [
        self::PREFIX . 'name' => 'Daffodil International University',
        self::PREFIX . 'short_name' => 'Daffodil',

        /*
         * Hand-written patterns, which win over anything derived from the name.
         *
         * The university is written 27 different ways across a single export —
         * "Daffodill", "Daffodils", "Univeristy", "Univ." — and this one has
         * been tuned against all of them. A derived pattern gets most of the
         * way (see patternsFor) but cannot know that somebody typed
         * "Internatinal", so an observed misspelling belongs here.
         */
        self::PREFIX . 'match_patterns' => ['daffodil[a-z]*\s+internationa?l?\s+univ'],

        /*
         * Carries the name, is not this university.
         *
         * Whether these count is the reviewer's call at upload time — see the
         * "include sister institutions" switch — but they have to be nameable
         * before that switch can mean anything.
         */
        self::PREFIX . 'not_us' => ['Daffodil Polytechnic', 'Daffodil Institute of IT'],

        /** Addresses that belong to the institution, for the student rule below. */
        self::PREFIX . 'email_domains' => ['diu.edu.bd', 'daffodilvarsity.edu.bd'],

        /*
         * How a student address is told from a staff one.
         *
         * DIU addresses staff as name.department and students by admission
         * number — kabir.cse@diu.edu.bd against murshid15-6122@diu.edu.bd — so
         * a digit in the local part is the whole rule. Another university may
         * number everybody, or nobody; an empty pattern turns the rule off.
         */
        self::PREFIX . 'student_email_pattern' => '\d',

        /*
         * Scopus wording that resembles nothing in our own tables.
         *
         * A null department means "the faculty is known, the department is for
         * the reviewer" — right for a business school, which could be Economics
         * or BBA. Faculty is matched on short_name, department on code, so the
         * values are this institution's own and have to move with it.
         */
        self::PREFIX . 'unit_aliases' => [
            ['unit' => 'school of business and economics', 'faculty' => 'FBE', 'department' => null],
            ['unit' => 'faculty of economics and business', 'faculty' => 'FBE', 'department' => null],
            ['unit' => 'school of business', 'faculty' => 'FBE', 'department' => null],
            ['unit' => 'faculty of business', 'faculty' => 'FBE', 'department' => null],
            ['unit' => 'faculty of engineering and technology', 'faculty' => 'FE', 'department' => null],
            ['unit' => 'faculty of civil engineering technology', 'faculty' => 'FE', 'department' => null],
            ['unit' => 'faculty of health', 'faculty' => 'FHLS', 'department' => null],
            ['unit' => 'faculty of allied health sciences', 'faculty' => 'FHLS', 'department' => null],
            ['unit' => 'faculty of science', 'faculty' => 'FSIT', 'department' => null],
        ],
    ];

    /** Raw setting value, or the default when nothing has been saved. */
    public static function get(string $key): mixed
    {
        if (! str_starts_with($key, self::PREFIX)) {
            $key = self::PREFIX . $key;
        }

        $value = Setting::get($key, null);

        if ($value === null || $value === '' || $value === []) {
            return self::DEFAULTS[$key] ?? null;
        }

        return $value;
    }

    /**
     * A setting that holds a list.
     *
     * Setting::set json-encodes an array but does not record that it did, so
     * what comes back is a JSON string. Decoding here keeps every caller from
     * having to know that — the same thing Branding::socialLinks does.
     *
     * @return array<int, mixed>
     */
    public static function list(string $key): array
    {
        $value = self::get($key);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter([$value]);
        }

        return is_array($value) ? array_values($value) : [];
    }

    public static function name(): string
    {
        $name = (string) self::get('name');

        // A fresh install sets the university's name once, under branding; this
        // follows it rather than asking for the same name twice.
        return $name !== '' ? $name : (string) Branding::get('site_name');
    }

    /** What to call our own authors in a heading: "287 Daffodil authors". */
    public static function shortName(): string
    {
        $short = (string) self::get('short_name');

        return $short !== '' ? $short : self::name();
    }

    /**
     * The patterns an affiliation line is tested against, as full regexes.
     *
     * @return array<int, string>
     */
    public static function matchPatterns(): array
    {
        $patterns = array_values(array_filter(array_map(
            fn ($pattern) => trim((string) $pattern),
            self::list('match_patterns'),
        ), 'strlen'));

        if ($patterns === []) {
            $patterns = self::patternsFor(self::name());
        }

        return array_map(fn (string $pattern) => '/' . $pattern . '/i', $patterns);
    }

    /**
     * A tolerant pattern built from the institution's name.
     *
     * Only a starting point, and deliberately a loose one: each word is cut to
     * its first four letters and allowed to run on, so "Daffodil International
     * University" also answers to "Daffodill Internatinal Univeristy" — the
     * kind of thing an export is full of. What it cannot do is recognise a
     * word nobody spelt the beginning of right, or a name written in another
     * order ("Dhaka University" for "University of Dhaka"). Those go in
     * match_patterns by hand.
     *
     * @return array<int, string>
     */
    public static function patternsFor(string $name): array
    {
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);

        if (! $words) {
            return [];
        }

        $stems = array_map(function (string $word): string {
            $stem = mb_substr($word, 0, 4);

            // A word already at or under the stem length has to match whole,
            // or "of" would become a wildcard.
            return mb_strlen($word) <= 4
                ? preg_quote($word, '/')
                : preg_quote($stem, '/') . '[a-z]*';
        }, $words);

        return [implode('\s+', $stems)];
    }

    /** @return array<int, string> */
    public static function notUs(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            self::list('not_us'),
        ), 'strlen'));
    }

    /** @return array<int, string> */
    public static function emailDomains(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => mb_strtolower(trim((string) $value)),
            self::list('email_domains'),
        ), 'strlen'));
    }

    /**
     * The student-address rule as a full regex, or null when there is none.
     *
     * Tested against the local part of the address only, which is where the
     * admission number lives. Whether a given run consults it at all is the
     * "flag students by email" switch on the upload form, not this.
     */
    public static function studentEmailPattern(): ?string
    {
        $pattern = trim((string) self::get('student_email_pattern'));

        if ($pattern === '') {
            return null;
        }

        $full = '/' . $pattern . '/';

        // An admin can type nonsense into a regex field. Declining to apply a
        // pattern that will not compile beats every row of the run dying on it.
        return @preg_match($full, '') === false ? null : $full;
    }

    /**
     * Scopus unit wording => [faculty short name, department code or null].
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function unitAliases(): array
    {
        $aliases = [];

        foreach (self::list('unit_aliases') as $alias) {
            if (! is_array($alias)) {
                continue;
            }

            $unit = mb_strtolower(trim((string) ($alias['unit'] ?? '')));
            $faculty = trim((string) ($alias['faculty'] ?? ''));

            if ($unit === '' || $faculty === '') {
                continue;
            }

            $department = trim((string) ($alias['department'] ?? ''));

            $aliases[$unit] = [$faculty, $department !== '' ? $department : null];
        }

        return $aliases;
    }

    /**
     * Every resolved value, keyed without the prefix, for the settings form.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $out = [];

        foreach (array_keys(self::DEFAULTS) as $fullKey) {
            $bare = substr($fullKey, strlen(self::PREFIX));

            $out[$bare] = in_array($bare, ['match_patterns', 'not_us', 'email_domains', 'unit_aliases'], true)
                ? self::list($fullKey)
                : self::get($fullKey);
        }

        return $out;
    }
}
