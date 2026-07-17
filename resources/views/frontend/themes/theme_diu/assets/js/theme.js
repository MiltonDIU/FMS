import '../../../../../../js/bootstrap';

// Alpine is provided and started by Livewire v4 (via @livewireScripts).
// Starting a second Alpine instance here conflicts with Livewire's bundled
// Alpine and breaks wire:model binding (the instant search).

// ─── Dark mode toggle ───────────────────────────────────────────────
// Single source of truth is localStorage["appearance-mode"].
// The Appearance preload script already applies it before paint on a full
// page load; this module keeps it in sync across Livewire wire:navigate
// morphs (which do NOT re-run the preload) and handles the header toggle.
(function () {
    var STORAGE_KEY = 'appearance-mode';

    function storedMode() {
        var v = null;
        try {
            v = localStorage.getItem(STORAGE_KEY);
        } catch (e) {}
        return (v === 'light' || v === 'dark') ? v : null;
    }

    function apply(mode) {
        var isDark = mode === 'dark';
        var root = document.documentElement;
        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
    }

    // Keep the visual mode consistent with the stored choice. Falls back to the
    // class already on <html> (set by the preload script) when nothing stored.
    function syncFromStorage() {
        var mode = storedMode();
        if (! mode) {
            return;
        }
        apply(mode);
    }

    function toggle() {
        var current = storedMode();
        if (! current) {
            current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        }
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
    // reload, so re-apply the stored mode after each navigation to avoid the
    // page drifting into the wrong light/dark state.
    document.addEventListener('livewire:navigated', syncFromStorage);
    document.addEventListener('livewire:load', syncFromStorage);

    // Initial sync in case the preload script was skipped/overwritten.
    syncFromStorage();
})();
