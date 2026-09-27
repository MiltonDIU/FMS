<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/**
 * Link fields that accept an address typed without http:// or https://.
 *
 * The imported records are full of links like "www.iosrjournals.org" — 246 of
 * the 8,952 journal links and 21 of the 36 membership links. Laravel's url
 * rule rejects them, so any save of a profile holding one failed with "The
 * journal link field must be a valid URL", whatever was actually being edited.
 *
 * Rather than loosening the rule, the address is completed: "https://" is put
 * in front when a link has no scheme. That also mends the link on the public
 * site, where a scheme-less href is taken as a path on this site and goes
 * nowhere. It happens when the form loads, when a field is left, and again on
 * save, so both old data and new typing are covered.
 *
 * A few other slips found in the imported links are mended too (see
 * normalize()). Anything that is still not a URL after that — a local file
 * path, a journal name typed into the link field — is left alone for the rule
 * to reject, since guessing at those would publish a wrong link.
 */
class LenientUrl
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Invisible formatting characters pasted along with a link (a trailing
        // left-to-right mark was found on one) make an otherwise good URL fail.
        $value = trim((string) preg_replace('/\p{Cf}+/u', '', $value));

        // Placeholders typed into the old system where there was no link.
        if ($value === '' || preg_match('#^[\'"\s\-_.]*$|^n/?a$#i', $value)) {
            return '';
        }

        // Mistakes common enough in the imported data to mend safely:
        // "ttp://" with the h lost, "www. site.org" with a stray space,
        // and a bare "doi:10.…" which has one canonical address.
        $value = (string) preg_replace('#^ttps?://#i', 'h$0', $value);
        $value = (string) preg_replace('#^((?:https?://)?www\.)\s+#i', '$1', $value);

        if (preg_match('#^doi:\s*(10\.\S+)$#i', $value, $doi)) {
            return 'https://doi.org/' . $doi[1];
        }

        // Already has a scheme (https:, http:, mailto:, ftp:, …).
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        // Looks like a host: no spaces, and a dot before any path.
        if (! preg_match('/\s/', $value) && preg_match('#^[^/?\#]+\.[^/?\#]+#', $value)) {
            return 'https://' . $value;
        }

        return $value;
    }

    public static function field(TextInput $input): TextInput
    {
        return $input
            ->url()
            ->formatStateUsing(fn ($state) => static::normalize($state))
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, $state): void {
                $normalized = static::normalize($state);

                if ($normalized !== $state) {
                    $component->state($normalized);
                }
            })
            ->dehydrateStateUsing(fn ($state) => static::normalize($state));
    }
}
