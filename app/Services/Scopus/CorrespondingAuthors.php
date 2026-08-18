<?php

namespace App\Services\Scopus;

/**
 * Works out which of a paper's authors Scopus named as the corresponding one.
 *
 * The export writes it in a column of its own, in a shape that is nearly but not
 * quite a name:
 *
 *   M.M. Murshid; Daffodil International University, Department of Computer
 *   Science and Engineering, Dhaka, Bangladesh; email: murshid15-6122@diu.edu.bd
 *
 * and when a paper has more than one, they are chained into the same cell — 317
 * of the 1,572 rows in the July export do that. So the string is split on the
 * email that ends each block rather than on the semicolons inside it.
 *
 * The name is then matched back to a position in "Author full names", which is
 * the list every other part of the import works from. That matters more than it
 * sounds: the corresponding author is the first-listed author only 512 times out
 * of 1,791. The other 71% were being filed as ordinary co-authors, because
 * position was the only thing anybody was reading.
 *
 * Matching goes from the full name's side, not the address's. "M. Al-Mamun" and
 * "A.R.M. Towfiqul Islam" have surnames of one and two words, and no rule about
 * where a Bangladeshi surname begins survives contact with a real export. What
 * does hold is that the address ends with the surname the full name declares
 * before its comma — so each candidate surname is tried as a suffix, and
 * whatever precedes it has to work as initials. That reads 1,791 of the 1,801
 * addresses in the July export; the ten it does not are rows where Scopus left
 * the author list empty.
 */
class CorrespondingAuthors
{
    /**
     * Honorifics Scopus keeps in one column and drops from the other.
     *
     * "Md.R. Repon" against "Repon, Reazuddin": the address abbreviates a
     * courtesy title the full name never carried, so the initials disagree on a
     * letter that stands for nothing.
     */
    protected const HONORIFICS = ['md', 'mohammad', 'mohammed', 'muhammad', 'mst', 'mrs', 'mr', 'dr', 'prof'];

    /**
     * The positions in $names that this correspondence address points at.
     *
     * @param  array<int, string>  $names  "Author full names" entries, in order
     * @return array<int, int>  positions, ascending, without repeats
     */
    public function positionsIn(string $address, array $names): array
    {
        $positions = [];

        foreach ($this->namesIn($address) as $written) {
            $position = $this->positionOf($written, $names);

            if ($position !== null) {
                $positions[$position] = true;
            }
        }

        $positions = array_keys($positions);
        sort($positions);

        return $positions;
    }

    /**
     * The names as the address wrote them, one per corresponding author.
     *
     * @return array<int, string>
     */
    public function namesIn(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [];
        }

        // Each block runs up to the email that closes it. Splitting on the
        // semicolons instead would cut every address into its street parts.
        $blocks = preg_split('/;\s*email:\s*\S+\s*;?\s*/i', $address) ?: [];

        $names = [];

        foreach ($blocks as $block) {
            // The name is what precedes the first separator; everything after it
            // is the institution, which belongs to the affiliation columns.
            $name = trim((string) preg_split('/[;,]/', trim($block))[0]);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Where in the author list a written name belongs, or null if nowhere.
     *
     * @param  array<int, string>  $names
     */
    public function positionOf(string $written, array $names): ?int
    {
        $writtenFlat = str_replace(' ', '', $this->normalise($written));

        if ($writtenFlat === '') {
            return null;
        }

        foreach ($names as $position => $full) {
            [$surname, $given] = $this->splitFullName($full);

            if ($surname === '') {
                continue;
            }

            if (! str_ends_with($writtenFlat, $surname)) {
                continue;
            }

            $prefix = substr($writtenFlat, 0, strlen($writtenFlat) - strlen($surname));

            if ($this->initialsAgree($prefix, $given)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * A full name's surname and given names, as the export writes them.
     *
     * "Murshid, Md Mahmud (60594470100)" is the usual shape. A handful of people
     * are published under a single name — "Amanullah (60637766000)" — and have
     * no comma to split on, so the whole of it is the surname.
     *
     * @return array{0: string, 1: array<int, string>}  flattened surname, given-name words
     */
    protected function splitFullName(string $full): array
    {
        $full = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $full));

        if (! str_contains($full, ',')) {
            return [str_replace(' ', '', $this->normalise($full)), []];
        }

        [$surname, $given] = array_map('trim', explode(',', $full, 2));

        return [
            str_replace(' ', '', $this->normalise($surname)),
            array_values(array_filter(explode(' ', $this->normalise($given)), 'strlen')),
        ];
    }

    /**
     * Whether what precedes the surname can stand for these given names.
     *
     * Nothing at all agrees: a surname on its own is how a single-name author is
     * written. Otherwise the letters have to be a run of the given names'
     * initials from the start, or a subsequence of them — "C.B. Gopinath" is
     * "Gopinath, Subash C.B.", where the address kept the middle initials and
     * dropped the first given name.
     *
     * @param  array<int, string>  $given
     */
    protected function initialsAgree(string $prefix, array $given): bool
    {
        if ($prefix === '') {
            return true;
        }

        $initials = implode('', array_map(fn (string $word) => $word[0], $given));

        if ($initials === '') {
            return false;
        }

        foreach ([$prefix, $this->withoutHonorifics($prefix, $given)] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (str_starts_with($initials, $candidate)
                || str_starts_with($candidate, $initials)
                || $this->isSubsequence($candidate, $initials)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The prefix with a leading courtesy title removed, when the name itself
     * does not claim one.
     *
     * Only ever removed from the front, and only when the given names do not
     * begin with it — "Md Mahmud" really is initialled "mm", and stripping it
     * there would break a match that works.
     *
     * @param  array<int, string>  $given
     */
    protected function withoutHonorifics(string $prefix, array $given): string
    {
        $first = $given[0] ?? '';

        if (in_array($first, self::HONORIFICS, true)) {
            return $prefix;
        }

        // "mdr" -> "r": the honorific is written out in the address and
        // abbreviated to its own initial, so only that one letter comes off.
        foreach (self::HONORIFICS as $honorific) {
            if ($prefix !== $honorific && str_starts_with($prefix, $honorific)) {
                return substr($prefix, strlen($honorific));
            }
        }

        return $prefix;
    }

    /** Every letter of $needle appears in $haystack, in order. */
    protected function isSubsequence(string $needle, string $haystack): bool
    {
        $at = 0;

        for ($i = 0; $i < strlen($needle); $i++) {
            $found = strpos($haystack, $needle[$i], $at);

            if ($found === false) {
                return false;
            }

            $at = $found + 1;
        }

        return true;
    }

    /** Letters and single spaces, lower-cased — the form both sides can be compared in. */
    protected function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z ]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
