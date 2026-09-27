<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The site-wide "under maintenance" switch in System Settings.
 *
 * Laravel's own `artisan down` needs shell access and takes the admin panel
 * down with everything else. This is a setting instead, so it can be flipped
 * from the panel while the panel keeps working — which is the point: the
 * internal work happens there while the public sees one calm page.
 *
 * While it is on, every public request — a real page, an unknown URL, a
 * controller that throws — answers with the maintenance page and nothing else.
 * The middleware covers requests that reach a route; the exception handler
 * (bootstrap/app.php) covers the ones that fail before or while getting there,
 * so no 404 or error page leaks through.
 */
class MaintenanceMode
{
    public const ENABLED_KEY = 'maintenance_mode_enabled';

    public const TITLE_KEY = 'maintenance_mode_title';

    public const MESSAGE_KEY = 'maintenance_mode_message';

    public const ALLOW_ADMINS_KEY = 'maintenance_mode_allow_admins';

    public const BACKGROUND_KEY = 'maintenance_mode_background';

    public const VIEW = 'frontend.maintenance';

    public const DEFAULT_TITLE = 'We\'ll be back soon';

    public const DEFAULT_MESSAGE = 'The site is undergoing scheduled maintenance. Please check back in a little while.';

    /**
     * Paths that keep working while the switch is on.
     *
     * The admin panel and everything it loads (Livewire updates, Filament
     * assets, uploaded files, built CSS/JS), plus the teacher account flows
     * that land in the panel. Email tracking is here because it is fetched by
     * mail clients, not people, and the batch statistics should not go blank
     * for the length of the maintenance.
     */
    public const EXEMPT_PATHS = [
        'admin',
        'admin/*',
        'livewire*',
        'filament/*',
        'css/filament/*',
        'js/filament/*',
        'fonts/filament/*',
        'build/*',
        'storage/*',
        'up',
        'teacher/activate/*',
        'teacher/set-password',
        'email-track/*',
    ];

    public static function enabled(): bool
    {
        return filter_var(Setting::get(self::ENABLED_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function title(): string
    {
        return trim((string) Setting::get(self::TITLE_KEY, '')) ?: self::DEFAULT_TITLE;
    }

    public static function message(): string
    {
        return trim((string) Setting::get(self::MESSAGE_KEY, '')) ?: self::DEFAULT_MESSAGE;
    }

    /**
     * Public URL of the uploaded background image, or null for the plain page.
     * Served from storage/*, which stays reachable while the switch is on.
     */
    public static function backgroundUrl(): ?string
    {
        $path = Setting::get(self::BACKGROUND_KEY);

        if (is_array($path)) {
            $path = reset($path);
        }

        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }

    /**
     * Whether this request should get the maintenance page instead of its answer.
     */
    public static function blocks(Request $request): bool
    {
        if (! self::enabled() || $request->is(self::EXEMPT_PATHS)) {
            return false;
        }

        return ! self::viewerBypasses($request);
    }

    /**
     * Signed-in admins can still browse the public site to check their work.
     *
     * Only when a session exists: a request that 404s before routing never
     * started one, and asking the guard then would be a guess.
     */
    protected static function viewerBypasses(Request $request): bool
    {
        if (! filter_var(Setting::get(self::ALLOW_ADMINS_KEY, true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return false;
        }

        return (bool) $request->user()?->can('View:SystemSettings', Setting::class);
    }

    /**
     * The one answer a blocked request gets: 503 so search engines keep the
     * existing index, JSON for the mobile apps, the page for everyone else.
     */
    public static function response(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()
                ->json(['message' => self::message(), 'maintenance' => true], Response::HTTP_SERVICE_UNAVAILABLE)
                ->header('Retry-After', '3600');
        }

        return response()
            ->view(self::VIEW, [
                'title' => self::title(),
                'message' => self::message(),
                'background' => self::backgroundUrl(),
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', '3600');
    }

    /**
     * For the exception handler: the maintenance page if this request should
     * get it, otherwise null so normal error rendering carries on.
     *
     * Never throws. If the settings table itself is what failed, the real
     * error is more useful than a second one raised from here.
     */
    public static function responseFor(Request $request): ?Response
    {
        try {
            return self::blocks($request) ? self::response($request) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
