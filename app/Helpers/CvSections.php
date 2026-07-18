<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Defines the individual content sections that may be included in the
 * downloaded CV / PDF, and whether each is currently enabled.
 *
 * The admin toggles these from System Settings → Frontend Settings →
 * "CV / PDF Content". Each section defaults to ENABLED so existing CVs keep
 * their full content until an admin explicitly hides a section.
 */
class CvSections
{
    /**
     * Section key => human label (used in the admin UI + as the field key
     * suffix: `cv_section_<key>`).
     *
     * "basic_info" covers the header block (name, title, organisation and the
     * contact line: email / phone / room). The remaining keys map 1:1 to the
     * relationships rendered in resources/views/frontend/cv.blade.php.
     */
    public const SECTIONS = [
        'basic_info'       => 'Basic Info (Name, Title, Contact)',
        'profile'          => 'Profile / Bio & Research Interests',
        'experience'       => 'Experience',
        'publications'     => 'Publications',
        'teaching_areas'   => 'Teaching Areas',
        'education'        => 'Education',
        'skills'           => 'Skills',
        'memberships'      => 'Memberships',
        'awards'           => 'Awards & Honors',
        'certifications'   => 'Certifications',
        'links'            => 'Links (Social)',
    ];

    public static function settingKey(string $section): string
    {
        return 'cv_section_' . $section;
    }

    /**
     * Whether a given section is enabled for the CV / PDF.
     */
    public static function enabled(string $section): bool
    {
        return (bool) filter_var(Setting::get(self::settingKey($section), true), FILTER_VALIDATE_BOOLEAN);
    }

    public const WATERMARK_ENABLED_KEY = 'cv_watermark_enabled';

    public const WATERMARK_TEXT_KEY = 'cv_watermark_text';

    /**
     * Whether a watermark should be printed on the downloaded CV / PDF.
     */
    public static function watermarkEnabled(): bool
    {
        return (bool) filter_var(Setting::get(self::WATERMARK_ENABLED_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Watermark text (falls back to the branding watermark text, then "DIU").
     */
    public static function watermarkText(): string
    {
        $text = (string) Setting::get(self::WATERMARK_TEXT_KEY, '');

        if (trim($text) === '') {
            $text = (string) \App\Helpers\Branding::get('watermark_text');
        }

        return trim($text) !== '' ? $text : 'DIU';
    }
}
