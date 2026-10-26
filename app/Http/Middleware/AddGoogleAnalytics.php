<?php

namespace App\Http\Middleware;

use App\Helpers\GoogleAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the Google Analytics tag into public pages, just before </head>.
 * See App\Helpers\GoogleAnalytics for which pages and visitors get it.
 *
 * Appended to the web group after ShowMaintenancePage, so the maintenance
 * page — which answers without going any further — never carries the tag.
 */
class AddGoogleAnalytics
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! GoogleAnalytics::appliesTo($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        $at = is_string($content) ? stripos($content, '</head>') : false;

        if ($at !== false) {
            $response->setContent(substr_replace($content, GoogleAnalytics::snippet(), $at, 0));
        }

        return $response;
    }
}
