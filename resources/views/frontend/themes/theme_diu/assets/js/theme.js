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

/* The site header: the first child of <body>, not any <header> on the page —
   an article can have a header of its own. */
function siteHeader() { return document.querySelector('body > header'); }

/* ───────────────────────────────────────────────────────────────────────────
 * The header's height, as a CSS variable
 * ───────────────────────────────────────────────────────────────────────────
 * Everything that parks under the header needs to know where it ends, and this
 * header is three bands that wrap differently at every width — no stylesheet
 * number is right everywhere. So it is measured, and measured again whenever
 * it changes size: a rotation, a font swapping in, a wrapped statistics strip.
 * ------------------------------------------------------------------------- */
(function () {
    var observer = null;

    function measure() {
        var header = siteHeader();
        if (!header) return;

        document.documentElement.style.setProperty('--header-h', header.offsetHeight + 'px');
    }

    function watch() {
        measure();

        if (observer) observer.disconnect();

        var header = siteHeader();

        if (header && window.ResizeObserver) {
            observer = new ResizeObserver(measure);
            observer.observe(header);
        }
    }

    window.addEventListener('resize', measure, { passive: true });
    document.addEventListener('livewire:navigated', watch);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watch);
    } else {
        watch();
    }
})();

/**
 * Scroll a horizontal strip so that one of its children sits in the middle.
 *
 * The profile's section strip scrolls sideways, and on a phone only a few
 * entries fit — so the active one is usually off to one side, and the strip
 * shows what you are not reading while hiding what you are. Centring answers
 * that, and shows what remains on either side of it.
 *
 * scrollLeft rather than scrollIntoView: that one also scrolls the page
 * vertically to reach the element on some browsers.
 */
function centreInRail(rail, child, behavior) {
    // Nothing to do when the strip does not actually scroll.
    if (!rail || !child || rail.scrollWidth <= rail.clientWidth) return;

    var target = child.offsetLeft - (rail.clientWidth - child.offsetWidth) / 2;
    var max = rail.scrollWidth - rail.clientWidth;

    rail.scrollTo({
        left: Math.max(0, Math.min(target, max)),
        behavior: behavior || 'auto',
    });
}

/* ───────────────────────────────────────────────────────────────────────────
 * The profile's section strip
 * ───────────────────────────────────────────────────────────────────────────
 * The profile is one page now rather than nine tabs, so something has to say
 * where in it you are. An IntersectionObserver marks the section currently
 * crossing the reading line and the strip follows.
 *
 * Decoration over links that already work: with JavaScript off, every entry is
 * still an anchor to a section already on the page.
 * ------------------------------------------------------------------------- */
