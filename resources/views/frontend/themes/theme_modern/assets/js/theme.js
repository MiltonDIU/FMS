import '../../../../../../js/bootstrap';

// Alpine is provided and started by Livewire v4 (via @livewireScripts).
// Starting a second Alpine instance here conflicts with Livewire's bundled
// Alpine and breaks wire:model binding (the instant search).

// ─── Dark mode toggle ───────────────────────────────────────────────
// Single source of truth mirrors the Appearance preload script:
//   stored visitor choice  ->  admin default  ->  OS preference (when "system")
// Keeps <html> in sync across Livewire wire:navigate morphs and handles
// the header toggle.
(function () {
    var STORAGE_KEY = 'appearance-mode';

    function resolveMode() {
        var stored = null;
        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch (e) {}
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
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

    sync();
})();

// ─── Sticky offset: expose the sticky header's height as --header-h ──────
// The header is `sticky top-0`, so page-level sticky elements (profile
// sidebar + tab bar) must offset by the header height, otherwise they slide
// underneath it. We measure it live and keep it fresh on resize/navigation.
(function () {
    function updateHeaderHeight() {
        var header = document.querySelector('header.sticky, header.glass-header');
        var h = header ? Math.round(header.getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--header-h', h + 'px');
    }

    updateHeaderHeight();

    window.addEventListener('resize', updateHeaderHeight);
    window.addEventListener('load', updateHeaderHeight);
    document.addEventListener('livewire:navigated', updateHeaderHeight);
    document.addEventListener('livewire:load', updateHeaderHeight);

    // Fonts/images can change the header height after first paint.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(updateHeaderHeight);
    }
})();
