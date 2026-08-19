import '../../../../../../js/bootstrap';

// Alpine is provided and started by Livewire (via @livewireScripts). Booting a
// second instance here fights Livewire's own and breaks wire:model, which is
// what drives the instant search.

/* ───────────────────────────────────────────────────────────────────────────
 * Appearance
 * ───────────────────────────────────────────────────────────────────────────
 * Mirrors App\Helpers\Appearance::preloadScript() exactly:
 *   stored visitor choice → admin default → OS preference (when "system")
 *
 * The reason this cannot just live in <head>: wire:navigate swaps the DOM
 * instead of reloading, so the <head> preload never runs again and the `dark`
 * class silently vanishes on the second page a visitor opens. Re-stamping
 * before the swap (livewire:navigate) avoids a flash of the wrong theme;
 * re-stamping after it (livewire:navigated) also re-binds the toggle, which is
 * a new element by then.
 * ------------------------------------------------------------------------- */
(function () {
    var STORAGE_KEY = 'appearance-mode';

    function resolveMode() {
        var stored = null;
        try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (stored === 'light' || stored === 'dark') return stored;

        var adminDefault = window.__APPEARANCE_DEFAULT__ || 'system';
        if (adminDefault === 'dark') return 'dark';
        if (adminDefault === 'light') return 'light';

        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark'
            : 'light';
    }

    function apply(mode) {
        var isDark = mode === 'dark';
        var root = document.documentElement;
        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
    }

    function sync() { apply(resolveMode()); }

    function toggle() {
        var next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
        apply(next);
    }

    function bindToggle() {
        var btn = document.getElementById('appearance-toggle');
        if (!btn) return;
        btn.removeEventListener('click', toggle);
        btn.addEventListener('click', toggle);
    }

    document.addEventListener('livewire:navigate', sync);
    document.addEventListener('livewire:navigated', function () { sync(); bindToggle(); });

    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        if (mq.addEventListener) mq.addEventListener('change', sync);
        else if (mq.addListener) mq.addListener(sync);
    }

    sync();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindToggle);
    } else {
        bindToggle();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * The profile's section index
 * ───────────────────────────────────────────────────────────────────────────
 * A profile here is one document rather than nine tabs, so something has to say
 * where in it you are. An IntersectionObserver marks the section currently
 * crossing the reading line and both the list (wide) and the strip (narrow)
 * follow it.
 *
 * Decoration over links that already work: with JavaScript off, every entry is
 * still an anchor to a section already on the page.
 *
 * Re-armed on livewire:navigated because the observer holds references to
 * elements that the page swap has thrown away.
 * ------------------------------------------------------------------------- */
(function () {
    var observer = null;

    /*
     * Scroll the narrow-screen strip so the active entry sits in the middle.
     *
     * The strip scrolls sideways and about four entries fit on a phone, so the
     * active one is usually off to one side — which shows you four things you
     * are not reading and hides the one you are, with no clue which direction
     * the rest lie in. Centring answers both.
     */
    function centre(link) {
        var strip = link.closest('.section-strip');
        if (!strip || strip.scrollWidth <= strip.clientWidth) return;

        var target = link.offsetLeft - (strip.clientWidth - link.offsetWidth) / 2;
        var max = strip.scrollWidth - strip.clientWidth;

        strip.scrollTo({
            left: Math.max(0, Math.min(target, max)),
            behavior: 'smooth'
        });
    }

    function mark(id) {
        var links = document.querySelectorAll('[data-section-link]');
        var centred = false;

        links.forEach(function (link) {
            var active = link.getAttribute('data-section-link') === id;

            link.classList.toggle('is-active', active);

            // Only the strip's copy is scrolled into view, and only once: the
            // same id appears in both the list and the strip.
            if (active && !centred && link.closest('.section-strip')) {
                centred = true;
                centre(link);
            }
        });
    }

    function arm() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }

        var sections = document.querySelectorAll('.doc-section[id]');
        if (!sections.length || !window.IntersectionObserver) return;

        /*
         * The reading line sits a third of the way down the window rather than
         * at its centre. A section's heading is at its top, and a band centred
         * in the viewport marks the section you have half finished instead of
         * the one you have just arrived at.
         */
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) mark(entry.target.id);
            });
        }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

        sections.forEach(function (section) { observer.observe(section); });
    }

    document.addEventListener('livewire:navigated', arm);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arm);
    } else {
        arm();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * Keeping the reading position while the ledger changes under it
 * ───────────────────────────────────────────────────────────────────────────
 * Typing in the finder replaces the rows below it. When the finder is parked
 * against the masthead and the new result set is shorter than the old one, the
 * page can no longer be scrolled as far — so the browser clamps, the finder
 * unsticks, and the field the visitor is typing into slides up the screen.
 *
 * The fix is the same one the other sticky-bar themes use: if the bar was
 * parked before the change, put the scroll back where it needs to be for it to
 * still be parked afterwards.
 *
 * A sticky element reports its stuck position rather than its real one, so the
 * measurement is taken from [data-finder-anchor] — an empty marker sitting in
 * normal flow just above it.
 * ------------------------------------------------------------------------- */
(function () {
    // Re-queried each time: both live inside a Livewire component and are
    // replaced wholesale on every morph.
    function anchor() { return document.querySelector('[data-finder-anchor]'); }
    function finder() { return document.querySelector('.finder'); }

    function headerHeight() {
        var masthead = document.querySelector('.masthead');
        return masthead ? masthead.offsetHeight : 0;
    }

    /*
     * Below the small breakpoint the finder is not sticky at all — it scrolls
     * away with the page, because parking it would hold a quarter of a phone
     * screen for a control already used. Asked of the computed style rather
     * than re-declared as a matchMedia query here, so the breakpoint lives in
     * exactly one place and the two cannot drift apart.
     */
    function sticky() {
        var el = finder();
        if (!el) return false;

        return window.getComputedStyle(el).position === 'sticky';
    }

    var parked = false;

    function sync() {
        var mark = anchor();

        if (!mark || !sticky()) { parked = false; return; }

        parked = mark.getBoundingClientRect().top <= headerHeight() + 1;
    }

    function park() {
        var mark = anchor();
        if (!mark) return;

        var target = mark.getBoundingClientRect().top + window.scrollY - headerHeight();
        var max = document.documentElement.scrollHeight - window.innerHeight;

        /*
         * 'instant', not 'auto': the page sets `scroll-behavior: smooth` for
         * anchor links, and 'auto' would inherit it — turning a correction
         * nobody should notice into a visible glide.
         */
        window.scrollTo({ top: Math.max(0, Math.min(target, max)), behavior: 'instant' });
    }

    window.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync, { passive: true });

    /*
     * Livewire v4 dispatches no DOM event for morphing — only init, navigate
     * and navigated — so this has to be the JS hook.
     *
     * Registering it is a race. This file is loaded by @vite as a module, which
     * defers; Livewire's own script is a classic one at the end of <body> and
     * runs during parsing, so by the time this executes `livewire:init` has
     * usually already been and gone. Listening for it alone would silently
     * never fire. So: register now if Livewire is up, and keep the listeners as
     * the fallback for the other ordering.
     */
    var hooked = false;

    function registerHook() {
        if (hooked || !window.Livewire || typeof Livewire.hook !== 'function') return;

        hooked = true;

        Livewire.hook('morphed', function () {
            // Next frame, so layout has settled and scrollHeight is the new one
            // rather than the one being corrected for.
            requestAnimationFrame(function () {
                if (parked) park();
                sync();
            });
        });
    }

    registerHook();
    document.addEventListener('livewire:init', registerHook);
    document.addEventListener('livewire:initialized', registerHook);
    document.addEventListener('livewire:navigated', function () { registerHook(); sync(); });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sync);
    } else {
        sync();
    }
})();
