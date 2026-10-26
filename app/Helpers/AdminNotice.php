<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * A notice across the top of the admin panel, for everyone signed in.
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

    /** Banner colours: background, border and text, readable in light and dark. */
    public const STYLES = [
        'info' => ['label' => 'Information (blue)', 'bg' => 'rgba(59,130,246,0.12)', 'border' => 'rgba(59,130,246,0.45)', 'text' => '#2563eb'],
        'warning' => ['label' => 'Warning (amber)', 'bg' => 'rgba(245,158,11,0.14)', 'border' => 'rgba(245,158,11,0.5)', 'text' => '#b45309'],
        'danger' => ['label' => 'Important (red)', 'bg' => 'rgba(239,68,68,0.12)', 'border' => 'rgba(239,68,68,0.45)', 'text' => '#dc2626'],
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

    /** @return array{label: string, bg: string, border: string, text: string} */
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
        ])->render();
    }
}
