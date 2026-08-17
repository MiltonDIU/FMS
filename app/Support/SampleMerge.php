<?php

namespace App\Support;

/**
 * Accumulates API responses into one sample that covers every section.
 *
 * No single employee exercises the whole payload: most have no awards, no
 * certifications, an empty skills list. Fetching a second employee who does
 * have awards must therefore *add* that section to the sample rather than
 * replace what is already there, so that over a few fetches the sample ends up
 * carrying a real example of every collection — which is the only way to see
 * the columns inside them and map them.
 *
 * The rule throughout is "fill gaps, never overwrite with something emptier".
 */
class SampleMerge
{
    /**
     * Merge a fresh response into the stored sample.
     *
     * @param string|null $existingJson the sample as it stands, possibly hand-edited
     * @param array<string,mixed> $incoming the decoded response just fetched
     * @return string pretty-printed JSON for the sample box
     */
    public static function into(?string $existingJson, array $incoming): string
    {
        $existing = json_decode((string) $existingJson, true);

        // A box holding nothing, or something an edit left un-parseable, is
        // replaced rather than treated as a merge base — there is nothing
        // dependable in it to preserve.
        $merged = is_array($existing)
            ? self::mergeValue($existing, $incoming)
            : $incoming;

        return (string) json_encode(
            $merged,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param mixed $existing
     * @param mixed $incoming
     * @return mixed
     */
    protected static function mergeValue(mixed $existing, mixed $incoming): mixed
    {
        if (is_array($existing) && is_array($incoming)) {
            return array_is_list($existing) || array_is_list($incoming)
                ? self::mergeList($existing, $incoming)
                : self::mergeObject($existing, $incoming);
        }

        // Different shapes, or scalars: keep what we have unless it says
        // nothing. A null office_room gives way to a real one; a real one is
        // never replaced by the next employee's null.
        return self::isEmpty($existing) ? $incoming : $existing;
    }

    /**
     * Union of keys, recursing where both sides have one.
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    protected static function mergeObject(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            $existing[$key] = array_key_exists($key, $existing)
                ? self::mergeValue($existing[$key], $value)
                : $value;
        }

        return $existing;
    }

    /**
     * Collections are kept to a single representative element.
     *
     * One element is all a mapping needs — it is read for its column names, not
     * its contents — and it stops the sample growing by a full publication list
     * every time somebody fetches another employee. The two elements are merged
     * so a column present on only one of them still shows up.
     *
     * @param array<int,mixed> $existing
     * @param array<int,mixed> $incoming
     * @return array<int,mixed>
     */
    protected static function mergeList(array $existing, array $incoming): array
    {
        if ($existing === []) {
            return $incoming === [] ? [] : [reset($incoming)];
        }

        if ($incoming === []) {
            return $existing;
        }

        $first = reset($existing);
        $next = reset($incoming);

        return [self::mergeValue($first, $next)];
    }

    /**
     * Whether a value carries no information worth keeping.
     */
    protected static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
