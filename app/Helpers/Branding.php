<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Centralised accessors for site branding / identity data.
 *
 * Every value resolves from the `settings` table (prefixed `branding_`),
 * falling back to the hardcoded defaults below so the frontend keeps working
 * even before the admin seeds or saves anything. Admin configures all of this
 * from the "Branding & Site Identity" tab in System Settings.
 */
class Branding
{
    public const PREFIX = 'branding_';

    public const DEFAULTS = [
        self::PREFIX . 'site_name'            => 'Daffodil International University',
        self::PREFIX . 'site_short_name'      => 'DAFFODIL',
        self::PREFIX . 'short_name'            => 'DIU',
        self::PREFIX . 'badge_text'           => 'Directory',
        self::PREFIX . 'tagline'              => 'International University, Bangladesh',
        self::PREFIX . 'logo_mode'            => 'text',
        self::PREFIX . 'monogram'             => 'D',
        self::PREFIX . 'address_header'       => 'Smart City campus: Ashulia, Savar, Dhaka',
        self::PREFIX . 'address_footer'       => 'Smart City Campus, Dhaka',
        self::PREFIX . 'address_full'         => 'Daffodil Smart City, Ashulia, Dhaka',
        self::PREFIX . 'email'                => 'info@daffodilvarsity.edu.bd',
        self::PREFIX . 'phone'               => '',
        self::PREFIX . 'main_site_url'        => 'https://daffodilvarsity.edu.bd',
        self::PREFIX . 'main_site_label'      => 'Main Site',
        self::PREFIX . 'login_label'          => 'Teacher Login',
        self::PREFIX . 'portal_label'         => 'Academic Portal',
        self::PREFIX . 'portal_sublabel'      => 'Active Directory',
        self::PREFIX . 'scholars_label'       => 'Scholars',
        self::PREFIX . 'stat_faculties_label' => 'Academic Faculties',
        self::PREFIX . 'stat_departments_label' => 'Departments',
        self::PREFIX . 'stat_profiles_label'  => 'Faculty Profiles',
        self::PREFIX . 'footer_name'          => 'DAFFODIL INTERNATIONAL UNIVERSITY',
        self::PREFIX . 'footer_descriptor'    => 'Official Scholar Profile & Citation Directory',
        self::PREFIX . 'footer_copyright'     => 'Daffodil International University',
        self::PREFIX . 'footer_accreditation' => 'BAETE & IEB Accredited Smart Campus, Savar, Dhaka, Bangladesh.',
        self::PREFIX . 'meta_title_suffix'    => ' - Faculty Directory',
        self::PREFIX . 'meta_description'      => 'Daffodil International University — Faculty & Scholar Directory',
        self::PREFIX . 'watermark_text'        => 'DIU',
    ];

    /**
     * Get a single branding value, falling back to the hard-coded default.
     */
    public static function get(string $key): mixed
    {
        if (! str_starts_with($key, self::PREFIX)) {
            $key = self::PREFIX . $key;
        }

        $value = Setting::get($key, null);

        if ($value === null || $value === '') {
            return self::DEFAULTS[$key] ?? null;
        }

        return $value;
    }

    /**
     * Public URL for the uploaded logo image (null when none uploaded).
     */
    public static function logoUrl(): ?string
    {
        $path = Setting::get(self::PREFIX . 'logo_image', null);

        if (is_array($path)) {
            $path = reset($path);
        }

        if (empty($path)) {
            return null;
        }

        if (is_string($path) && (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '//')
        )) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Whether the admin has chosen to render an uploaded image logo.
     */
    public static function useImageLogo(): bool
    {
        return self::get('logo_mode') === 'image' && self::logoUrl() !== null;
    }

    /**
     * Return the social links as a clean array of {platform, url} pairs.
     */
    public static function socialLinks(): array
    {
        $raw = self::get(self::PREFIX . 'social_links');

        if (is_array($raw)) {
            $links = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            $links = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter($links, fn ($l) => ! empty($l['url'] ?? '') && ! empty($l['platform'] ?? '')));
    }

    /**
     * Return every resolved branding value keyed by the bare key name
     * (without the `branding_` prefix) for convenient View::share usage.
     */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $fullKey) {
            $bare = substr($fullKey, strlen(self::PREFIX));
            $out[$bare] = self::get($fullKey);
        }

        $out['logo_url'] = self::logoUrl();
        $out['use_image_logo'] = self::useImageLogo();
        $out['social_links'] = self::socialLinks();

        return $out;
    }
}
