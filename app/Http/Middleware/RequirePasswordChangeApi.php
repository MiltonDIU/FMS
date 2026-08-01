<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses everything until a teacher has replaced the password they were sent.
 *
 * Every account came over from the migration with a password generated for it
 * and emailed out, so the first sign-in uses a secret that has travelled through
 * a mailbox. Until it is replaced, the account is only as private as that email.
 *
 * The app shows a "choose a password" screen, but a screen is a suggestion: a
 * modified client, or anyone talking to the API directly, would simply skip it.
 * So the refusal is here. This mirrors RequirePasswordSetup, which does the same
 * job for the web panel and for the same reason.
 *
 * The three endpoints left open are the ones needed to get out of the state —
 * find out you are in it, leave it, or sign out.
 */
class RequirePasswordChangeApi
{
    /**
     * Route names reachable while the emailed password is still in use.
     */
    protected const ALLOWED = [
        'api.auth.me',
        'api.auth.password.change',
        'api.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->password_set_at !== null) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'Choose your own password before continuing.',
            'must_change_password' => true,
        ], Response::HTTP_FORBIDDEN);
    }
}
