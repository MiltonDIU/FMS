<?php

namespace App\Http\Middleware;

use App\Helpers\Theme;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the public site with a plain explanation when no theme is installed.
 *
 * Theme::active() already falls back to any theme that is present, so this only
 * fires when every theme folder has been removed. Without it the controllers
 * would still render (Theme::view() returns the missing-theme page), but with a
 * 200 — telling search engines the directory is fine and empty. 503 says the
 * opposite, and keeps the outage out of the index.
 *
 * Wrapped around the public routes only; the admin panel is where someone would
 * go to fix this, so it must stay reachable.
 */
class EnsureThemeInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Theme::active() === '') {
            return response()
                ->view(Theme::MISSING_VIEW, [], Response::HTTP_SERVICE_UNAVAILABLE)
                ->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
