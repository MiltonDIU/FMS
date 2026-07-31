<?php

namespace App\Helpers;

/**
 * Vets a URL the application is about to fetch on an operator's behalf.
 *
 * The integration mapping screen lets an admin type any address and have the
 * server request it, which is a server-side request forgery hole: the server
 * sits inside the network, so it can reach the database host, other internal
 * services, and cloud metadata endpoints like 169.254.169.254 that hold instance
 * credentials. The response body is shown back in the UI, so anything reachable
 * is also readable.
 *
 * Private ranges are therefore refused — except for hosts that are obviously
 * meant to be reachable: the application's own origin (the integration URL
 * points at it during development) and anything the deployment lists in
 * services.integration.allowed_hosts.
 */
class OutboundUrl
{
    /**
     * Why this URL must not be fetched, or null when it is acceptable.
     */
    public static function rejectionReason(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return 'That does not look like a valid URL.';
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Only http and https addresses can be fetched.';
        }

        // user:pass@host would send credentials somewhere unintended.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Remove the credentials from the URL.';
        }

        $host = strtolower($parts['host']);

        if (self::isAllowlisted($host)) {
            return null;
        }

        foreach (self::resolve($host) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return "{$host} resolves to the internal address {$ip}. Add it to "
                    . 'services.integration.allowed_hosts if it is genuinely an '
                    . 'integration endpoint.';
            }
        }

        return null;
    }

    /**
     * Hosts this deployment has declared safe, plus its own origin.
     *
     * @return array<int,string>
     */
    public static function allowedHosts(): array
    {
        $configured = (array) config('services.integration.allowed_hosts', []);

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (filled($appHost)) {
            $configured[] = $appHost;
        }

        return array_values(array_filter(array_map(
            fn ($host) => strtolower(trim((string) $host)),
            $configured,
        )));
    }

    protected static function isAllowlisted(string $host): bool
    {
        return in_array($host, self::allowedHosts(), true);
    }

    /**
     * Every address the host resolves to.
     *
     * All of them are checked, not just the first: a name that returns one
     * public and one private address would otherwise slip through.
     *
     * @return array<int,string>
     */
    protected static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($ip) {
                $ips[] = $ip;
            }
        }

        if (empty($ips)) {
            $resolved = gethostbyname($host);
            // gethostbyname hands back the input unchanged when it fails.
            $ips = $resolved !== $host ? [$resolved] : [];
        }

        return $ips;
    }
}
