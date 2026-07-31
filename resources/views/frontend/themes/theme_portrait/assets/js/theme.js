import '../../../../../../js/bootstrap';

// Alpine is provided and started by Livewire v4 (via @livewireScripts).
// Starting a second Alpine instance here conflicts with Livewire's bundled
// Alpine and breaks wire:model binding (the instant search).

// ─── Dark mode toggle ──────────────────────────────────────────────────────
// Single source of truth mirrors the Appearance preload script:
//   stored visitor choice → admin default → OS preference (when "system")
//
// Problem: Livewire wire:navigate does a partial DOM swap (morph) instead of
// a full page reload. The <head> preload script that stamps `dark` on <html>
// does NOT re-run, so after navigation the class silently disappears.
//
// Fix strategy:
//  1. Listen to `livewire:navigate`   — fires BEFORE the swap; stamp the class
//     early so there is no flash-of-wrong-theme during the transition.
//  2. Listen to `livewire:navigated`  — fires AFTER the swap; re-stamp and
//     also re-attach the toggle button listener (new DOM, new button element).
//  3. Listen to `livewire:morph`      — fires after every individual morph
//     cycle inside a page; ensures incremental morphs don't strip the class.
// ─────────────────────────────────────────────────────────────────────────────
(function () {
    var STORAGE_KEY = 'appearance-mode';

    // Resolves the mode to apply, exactly like Appearance::preloadScript().
    function resolveMode() {
        var stored = null;
        try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (stored === 'light' || stored === 'dark') return stored;
        var adminDefault = window.__APPEARANCE_DEFAULT__ || 'system';
        if (adminDefault === 'dark') return 'dark';
        if (adminDefault === 'light') return 'light';
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark' : 'light';
    }

    function apply(mode) {
        var isDark = mode === 'dark';
        var root   = document.documentElement;
        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
    }

    function sync() { apply(resolveMode()); }

    function toggle() {
        var current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        var next    = current === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
        apply(next);
    }

    // Attach the toggle button listener. Called once on boot and again after
    // every navigation so the freshly-morphed button element is wired up.
    function bindToggleButton() {
        var btn = document.getElementById('appearance-toggle');
        if (!btn) return;
        // Remove any stale listener before adding, to prevent duplicates.
        btn.removeEventListener('click', toggle);
        btn.addEventListener('click', toggle);
    }

    // ── Event hooks ───────────────────────────────────────────────────────
    // Before Livewire starts swapping the DOM — prevents FOUT.
    document.addEventListener('livewire:navigate',   sync);
    // After the new page is fully rendered.
    document.addEventListener('livewire:navigated',  function () { sync(); bindToggleButton(); });
    // After each incremental morph cycle within a page.
    document.addEventListener('livewire:morph',      sync);
    // Legacy / full-page Livewire load.
    document.addEventListener('livewire:load',       function () { sync(); bindToggleButton(); });

    // OS-level preference change (e.g. user switches system dark mode).
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var osHandler = function () { sync(); };
        if (mq.addEventListener)  mq.addEventListener('change', osHandler);
        else if (mq.addListener)  mq.addListener(osHandler);
    }

    // ── Initial boot ──────────────────────────────────────────────────────
    sync();
    // Wait for DOM ready to bind the button (script may run before body).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindToggleButton);
    } else {
        bindToggleButton();
    }
})();
