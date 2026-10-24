<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when its token was issued for this ability by
 * name.
 *
 * Not Sanctum's own `abilities:` middleware. A token from the app's sign-in is
 * created with no abilities listed, which Sanctum records as "*" — and its
 * check answers yes to "*" for every ability there is. Every teacher's phone
 * would then hold a key to the HR endpoints. Here "*" counts for nothing: the
 * ability has to be written on the token, which only teachers:issue-hr-token
 * does.
 */
class RequireTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $abilities = $request->user()?->currentAccessToken()?->abilities ?? [];

        if (! in_array($ability, $abilities, true)) {
            return new JsonResponse([
                'message' => 'This token was not issued for this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
