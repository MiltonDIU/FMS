<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Controls whether the public teacher profile exposes the vCard (Save Contact)
 * and CV / PDF download actions.
 *
 * Both flags default to ENABLED so existing behaviour is preserved until the
 * admin explicitly turns one off from System Settings → Frontend Settings →
 * Profile Download Options. The controller enforces the same gates so the
 * files can't be fetched directly while disabled.
 */
class ProfileDownload
{
    public const VCARD_KEY = 'profile_enable_vcard';

    public const CV_KEY = 'profile_enable_cv';

    /**
     * Whether the vCard download is available on public profiles.
     */
    public static function vcardEnabled(): bool
    {
        return (bool) filter_var(Setting::get(self::VCARD_KEY, true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the CV / PDF download is available on public profiles.
     */
    public static function cvEnabled(): bool
    {
        return (bool) filter_var(Setting::get(self::CV_KEY, true), FILTER_VALIDATE_BOOLEAN);
    }
}
