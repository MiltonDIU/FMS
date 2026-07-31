<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Dean / Head / office contacts for a department, from the university's
 * public backend.
 *
 * This used to live inline in DepartmentController::contact(), where it ran on
 * every request. It now also feeds the department listing page, so a live call
 * per page view is no longer acceptable: the answer changes a few times a year
 * and the API sits on someone else's server.
 *
 * Hence the cache — and a second, much shorter cache for failures, so an API
 * that is down costs one slow request every five minutes instead of one slow
 * request per visitor.
 */
class DepartmentContacts
{
    /**
     * The groups the API returns, in the order they are shown.
     * `icon` is intentionally absent — no theme ever used it.
     */
    public const BLOCKS = [
        ['key' => 'deans', 'title' => 'Dean'],
        ['key' => 'deans_officers', 'title' => "Dean's Office"],
        ['key' => 'department_heads', 'title' => 'Head of Department'],
        ['key' => 'department_officers', 'title' => 'Department Office'],
    ];

    /** How long a good answer is reused. */
    public const TTL = 6 * 3600;

    /** How long a failure is remembered. Short, so an outage self-heals. */
    public const FAILURE_TTL = 300;

    /**
     * Seconds to wait on the API. Lower than the eight the contact page used,
     * because a department page must not hang on someone else's server.
     */
    public const TIMEOUT = 4;

    /** Photos come back as paths relative to the backend host. */
    public const PHOTO_BASE_URL = 'https://webbackend.daffodilvarsity.edu.bd/';

    /**
     * @return array{sections: array<string, mixed>, error: string|null}
     */
    public static function for(Department $department): array
    {
        $code = strtolower(trim((string) ($department->code ?: $department->short_name ?? '')));

        if ($code === '') {
            return static::blank('This department has no contact code.');
        }

        $key = "department-contacts.{$code}";
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = static::fetch($code);

        Cache::put($key, $result, $result['error'] ? static::FAILURE_TTL : static::TTL);

        return $result;
    }

    /**
     * True when the API answered but had nobody to list — worth distinguishing
     * from an outage, since the two need different wording on screen.
     */
    public static function isEmpty(array $contacts): bool
    {
        foreach (static::BLOCKS as $block) {
            if (! empty($contacts['sections'][$block['key']])) {
                return false;
            }
        }

        return true;
    }

    protected static function fetch(string $code): array
    {
        $base = rtrim(config('services.diu_contacts_api', 'https://webbackend.daffodilvarsity.edu.bd/api/v1/public/department'), '/');

        try {
            $response = Http::timeout(static::TIMEOUT)->get("{$base}/{$code}/contact-us");
        } catch (ConnectionException $e) {
            return static::blank('Could not reach the contacts service.');
        }

        if (! $response->successful()) {
            return static::blank("Could not load contacts (HTTP {$response->status()}).");
        }

        $payload = $response->json();
        $data = $payload['data'] ?? $payload;

        if (! is_array($data)) {
            return static::blank('The contacts service returned something unexpected.');
        }

        $sections = ['department' => $data['department'] ?? null];

        foreach (static::BLOCKS as $block) {
            $sections[$block['key']] = static::people($data[$block['key']] ?? []);
        }

        return ['sections' => $sections, 'error' => null];
    }

    /**
     * Normalise the API's associative arrays into a clean, keyed list.
     */
    protected static function people(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->values()
            ->map(fn ($c) => [
                'name' => $c['name'] ?? null,
                'email' => $c['email'] ?? null,
                'mobile' => $c['mobile'] ?? null,
                'ip_phone' => $c['ip_phone'] ?? null,
                'designation' => $c['designation'] ?? null,
                'photo' => $c['photo'] ?? null,
                'photo_url' => ! empty($c['photo'])
                    ? static::PHOTO_BASE_URL . ltrim($c['photo'], '/')
                    : null,
            ])
            ->filter(fn ($c) => filled($c['name']))
            ->values()
            ->all();
    }

    protected static function blank(?string $error): array
    {
        $sections = ['department' => null];

        foreach (static::BLOCKS as $block) {
            $sections[$block['key']] = [];
        }

        return ['sections' => $sections, 'error' => $error];
    }
}
