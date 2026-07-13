<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleFrontendDriverMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $driver = Setting::get('frontend_driver', 'blade');

        if ($driver === 'nextjs') {
            $nextjsUrl = rtrim(Setting::get('nextjs_url', ''), '/');
            if (!empty($nextjsUrl)) {
                $path = $request->getPathInfo();
                $query = $request->getQueryString();
                $redirectUrl = $nextjsUrl . $path . ($query ? '?' . $query : '');
                
                return redirect()->away($redirectUrl);
            }
        }

        return $next($request);
    }
}
