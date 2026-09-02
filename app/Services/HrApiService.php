<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The DIU HR / SSO employee API.
 *
 * The mapping side of teacher import already existed and works: 83 rules in
 * IntegrationMappingSeeder, written against this vendor's field names. What was
 * missing was any way to reach the vendor at all — nothing in the import path
 * made an HTTP request, and this API requires a Keycloak bearer token. That gap
 * is all this class fills: get a token, call the two endpoints, hand the raw
 * JSON to IntegrationService::transform() exactly as before.
 *
 * Credentials come from settings, entered in the admin panel after the project
 * is configured, so no working secret lives in the repository.
 */
class HrApiService
{
    /** Token cache key. Includes the credentials so rotating one drops it. */
    protected const TOKEN_CACHE_PREFIX = 'hr_api.token.';

    protected const TIMEOUT = 25;

    /*
     * What the vendor's endpoints were on the day this was written. They are
     * fallbacks, not the answer: the answer is whatever the corresponding
     * setting holds, and these only stand in while it is empty — a fresh
     * install, or a settings row not yet seeded.
     *
     * The paths live in settings because they are the vendor's to change, not
     * ours. An endpoint moving used to mean editing this file and deploying;
     * now it means editing a field on the Teacher API Integration tab.
     */
    public const DEFAULT_SEARCH_PATH = '/api/ess/portal/external-employee-info';

    public const DEFAULT_PROFILE_PATH = '/api/ess/portal/external-employee-info/{employeeId}';

    /**
     * Where the employee id goes in the profile path.
     *
     * A placeholder rather than "whatever is appended", so the vendor can move
     * the id into the middle of a path — /employee/{employeeId}/profile — or
     * into a query string without this class needing to know.
     */
    public const EMPLOYEE_ID_PLACEHOLDER = '{employeeId}';

    /**
     * Whether an administrator has finished entering the credentials.
     */
    public function isConfigured(): bool
    {
        foreach (['hr_api_base_url', 'hr_api_token_url', 'hr_api_client_id', 'hr_api_username'] as $key) {
            if (blank(Setting::get($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Search employees by name, id or email.
     *
     * @return array<int,array<string,mixed>> the vendor's records, untouched
     *
     * @throws \RuntimeException when the API cannot be reached or refuses us
     */
    public function searchTeachers(string $query, int $page = 1, int $perPage = 20): array
    {
        $response = $this->get(
            $this->searchPath(),
            self::searchParameters($query) + [
                'pageNumber' => $page,
                'pageSize' => $perPage,
            ],
        );

        $records = $response['data'] ?? [];

        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    /**
     * Put the search term in the parameter that matches what was typed.
     *
     * The endpoint takes employeeId, name and email as separate filters and
     * combines them, so sending the same text in all three matches nobody.
     * Everything used to go to `name`, which meant searching by employee number
     * or address silently returned nothing.
     *
     * An empty term sends no filter at all, which lists the directory — that is
     * what the bulk import relies on.
     *
     * @return array<string,string>
     */
    public static function searchParameters(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        if (str_contains($query, '@')) {
            return ['email' => $query];
        }

        // Employee numbers are digits and nothing else; a name never is.
        if (preg_match('/^\d+$/', $query)) {
            return ['employeeId' => $query];
        }

        return ['name' => $query];
    }

    /**
     * One employee's full profile.
     *
     * Returns the response with `teacher_profile` lifted to the top level and
     * `core_info` flattened into it, because that is the shape the existing
     * mapping rules read: they name `employee_id` and
     * `employeeEducationalInformations.instituteName`, not `core_info.…`.
     *
     * @return array<string,mixed>|null null when the API has no such employee
     *
     * @throws \RuntimeException when the API cannot be reached or refuses us
     */
    public function getTeacherProfile(string $employeeId): ?array
    {
        return self::normaliseProfile(
            $this->get($this->profilePath($employeeId)),
        );
    }

    /**
     * The search endpoint's path, as configured.
     */
    public function searchPath(): string
    {
        return $this->configuredPath('hr_api_search_path', self::DEFAULT_SEARCH_PATH);
    }

    /**
     * The profile endpoint's path for one employee, as configured.
     *
     * The id is url-encoded wherever it lands. A configured path with no
     * placeholder is treated as a collection address and the id appended, which
     * is what every version of this method did before the placeholder existed —
     * so a path pasted in without one still works rather than silently
     * requesting the whole list.
     */
    public function profilePath(string $employeeId): string
    {
        $path = $this->configuredPath('hr_api_profile_path', self::DEFAULT_PROFILE_PATH);
        $encoded = rawurlencode($employeeId);

        if (! str_contains($path, self::EMPLOYEE_ID_PLACEHOLDER)) {
            return rtrim($path, '/') . '/' . $encoded;
        }

        return str_replace(self::EMPLOYEE_ID_PLACEHOLDER, $encoded, $path);
    }

    /**
     * A path from settings, falling back to what the vendor used at the time.
     *
     * An empty setting means "not configured", not "no path" — a blank field
     * would otherwise point every call at the base URL itself.
     */
    protected function configuredPath(string $settingKey, string $default): string
    {
        $path = trim((string) Setting::get($settingKey));

        return $path === '' ? $default : $path;
    }

    /**
     * Reshape a profile response into the form the mapping rules are written
     * against.
     *
     * The vendor nests everything under `teacher_profile` and puts the scalar
     * columns one level deeper again in `core_info`. The mapping rules name
     * `employee_id` and `employeeEducationalInformations.instituteName`, so the
     * wrapper is stripped and `core_info` lifted alongside its sibling
     * collections.
     *
     * Public and static because the Integration Mapping screen has to detect
     * fields from exactly this shape — if the screen and the importer disagreed
     * about it, every rule an administrator built would point at a path the
     * import never sees.
     *
     * @param array<string,mixed> $response the raw response body
     * @return array<string,mixed>|null null when there is no profile in it
     */
    public static function normaliseProfile(array $response): ?array
    {
        $profile = $response['teacher_profile'] ?? $response;

        if (! is_array($profile) || $profile === []) {
            return null;
        }

        $coreInfo = $profile['core_info'] ?? [];
        unset($profile['core_info']);

        $normalised = array_merge(is_array($coreInfo) ? $coreInfo : [], $profile);

        return $normalised === [] ? null : $normalised;
    }

    /**
     * Whether this URL points at the configured HR API.
     */
    public function ownsUrl(string $url): bool
    {
        $base = trim((string) Setting::get('hr_api_base_url'));

        if ($base === '') {
            return false;
        }

        return parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST);
    }

    /**
     * Authenticated GET of any URL on the HR API.
     *
     * Used by the Integration Mapping screen so an administrator can point
     * "Fetch Data" at a live endpoint and get a real response back rather than
     * a 401.
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException
     */
    public function fetchUrl(string $url): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('The HR API is not configured yet. Set it up under System Settings → Teacher API Integration.');
        }

        $response = $this->send($url, [], $this->accessToken());

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->send($url, [], $this->accessToken());
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->status(), $response->json()));
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException('The HR API did not return JSON.');
        }

        return $body;
    }