(function () {
    var observer = null;
    var resizeObserver = null;
    var onScroll = null;

    function smooth() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth';
    }

    function teardown() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }

        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }

        if (onScroll) {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            onScroll = null;
        }
    }

    function arm() {
        teardown();

        var links = Array.prototype.slice.call(document.querySelectorAll('[data-section-link]'));
        if (!links.length || !window.IntersectionObserver) return;

        var sections = [];

        links.forEach(function (link) {
            var section = document.getElementById(link.getAttribute('data-section-link'));

            if (section && sections.indexOf(section) === -1) sections.push(section);
        });

        if (!sections.length) return;

        var current = null;

        function activate(id) {
            if (id === current) return;
            current = id;

            links.forEach(function (link) {
                var on = link.getAttribute('data-section-link') === id;
                link.classList.toggle('is-active', on);

                if (on) {
                    link.setAttribute('aria-current', 'true');
                    // Smoothly, because the section changes while you are
                    // already reading and an instant jump reads as a glitch.
                    centreInRail(link.closest('.diu-tabs'), link, smooth());
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        /*
         * Only meaningful on a page that actually scrolls. When a sparse
         * profile fits on screen whole, everything is visible, and jumping the
         * strip to the last entry would answer a question nobody asked.
         */
        function atBottom() {
            var doc = document.documentElement;

            if (doc.scrollHeight - window.innerHeight <= 4) return false;

            return window.innerHeight + window.scrollY >= doc.scrollHeight - 4;
        }

        /*
         * The reading line sits a third of the way down rather than at the
         * very top: a heading is "the section you are reading" from the moment
         * it comes comfortably into view, not the instant its first pixel
         * appears under the strip.
         */
        observer = new IntersectionObserver(function (entries) {
            // The bottom rule below owns the last stretch of the page.
            if (atBottom()) return;

            var visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (visible.length) activate(visible[0].target.id);
        }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

        sections.forEach(function (section) { observer.observe(section); });

        /*
         * Once the page has run out of scroll, the last sections can never
         * reach the reading line — a short Memberships list at the end simply
         * never crosses it. At the bottom of the document the last section is
         * the one you are reading, by definition.
         */
        onScroll = function () {
            if (atBottom()) activate(sections[sections.length - 1].id);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        /*
         * The page is a different height a second after it is parsed —
         * the portrait loads, the web font swaps in — and either can change
         * whether it scrolls at all without a scroll event ever firing.
         */
        if (window.ResizeObserver) {
            resizeObserver = new ResizeObserver(function () { onScroll(); });
            resizeObserver.observe(document.body);
        }

        activate(sections[0].id);
        onScroll();

        // Clicking an entry should win immediately rather than waiting for the
        // smooth scroll to settle under the observer.
        links.forEach(function (link) {
            link.addEventListener('click', function () {
                activate(link.getAttribute('data-section-link'));
            });
        });
    }

    document.addEventListener('livewire:navigated', arm);
    document.addEventListener('livewire:navigate', teardown);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arm);
    } else {
        arm();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * The search bar: parked or not, and staying put through a filter
 * ───────────────────────────────────────────────────────────────────────────
 * Two jobs.
 *
 * The first is cosmetic: CSS cannot ask whether a `position: sticky` element is
 * currently stuck, so `.is-stuck` comes from here. Nothing depends on it; if
 * this never runs the bar simply never gains its parked surface.
 *
 * The second is the real bug: filtering from a parked bar dropped it down the
 * page, for two unrelated reasons depending on which control was used.
 *
 *   Typing, designation and role are morphs. Fewer results means a shorter
 *   document, the browser clamps scrollY to the new maximum, and the page
 *   slides up out from under the bar.
 *
 *   Faculty and department are links carrying wire:navigate, because a faculty
 *   is an address worth sharing. That is a page swap, and Livewire returns
 *   scroll to the top as it should for a new page.
 *
 * Both are answered the same way: if the bar was parked when the interaction
 * started, put the scroll back where it needs to be for it to still be parked
 * once the new content is in place.
 *
 * A sticky element reports its stuck position rather than its real one, so the
 * measurement is taken from [data-command-anchor] — an empty marker sitting in
 * normal flow just above it.
 * ------------------------------------------------------------------------- */
(function () {
    // Re-queried each time: both live inside a Livewire component and are
    // replaced wholesale on every morph.
    function anchor() { return document.querySelector('[data-command-anchor]'); }
    function bar() { return document.querySelector('.diu-command'); }

    function headerHeight() {
        var header = siteHeader();
        return header ? header.offsetHeight : 0;
    }

    /*
     * Below the large breakpoint the bar is not sticky at all. Asked of the
     * computed style rather than re-declared as a matchMedia query, so the
     * breakpoint lives in exactly one place.
     */
    function sticky(el) {
        return !!el && window.getComputedStyle(el).position === 'sticky';
    }

    var parked = false;

    function sync() {
        var el = bar();
        var mark = anchor();

        // Folded into the bubble there is no bar to be parked.
        if (!el || !mark || !sticky(el) || el.classList.contains('is-folded')) {
            parked = false;
            if (el) el.classList.remove('is-stuck');
            return;
        }

        parked = mark.getBoundingClientRect().top <= headerHeight() + 1;
        el.classList.toggle('is-stuck', parked);
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

    // Unfolding can put the bar back onto a page already scrolled past where it
    // rests, so it has to be told whether it is parked straight away.
    document.addEventListener('diu:command-fold', sync);

    /*
     * The wire:navigate half. wire:navigate keeps the same JS context, so
     * whether the bar was parked survives the swap here. Only restored when the
     * page being arrived at has a bar too: a card is also a wire:navigate, and a
     * profile should open at the top.
     */
    var parkedBeforeNavigate = false;

    document.addEventListener('livewire:navigate', function () {
        parkedBeforeNavigate = parked;
    });

    document.addEventListener('livewire:navigated', function () {
        var restore = parkedBeforeNavigate;
        parkedBeforeNavigate = false;

        // Next frame: Livewire has finished its own scroll handling by then.
        requestAnimationFrame(function () {
            if (restore && anchor() && sticky(bar())) park();

            sync();
        });
    });

    /*
     * Livewire v4 dispatches no DOM event for morphing, so this has to be the
     * JS hook — and registering it is a race. This file is a deferred module;
     * Livewire's script is a classic one that has usually run already, so
     * `livewire:init` has often been and gone. Register now if Livewire is up,
     * and keep the listeners as the fallback for the other ordering.
     */
    var hooked = false;

    function registerHook() {
        if (hooked || !window.Livewire || typeof Livewire.hook !== 'function') return;

        hooked = true;

        Livewire.hook('morphed', function () {
            // Next frame, so scrollHeight is the new one.
            requestAnimationFrame(function () {
                if (parked && anchor()) park();
                sync();
            });
        });
    }

    registerHook();
    document.addEventListener('livewire:init', registerHook);
    document.addEventListener('livewire:initialized', registerHook);
    document.addEventListener('livewire:navigated', registerHook);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sync);
    } else {
        sync();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * Folding the search bar into a bubble you can put where you like
 * ───────────────────────────────────────────────────────────────────────────
 * A reader who has already found the right list still pays for the bar on
 * every screen of cards after that. So the fold button puts it away into a
 * bubble, the bubble is dragged wherever the reader's thumb or pointer lives,
 * and a tap opens the bar again exactly as it was.
 *
 *   The bubble is built here rather than in Blade because it must outlive the
 *   component: faculty and department links are wire:navigate, which replace
 *   the whole component. It lives on <body>, rebuilt from sessionStorage after
 *   each navigation — so folded stays folded, and stays where you left it.
 *
 *   Folding removes the bar from the flow, which drags everything below it up
 *   the page. Both directions are measured and corrected.
 *
 *   A drag and a tap start identically. The press only becomes a drag once it
 *   has travelled far enough to mean it, and the click that follows a real
 *   drag is swallowed — otherwise letting go of the bubble would open the bar.
 * ------------------------------------------------------------------------- */
(function () {
    /* Kept between the bubble and the edge of the window. */
    var EDGE = 16;

    /* How far a press travels before it is a drag rather than a tap. */
    var SLOP = 6;

    /* Matches .command-bubble in theme.css; used only before it can be
       measured. */
    var SIZE = 52;

    var FOLD_KEY = 'diu-command-folded';
    var POS_KEY = 'diu-command-bubble';

    var bubble = null;
    var pos = null;
    var drag = null;
    var swallowClick = false;

    function bar() { return document.querySelector('.diu-command'); }

    function store(key, value) {
        try { sessionStorage.setItem(key, value); } catch (e) {}
    }

    function recall(key) {
        try { return sessionStorage.getItem(key); } catch (e) { return null; }
    }

    /* How you are reading now, not a setting: remembered for the session. */
    var folded = recall(FOLD_KEY) === 'yes';

    function size() {
        return bubble && bubble.offsetWidth ? bubble.offsetWidth : SIZE;
    }

    /* Always inside the window, so a rotation cannot strand it off-screen. */
    function clamp(point) {
        var s = size();

        return {
            x: Math.max(EDGE, Math.min(point.x, window.innerWidth - s - EDGE)),
            y: Math.max(EDGE, Math.min(point.y, window.innerHeight - s - EDGE))
        };
    }

    /* Bottom right, lifted clear of a phone browser's own bottom bar. */
    function restingPlace() {
        var s = size();

        return {
            x: window.innerWidth - s - EDGE,
            y: window.innerHeight - s - EDGE * 5
        };
    }

    function place(point) {
        pos = clamp(point);

        if (!bubble) return;

        bubble.style.left = pos.x + 'px';
        bubble.style.top = pos.y + 'px';
    }

    function recallPlace() {
        var raw = recall(POS_KEY);
        if (!raw) return null;

        try {
            var point = JSON.parse(raw);

            return (typeof point.x === 'number' && typeof point.y === 'number') ? point : null;
        } catch (e) {
            return null;
        }
    }

    /* Let go and it settles against the nearer side, so it ends up somewhere
       the reader chose rather than over a card. */
    function settle() {
        if (!bubble || !pos) return;

        var s = size();
        var nearerLeft = pos.x + s / 2 < window.innerWidth / 2;

        bubble.classList.add('is-settling');
        place({ x: nearerLeft ? EDGE : window.innerWidth - s - EDGE, y: pos.y });
        store(POS_KEY, JSON.stringify(pos));

        window.setTimeout(function () {
            if (bubble) bubble.classList.remove('is-settling');
        }, 220);
    }

    function onDown(event) {
        if (event.button && event.button !== 0) return;

        swallowClick = false;

        drag = {
            id: event.pointerId,
            offsetX: event.clientX - pos.x,
            offsetY: event.clientY - pos.y,
            moved: false
        };

        // Capture, so a finger that outruns the bubble keeps moving it.
        try { bubble.setPointerCapture(event.pointerId); } catch (e) {}
    }

    function onMove(event) {
        if (!drag || event.pointerId !== drag.id) return;

        var next = { x: event.clientX - drag.offsetX, y: event.clientY - drag.offsetY };

        if (!drag.moved) {
            if (Math.abs(next.x - pos.x) < SLOP && Math.abs(next.y - pos.y) < SLOP) return;

            drag.moved = true;
            bubble.classList.add('is-dragging');
        }

        place(next);
    }

    function onUp(event) {
        if (!drag || event.pointerId !== drag.id) return;

        var moved = drag.moved;
        drag = null;

        try { bubble.releasePointerCapture(event.pointerId); } catch (e) {}
        bubble.classList.remove('is-dragging');

        if (!moved) return;

        // The click that ends a drag would otherwise open the bar.
        swallowClick = true;
        settle();
    }

    function onClick() {
        if (swallowClick) {
            swallowClick = false;
            return;
        }

        unfold();
    }

    /*
     * What the bubble offers is whatever the bar it replaced held: a
     * department's contacts view is only navigated, not searched. The fold
     * button says which — data-command-glyph and data-command-restore — and the
     * bubble wears it. Nothing set means the search bar.
     */
    var GLYPHS = {
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        nav: '<path d="M4 6h16M4 12h16M4 18h16"/>'
    };

    function trigger() { return document.querySelector('[data-command-fold]'); }

    function dress(el) {
        var button = trigger();
        var label = (button && button.getAttribute('data-command-restore')) || 'Show search and filters';
        var glyph = GLYPHS[button && button.getAttribute('data-command-glyph')] || GLYPHS.search;

        el.setAttribute('aria-label', label);
        el.title = label;

        el.innerHTML =
            '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            glyph + '</svg>';
    }

    function build() {
        var el = document.createElement('button');

        el.type = 'button';
        el.className = 'command-bubble';

        el.addEventListener('pointerdown', onDown);
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerup', onUp);
        el.addEventListener('pointercancel', onUp);
        el.addEventListener('click', onClick);

        document.body.appendChild(el);

        return el;
    }

    function remove() {
        if (!bubble) return;

        bubble.remove();
        bubble = null;
        drag = null;
    }

    /*
     * Hiding or restoring the bar changes the height of everything above the
     * results, so they slide under a scroll position that has not changed.
     * Measure the results either side of the change and put the difference
     * back — except at the very top, where unfolding should simply push the
     * cards down and leave the reader looking at the heading.
     */
    function withoutMovingThePage(change) {
        var mark = document.getElementById('results');
        var before = (mark && window.scrollY > 1) ? mark.getBoundingClientRect().top : null;

        change();

        if (before === null) return;

        var shift = mark.getBoundingClientRect().top - before;

        if (shift) window.scrollBy({ top: shift, behavior: 'instant' });
    }

    function apply(moveFocus) {
        var el = bar();

        // After a wire:navigate the old bubble went with the old body.
        if (bubble && !bubble.isConnected) bubble = null;

        // Profiles and other pages have no bar to fold.
        if (!el) {
            remove();
            return;
        }

        if (folded) {
            if (!bubble) bubble = build();

            dress(bubble);
            place(pos || recallPlace() || restingPlace());
        } else {
            remove();
        }

        withoutMovingThePage(function () {
            el.classList.toggle('is-folded', folded);
        });

        // Focus was on a control that has just been hidden; give a keyboard
        // reader somewhere to tab from. preventScroll so it cannot undo the
        // correction above.
        if (moveFocus) {
            var next = folded ? bubble : el.querySelector('[data-command-fold]');

            if (next) next.focus({ preventScroll: true });
        }

        document.dispatchEvent(new CustomEvent('diu:command-fold'));
    }

    function fold() {
        if (folded) return;

        folded = true;
        store(FOLD_KEY, 'yes');
        apply(true);
    }

    function unfold() {
        if (!folded) return;

        folded = false;
        store(FOLD_KEY, 'no');
        apply(true);
    }

    // Delegated: the button is replaced on every morph and every navigation.
    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-command-fold]') : null;

        if (button) fold();
    });

    window.addEventListener('resize', function () {
        apply(false);

        if (pos) place(pos);
    }, { passive: true });

    document.addEventListener('livewire:navigated', function () { apply(false); });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { apply(false); });
    } else {
        apply(false);
    }
})();
