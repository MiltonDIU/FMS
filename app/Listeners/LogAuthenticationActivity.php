<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records who signed in, who signed out, and who tried and failed.
 *
 * The activity log's own trait only sees model events — created, updated,
 * deleted — so signing in is invisible to it: no row changes. Laravel fires its
 * own auth events instead, and this turns them into log entries under the
 * 'auth' log name, kept apart from the 'User' entries that record edits to an
 * account.
 *
 * The IP address and user agent are not added here. App\Models\Activity fills
 * those on every entry, so they arrive without this having to think about it.
 *
 * Registration is automatic: Laravel discovers listeners in app/Listeners by
 * the event each method type-hints, so these five need no wiring. Registering
 * them again by hand — as a subscriber, say — logs everything twice.
 *
 * A failed attempt is worth more than a successful one. A handful of failures
 * is somebody mistyping; a run of them against different accounts from one
 * address is not, and there is nothing else in this system that would show it.
 */
class LogAuthenticationActivity
{
    /** Kept apart from the 'User' log, which records edits to an account. */
    public const LOG_NAME = 'auth';

    public function handleLogin(Login $event): void
    {
        activity(self::LOG_NAME)
            ->causedBy($event->user)
            ->on($event->user)
            ->withProperties([
                'guard' => $event->guard,
                'remembered' => (bool) $event->remember,
            ])
            ->event('login')
            ->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        // A session that expires rather than being signed out of fires this with
        // no user; there is nothing to attribute, so nothing is recorded.
        if (! $event->user) {
            return;
        }

        activity(self::LOG_NAME)
            ->causedBy($event->user)
            ->on($event->user)
            ->withProperties(['guard' => $event->guard])
            ->event('logout')
            ->log('logout');
    }

    public function handleFailed(Failed $event): void
    {
        activity(self::LOG_NAME)
            // causedBy stays empty when the account does not exist — the
            // attempted identifier goes in properties instead, so a run of
            // attempts against accounts that are not there is still visible.
            ->causedBy($event->user)
            ->withProperties([
                'guard' => $event->guard,
                'attempted' => $this->identifier($event->credentials),
                'account_exists' => $event->user !== null,
            ])
            ->event('failed')
            ->log('failed login');
    }

    public function handleLockout(Lockout $event): void
    {
        activity(self::LOG_NAME)
            ->withProperties([
                'attempted' => $this->identifier($event->request->only(['email', 'username'])),
            ])
            ->event('locked')
            ->log('locked out');
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        activity(self::LOG_NAME)
            ->causedBy($event->user)
            ->on($event->user)
            ->event('password_reset')
            ->log('password reset');
    }

    /**
     * The identifier someone tried to sign in with, and nothing else.
     *
     * The credentials array carries the submitted password. Laravel marks the
     * parameter #[\SensitiveParameter] so it stays out of stack traces, and it
     * has no business in a log either — only the username is taken, by name,
     * rather than filtering the password out and hoping nothing else sensitive
     * was in there.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function identifier(array $credentials): ?string
    {
        foreach (['email', 'username', 'name'] as $key) {
            if (filled($credentials[$key] ?? null) && is_string($credentials[$key])) {
                return mb_substr($credentials[$key], 0, 191);
            }
        }

        return null;
    }
}
