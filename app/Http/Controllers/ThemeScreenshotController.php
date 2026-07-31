<?php

namespace App\Http\Controllers;

use App\Helpers\Theme;
use App\Models\Setting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a theme's screenshot to the settings page.
 *
 * The image lives inside the theme folder so a theme stays one deletable unit,
 * and resources/ is not web-served — hence a route rather than an asset link.
 *
 * The slug is only ever used to look the theme up in Theme::installed(), and the
 * path comes back from that lookup, so a crafted slug cannot reach a file
 * outside the themes directory.
 */
class ThemeScreenshotController extends Controller
{
    /** Formats a theme may ship, mapped to what we will claim they are. */
    protected const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    public function __invoke(Request $request, string $theme): BinaryFileResponse
    {
        abort_unless(
            $request->user()?->can('View:SystemSettings', Setting::class),
            403,
        );

        $path = Theme::screenshotPath($theme);

        abort_if($path === null || ! is_file($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(isset(static::TYPES[$extension]), 404);

        return response()->file($path, [
            'Content-Type' => static::TYPES[$extension],
            // Someone replacing a screenshot expects to see the new one, and
            // these are only ever fetched by a handful of administrators.
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
