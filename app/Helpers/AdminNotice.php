<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * A notice under the admin panel's top bar, for everyone signed in, which each
 * of them can close for the rest of the day.
 *
 * The other half of System Settings → Maintenance Mode: for work that does not
 * need the site taken down — a planned update, data being re-imported, a
 * deadline — but that the people using the panel should hear about. The public
 * site is not touched; only signed-in users see it, on every panel page.
 *
 * Like MaintenanceMode, it reads its settings with fallbacks, so it works on a
 * database that was never seeded: the deployment does not seed.
 */
class AdminNotice
{
    public const ENABLED_KEY = 'admin_notice_enabled';

    public const MESSAGE_KEY = 'admin_notice_message';

    public const STYLE_KEY = 'admin_notice_style';

    public const VIEW = 'filament.admin-notice';

    /**
     * Banner colours, one set for the light panel and one for dark.
     *
     * Solid, not translucent: the first version laid a mid-blue text on a
     * tint of the same blue, which read poorly in light mode and almost not at
     * all in dark. Each pair here is a pale ground with near-black text of its
     * hue, or the reverse, and clears 7:1 contrast.
     */
    public const STYLES = [
        'info' => [
            'label' => 'Information (blue)',
            'bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1e3a8a',
            'dark_bg' => '#172554', 'dark_border' => '#1e40af', 'dark_text' => '#dbeafe',
        ],
        'warning' => [
            'label' => 'Warning (amber)',
            'bg' => '#fffbeb', 'border' => '#fcd34d', 'text' => '#78350f',
            'dark_bg' => '#451a03', 'dark_border' => '#92400e', 'dark_text' => '#fef3c7',
        ],
        'danger' => [
            'label' => 'Important (red)',
            'bg' => '#fef2f2', 'border' => '#fca5a5', 'text' => '#7f1d1d',
            'dark_bg' => '#450a0a', 'dark_border' => '#991b1b', 'dark_text' => '#fee2e2',
        ],
    ];

    public const DEFAULT_STYLE = 'warning';

    public static function message(): string
    {
        return trim((string) Setting::get(self::MESSAGE_KEY, ''));
    }

    /** Shown only when switched on and there is something to say. */
    public static function active(): bool
    {
        return filter_var(Setting::get(self::ENABLED_KEY, false), FILTER_VALIDATE_BOOLEAN)
            && self::message() !== '';
    }

    /** @return array<string, string> */
    public static function style(): array
    {
        return self::STYLES[Setting::get(self::STYLE_KEY, self::DEFAULT_STYLE)] ?? self::STYLES[self::DEFAULT_STYLE];
    }

    /** The banner's markup for the panel's render hook, or nothing. */
    public static function render(): string
    {
        if (! auth()->check() || ! self::active()) {
            return '';
        }

        return view(self::VIEW, [
            'message' => self::message(),
            'style' => self::style(),
            // What "closed for today" is remembered against: a changed notice
            // is a new one, and shows again.
            'id' => substr(md5(self::message() . '|' . Setting::get(self::STYLE_KEY, self::DEFAULT_STYLE)), 0, 12),
        ])->render();
    }
}
