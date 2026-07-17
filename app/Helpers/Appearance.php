<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Resolves the frontend light / dark / system appearance.
 *
 * The admin configures a default mode (light, dark, or system) via the
 * `appearance_mode` setting. Visitors may override it with the header toggle,
 * which persists their choice in localStorage under `appearance-mode`.
 *
 * `htmlClass()` returns the static class for the <html> element based on the
 * admin default, while `preloadScript()` injects a tiny blocking script in the
 * <head> that applies the visitor's stored choice (or system preference) before
 * first paint to avoid a flash of the wrong theme.
 */
class Appearance
{
    public const SETTING_KEY = 'appearance_mode';

    public const DEFAULT = 'light';

    /**
     * Valid appearance modes.
     */
    public const MODES = ['light', 'dark', 'system'];

    /**
     * localStorage key used by the frontend toggle to remember the visitor's
     * explicit choice.
     */
    public const STORAGE_KEY = 'appearance-mode';

    /**
     * Resolve the admin-configured default mode.
     */
    public static function defaultMode(): string
    {
        $mode = (string) Setting::get(self::SETTING_KEY, self::DEFAULT);

        return in_array($mode, self::MODES, true) ? $mode : self::DEFAULT;
    }

    /**
     * Static class for the <html> element based on the admin default.
     *
     * For `system` the actual mode is decided at runtime in the browser, so no
     * static class is emitted here (the preload script handles it).
     */
    public static function htmlClass(): string
    {
        $mode = self::defaultMode();

        if ($mode === 'dark') {
            return 'dark';
        }

        return '';
    }

    /**
     * Blocking pre-paint script that applies the visitor's choice (or system
     * preference when following the OS) before the page renders.
     *
     * Precedence: stored visitor choice -> admin default (when not "system").
     */
    public static function preloadScript(): string
    {
        $default = self::defaultMode();

        $js = <<<'JS'
(function () {
    var storageKey = 'appearance-mode';
    var stored = null;
    try { stored = localStorage.getItem(storageKey); } catch (e) {}
    var mode = stored;
    if (mode !== 'light' && mode !== 'dark') {
        mode = '__DEFAULT__';
    }
    var isDark;
    if (mode === 'light') {
        isDark = false;
    } else if (mode === 'dark') {
        isDark = true;
    } else {
        isDark = window.matchMedia
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : false;
    }
    var root = document.documentElement;
    root.classList.toggle('dark', isDark);
    root.style.colorScheme = isDark ? 'dark' : 'light';
})();
JS;

        return str_replace('__DEFAULT__', $default, $js);
    }
}
