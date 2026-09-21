<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Breaks a legacy name into the prefix, the name, and the credentials after it.
 *
 * The old system kept the whole thing in one column — "Professor Dr. Md.
 * Shahedur Rashid", "Muhammad Mahboob Ali, PhD" — and the export split it with
 * one line:
 *
 *     preg_replace('/^(Dr\.?|Prof\.?|Mr\.?|Mrs\.?|Ms\.?|Md\.?)\s+/i', '', $name)
 *
 * That line is where every name problem in this database came from, and it got
 * four separate things wrong:
 *
 *  - `Md\.?` was in the list, so 108 people lost the first word of their name.
 *    "Md. Shah Jahan" was stored as "Shah Jahan". Md is a name particle here,
 *    not a title, and it is not ours to remove.
 *
 *  - it replaced once and never looped, so "Prof. Dr. M. Mizanur Rahman" kept
 *    the second title and was stored as "Dr. M. Mizanur Rahman".
 *
 *  - it matched `Prof\.?` but never "Professor", because the pattern needs
 *    whitespace straight after and "Professor" continues. 117 people therefore
 *    have "Professor" sitting in first_name to this day.
 *
 *  - it knew nothing about what comes after a name, so 16 people's surname is
 *    literally "PhD", and their middle name ends in a comma.
 *
 * Titles are recognised here but never discarded: they are handed back so they
 * can be stored in their own field. Anything this cannot confidently identify
 * stays part of the name, which is the safe direction to be wrong in — a title
 * left in a name is visible and fixable, a name silently shortened is not.
 */
final class LegacyNameParser
{
    /**
     * Prefix atoms, longest spelling first so "professor" wins over "prof".
     *
     * "Md", "Mohammad" and "Mohammed" are deliberately absent. They are how a
     * great many people here write their given name, not honorifics, and
     * removing them is the single most damaging thing the old parser did.
     *
     * @var array<string, string>  pattern => canonical spelling
     */
    private const PREFIXES = [
        'professor' => 'Professor',
        'prof' => 'Professor',
        'dr' => 'Dr.',
        'engr' => 'Engr.',
        'major' => 'Major',
        'mrs' => 'Mrs.',
        'mst' => 'Mst.',
        'mr' => 'Mr.',
        'ms' => 'Ms.',
    ];

    /**
     * The order a stacked prefix is written in, whatever order it arrived in.
     *
     * "Professor Dr. Engr." and "Dr. Engr. Professor" are the same thing said
     * twice; storing both spellings is how the old data ended up with 25
     * variants of seven titles.
     *
     * @var array<int, string>
     */
    private const PREFIX_ORDER = ['Professor', 'Dr.', 'Engr.', 'Major', 'Mr.', 'Mrs.', 'Ms.', 'Mst.'];

    /**
     * Credentials written after a name, and how each is spelled once stored.
     *
     * Same idea as the prefixes: the data holds "PhD", "Ph.D" and "PhD." for one
     * qualification, and a directory that prints all three looks careless.
     *
     * @var array<string, string>  pattern => canonical spelling
     */
    private const SUFFIXES = [
        'ph\.?\s?d\.?' => 'PhD',
        'm\.?phil\.?' => 'MPhil',
        'mbbs' => 'MBBS',
        'fcps' => 'FCPS',
        'frcs' => 'FRCS',
        'facs' => 'FACS',
        'bds' => 'BDS',
        'dvm' => 'DVM',
        'mba' => 'MBA',
        'm\.?sc\.?' => 'MSc',
        'llb' => 'LLB',
        'llm' => 'LLM',
        'pgd' => 'PGD',
        'fca' => 'FCA',
        'acca' => 'ACCA',
        'cma' => 'CMA',
        'pmp' => 'PMP',
    ];

    /**
     * @return array{prefix: string|null, name: string, suffixes: array<int, string>}
     */
    public static function parse(?string $raw): array
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $raw) ?? '');

        if ($value === '') {
            return ['prefix' => null, 'name' => '', 'suffixes' => []];
        }

        [$value, $suffixes] = self::takeSuffixes($value);
        [$value, $prefixes] = self::takePrefixes($value);

        // Commas and stray punctuation left behind once the credentials have
        // been lifted off: "Mahboob Ali," becomes "Mahboob Ali".
        $name = trim($value, " \t,;.");
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        /*
         * A name that is nothing but titles is not a name. "Dr." alone means
         * the row held a title and no person, so the title goes back into the
         * name rather than leaving the field empty.
         */
        if ($name === '') {
            return ['prefix' => null, 'name' => implode(' ', $prefixes), 'suffixes' => $suffixes];
        }

        return [
            'prefix' => $prefixes === [] ? null : implode(' ', $prefixes),
            'name' => $name,
            'suffixes' => $suffixes,
        ];
    }

    /**
     * Lifts every leading title off, in a loop, so stacked ones all come away.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function takePrefixes(string $value): array
    {
        $found = [];

        while (true) {
            $matched = false;

            foreach (self::PREFIXES as $pattern => $canonical) {
                // The title, an optional full stop, then whitespace. The word
                // boundary is what stops "Prof" from eating "Professor" and,
                // more importantly, "Ms" from eating "Mst".
                if (preg_match('/^' . $pattern . '\b\.?\s+/i', $value)) {
                    $value = (string) preg_replace('/^' . $pattern . '\b\.?\s+/i', '', $value);

                    if (! in_array($canonical, $found, true)) {
                        $found[] = $canonical;
                    }

                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                break;
            }
        }

        usort($found, fn (string $a, string $b): int => array_search($a, self::PREFIX_ORDER, true)
            <=> array_search($b, self::PREFIX_ORDER, true));

        return [$value, $found];
    }

    /**
     * Lifts credentials off the end, in a loop, so "PhD, MBA" both come away.
     *
     * Only ever from the end, and only whole words. A qualification in the
     * middle of a name is somebody's actual name and is left alone.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function takeSuffixes(string $value): array
    {
        $found = [];

        while (true) {
            $matched = false;

            foreach (self::SUFFIXES as $pattern => $canonical) {
                // Optionally comma-separated, optionally bracketed, at the end.
                $regex = '/[\s,(]+' . $pattern . '\s*\)?$/i';

                if (preg_match($regex, $value)) {
                    $value = (string) preg_replace($regex, '', $value);

                    if (! in_array($canonical, $found, true)) {
                        $found[] = $canonical;
                    }

                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                break;
            }
        }

        // Taken off back to front, so reverse to read as written.
        return [$value, array_reverse($found)];
    }

    /**
     * Every prefix spelling this recognises, for seeding the lookup table.
     *
     * @return array<int, string>
     */
    public static function knownPrefixes(): array
    {
        return array_values(array_unique(array_values(self::PREFIXES)));
    }

    /**
     * @return array<int, string>
     */
    public static function knownSuffixes(): array
    {
        return array_values(array_unique(array_values(self::SUFFIXES)));
    }
}
