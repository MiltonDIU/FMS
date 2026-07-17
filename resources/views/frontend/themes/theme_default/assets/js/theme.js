import '../../../../../../js/bootstrap';

// Alpine is provided and started by Livewire v4 (via @livewireScripts).
// Starting a second Alpine instance here conflicts with Livewire's bundled
// Alpine and breaks wire:model binding (the instant search).

// ─── Dark mode toggle ─────────────────────────────────────────────
// Single source of truth mirrors the Appearance preload script:
//   stored visitor choice  ->  admin default  ->  OS preference (when "system")
// This module keeps <html> in sync across Livewire wire:navigate morphs
// (which do NOT re-run the preload) and handles the header toggle.
(function () {
    var STORAGE_KEY = 'appearance-mode';

    // Resolves the mode to apply, exactly like Appearance::preloadScript().
    function resolveMode() {
        var stored = null;
        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch (e) {}
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        // No stored choice: follow the OS preference (mirrors admin "system" mode).
        var prefersDark = window.matchMedia
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : false;
        return prefersDark ? 'dark' : 'light';
    }

    function apply(mode) {
        var isDark = mode === 'dark';
        var root = document.documentElement;
        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
    }

    function sync() {
        apply(resolveMode());
    }

    function toggle() {
        var current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {}
        apply(next);
    }

    var btn = document.getElementById('appearance-toggle');
    if (btn) {
        btn.addEventListener('click', toggle);
    }

    // Livewire SPA navigation (wire:navigate) morphs the DOM without a full
    // reload, so re-apply the resolved mode after each navigation. This is what
    // prevents the mode from getting stuck between pages (a refresh "fixes" it
    // only because the preload script re-runs on a full load).
    document.addEventListener('livewire:navigated', sync);
    document.addEventListener('livewire:load', sync);
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var handler = function () { sync(); };
        if (mq.addEventListener) {
            mq.addEventListener('change', handler);
        } else if (mq.addListener) {
            mq.addListener(handler);
        }
    }

    // Initial sync in case the preload script was skipped/overwritten.
    sync();
})();