    /**
     * Fetch a token and report the outcome, for the "Test Connection" button.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Fill in the base URL, token URL, client ID and username first, then save.',
            ];
        }

        try {
            $this->forgetToken();
            $this->accessToken();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Access token received. The credentials work.'];
    }

    /**
     * GET a path on the HR API, with the bearer token attached.
     *
     * A 401 drops the cached token and retries once, which is what makes a
     * token that expired early heal itself instead of needing an admin.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     *
     * @throws \RuntimeException
     */
    protected function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('The HR API is not configured yet. Set it up under System Settings → Teacher API Integration.');
        }

        $url = rtrim((string) Setting::get('hr_api_base_url'), '/') . '/' . ltrim($path, '/');

        $response = $this->send($url, $query, $this->accessToken());

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->send($url, $query, $this->accessToken());
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->status(), $response->json()));
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException('The HR API did not return JSON.');
        }

        // The vendor reports failure inside a 200 response.
        if (array_key_exists('success', $body) && ! filter_var($body['success'], FILTER_VALIDATE_BOOLEAN)) {
            throw new \RuntimeException('The HR API refused the request: ' . ($body['message'] ?? 'no reason given') . '.');
        }

        return $body;
    }

    /**
     * @param array<string,mixed> $query
     */
    protected function send(string $url, array $query, string $token): \Illuminate\Http\Client\Response
    {
        try {
            return Http::acceptJson()
                ->withToken($token)
                ->timeout(self::TIMEOUT)
                ->get($url, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException('Could not reach the HR API: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * A usable access token, from cache when one is still valid.
     *
     * @throws \RuntimeException
     */
    protected function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUrl = (string) Setting::get('hr_api_token_url');

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT)
                ->post($tokenUrl, [
                    'grant_type' => 'password',
                    'client_id' => (string) Setting::get('hr_api_client_id'),
                    'client_secret' => (string) Setting::get('hr_api_client_secret'),
                    'username' => (string) Setting::get('hr_api_username'),
                    'password' => (string) Setting::get('hr_api_password'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException('Could not reach the token endpoint: ' . $e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            $body = $response->json();
            $detail = $body['error_description'] ?? $body['error'] ?? ('HTTP ' . $response->status());

            throw new \RuntimeException("The token endpoint rejected our credentials ({$detail}).");
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw new \RuntimeException('The token endpoint responded without an access_token.');
        }

        // A minute of headroom, so a request already in flight is never handed
        // a token that expires under it.
        $ttl = max(0, (int) ($response->json('expires_in') ?? 0) - 60);

        if ($ttl > 0) {
            Cache::put($this->tokenCacheKey(), $token, $ttl);
        }

        return $token;
    }

    protected function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Keyed on the credentials, so changing a secret retires the old token
     * instead of leaving it in play until it expires on its own.
     */
    protected function tokenCacheKey(): string
    {
        $fingerprint = substr(hash('sha256', implode('|', [
            (string) Setting::get('hr_api_token_url'),
            (string) Setting::get('hr_api_client_id'),
            (string) Setting::get('hr_api_client_secret'),
            (string) Setting::get('hr_api_username'),
            (string) Setting::get('hr_api_password'),
        ])), 0, 16);

        return self::TOKEN_CACHE_PREFIX . $fingerprint;
    }

    /**
     * @param array<string,mixed>|null $body
     */
    protected function errorMessage(int $status, ?array $body): string
    {
        $detail = $body['message'] ?? $body['error'] ?? null;
        $suffix = is_string($detail) && $detail !== '' ? ": {$detail}" : '.';

        Log::warning("HR API returned HTTP {$status}{$suffix}");

        return match (true) {
            $status === 401 || $status === 403 => "The HR API rejected our credentials (HTTP {$status}){$suffix}",
            $status === 404 => "The HR API has no such employee (HTTP 404){$suffix}",
            $status >= 500 => "The HR API is unavailable (HTTP {$status}){$suffix}",
            default => "The HR API returned HTTP {$status}{$suffix}",
        };
    }
}
