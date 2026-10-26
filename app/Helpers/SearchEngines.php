<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Keeps the whole site out of search results, from System Settings →
 * Frontend Settings — for while it runs on a demo or staging address.
 *
 * Done with an X-Robots-Tag: noindex, nofollow header on every answer, added by
 * App\Http\Middleware\BlockSearchEngines, and not with "Disallow: /" in
 * robots.txt. A crawler barred by robots.txt never fetches the page, so it
 * never sees a noindex either, and Google still lists a blocked address it
 * finds linked elsewhere — URL only, no snippet. The header lets it look and
 * be told no; pages already indexed drop out the next time they are crawled.
 * A header rather than a meta tag also covers PDFs, images and every theme.
 */
class SearchEngines
{
    public const BLOCKED_KEY = 'search_engines_blocked';

    public const HEADER = 'noindex, nofollow';

    public static function blocked(): bool
    {
        return filter_var(Setting::get(self::BLOCKED_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }
}
