<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a freshly activated teacher on the password page until they set one.
 *
 * Enforced server-side rather than by prompting in the interface, because a
 * prompt is only a suggestion: navigating elsewhere would leave the account
 * signed in and still without a password of its own, which is the state the
 * activation link exists to end.
 */
class RequirePasswordSetup
{
    /**
     * Routes reachable while the password is still outstanding.
     */
    protected const ALLOWED = [
        'teacher.password.create',
        'teacher.password.store',
        'filament.admin.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->password_set_at !== null) {
            return $next($request);
        }

        // Only accounts that arrived through activation are held here. An
        // administrator created before this flow existed has a null
        // password_set_at too, and must not be locked out of their own panel.
        if (! $user->isTeacher()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        // Livewire's own endpoints have to keep working, or the password form
        // itself cannot submit.
        if ($request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('teacher.password.create');
    }
}
