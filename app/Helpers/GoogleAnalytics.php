<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google Analytics (GA4) on the public site, from System Settings →
 * Frontend Settings.
 *
 * Added to the page by App\Http\Middleware\AddGoogleAnalytics rather than
 * written into a theme. Each theme owns its own head partial, so a tag pasted
 * into one is gone the moment another theme is switched on; inserted into the
 * response, it reaches every theme, including ones not written yet.
 *
 * Public pages only. The admin panel, the activation pages and the maintenance
 * page are left alone, and so — while the setting asks — is anyone signed in,
 * so the team and the teachers looking at their own profiles do not count as
 * visitors.
 */
class GoogleAnalytics
{
    public const ENABLED_KEY = 'google_analytics_enabled';

    public const ID_KEY = 'google_analytics_id';

    public const SKIP_SIGNED_IN_KEY = 'google_analytics_skip_signed_in';

    /** A GA4 measurement ID. Anything else is not put on the page. */
    public const ID_PATTERN = '/^G-[A-Z0-9]{4,16}$/';

    public static function measurementId(): ?string
    {
        $id = strtoupper(trim((string) Setting::get(self::ID_KEY, '')));

        return preg_match(self::ID_PATTERN, $id) ? $id : null;
    }

    public static function enabled(): bool
    {
        return filter_var(Setting::get(self::ENABLED_KEY, false), FILTER_VALIDATE_BOOLEAN)
            && self::measurementId() !== null;
    }

    /** Whether this answer gets the tag. */
    public static function appliesTo(Request $request, Response $response): bool
    {
        if (! self::enabled() || ! $request->isMethod('GET') || $request->is(MaintenanceMode::EXEMPT_PATHS)) {
            return false;
        }

        if (! $response->isSuccessful() || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        $skipSignedIn = filter_var(Setting::get(self::SKIP_SIGNED_IN_KEY, true), FILTER_VALIDATE_BOOLEAN);

        return ! ($skipSignedIn && $request->user());
    }

    /** Google's own snippet, with the ID already checked against ID_PATTERN. */
    public static function snippet(): string
    {
        $id = self::measurementId();

        return <<<HTML
            <!-- Google Analytics: System Settings → Frontend Settings -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{$id}');
            </script>

            HTML;
    }
}
