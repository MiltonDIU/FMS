<?php

namespace App\Http\Middleware;

use App\Helpers\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Swaps every public answer for the maintenance page while the System Settings
 * switch is on. See App\Helpers\MaintenanceMode for what stays reachable.
 *
 * Appended to the web group so the session has started by the time it runs,
 * which is what lets a signed-in admin through.
 */
class ShowMaintenancePage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (MaintenanceMode::blocks($request)) {
            return MaintenanceMode::response($request);
        }

        return $next($request);
    }
}
