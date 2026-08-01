<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * The activity log entry, extended so that every record carries where it came
 * from.
 *
 * Spatie captures who and what, never from where: there is no mention of an IP
 * address or a user agent anywhere in the package. Version 5 also removed the
 * "taps" extension point earlier versions offered for adding defaults, so the
 * remaining way to attach something to *every* entry — model changes and
 * hand-written entries alike — is to own the model and fill it as it is
 * created. config/activitylog.php points at this class.
 *
 * The values are merged into properties rather than assigned, so nothing a
 * caller already put there is lost, and an entry that set its own address wins.
 * Empty values are dropped rather than stored: an entry with no client has no
 * ip_address key at all, which reads better than a column full of nulls and is
 * what a query should test for.
 *
 * Nothing else changes: the table, the relationships and the query scopes are
 * all inherited.
 */
class Activity extends SpatieActivity
{
    /** Under which keys the request context is stored. */
    public const IP_KEY = 'ip_address';

    public const AGENT_KEY = 'user_agent';

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->properties = collect($activity->properties ?? [])
                ->merge(array_filter(static::requestContext()))
                ->merge($activity->properties ?? [])   // an explicit value wins
                ->all();
        });
    }

    /**
     * Where this entry came from.
     *
     * A console run has no client; recording the command instead of an empty
     * address makes an entry written by a scheduled task or an import
     * identifiable, which is exactly when "who did this" is hardest to answer.
     *
     * The two are told apart by the raw REMOTE_ADDR superglobal rather than by
     * runningInConsole(). Two reasons. Laravel reports the console as running
     * for artisan *and* for the test suite, so that check would mean the address
     * could never be exercised by a test. And request()->ip() does not fall back
     * to nothing on the command line — Symfony builds a request from globals and
     * hands back 127.0.0.1, an address that looks real and is not. REMOTE_ADDR
     * is set by a web server and by nothing else.
     *
     * @return array<string, string|null>
     */
    protected static function requestContext(): array
    {
        if (! isset($_SERVER['REMOTE_ADDR'])) {
            $command = trim(implode(' ', array_slice($_SERVER['argv'] ?? [], 1)));

            return [
                static::IP_KEY => null,
                static::AGENT_KEY => 'console' . ($command !== '' ? ': ' . $command : ''),
            ];
        }

        if (! app()->bound('request')) {
            return [];
        }

        return [
            static::IP_KEY => request()->ip(),
            // Cut: some agent strings run to several hundred characters, and the
            // whole properties column is read back as JSON on every listing.
            static::AGENT_KEY => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ];
    }
}
