<?php

namespace App\Http\Middleware;

use App\Helpers\SearchEngines;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells search engines not to index or follow anything, while System Settings
 * says the site is to be kept out of search. See App\Helpers\SearchEngines.
 *
 * Global rather than in the web group, so the admin panel's login page and
 * downloaded files carry it too.
 */
class BlockSearchEngines
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (SearchEngines::blocked()) {
                $response->headers->set('X-Robots-Tag', SearchEngines::HEADER);
            }
        } catch (\Throwable) {
            // The settings table being unreachable is not this middleware's to report.
        }

        return $response;
    }
}
