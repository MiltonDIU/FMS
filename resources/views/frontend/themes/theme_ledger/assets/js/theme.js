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

/**
 * Scroll a horizontal rail so that one of its children sits in the middle.
 *
 * Every sideways-scrolling strip in this theme — the profile's section strip
 * and the faculty and department indexes — has the same problem on a phone:
 * only a few entries fit, so the active one is usually off to one side. The
 * strip then shows what you are not looking at and hides what you are, with
 * no clue which direction the rest lie in. Centring answers both.
 *
 * Written once and shared, because two copies of a scroll calculation drift
 * the first time either is touched.
 *
 * scrollLeft rather than scrollIntoView: that one also scrolls the page
 * vertically to reach the element on some browsers, taking the page away from
 * the reader.
 */
function centreInRail(rail, child, behavior) {
    // Nothing to do when the rail is not actually scrollable, which includes
    // the case where it is display:none at this breakpoint and measures zero.
    if (!rail || !child || rail.scrollWidth <= rail.clientWidth) return;

    var target = child.offsetLeft - (rail.clientWidth - child.offsetWidth) / 2;
    var max = rail.scrollWidth - rail.clientWidth;

    rail.scrollTo({
        left: Math.max(0, Math.min(target, max)),
        behavior: behavior || 'auto',
    });
}

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
 * Torn down on livewire:navigate and re-armed on livewire:navigated, because
 * the observers hold references to elements the page swap throws away.
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

        // The same id appears in both the list and the strip, so the sections
        // are de-duplicated as they are looked up.
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

                // Null for the list's copy, which does not scroll sideways.
                // Smoothly, because the section changes while you are already
                // reading and an instant jump would register as a glitch.
                if (on) centreInRail(link.closest('.section-strip'), link, smooth());
            });
        }

        /*
         * Only meaningful on a page that actually scrolls. When a sparse
         * profile fits on screen whole, nothing is "the section you are
         * reading" — everything is visible — and jumping the index to the last
         * entry would be answering a question nobody asked.
         */
        function atBottom() {
            var doc = document.documentElement;

            if (doc.scrollHeight - window.innerHeight <= 4) return false;

            return window.innerHeight + window.scrollY >= doc.scrollHeight - 4;
        }

        /*
         * The reading line sits near the top of the window rather than at its
         * centre. A section's heading is at its top, and a band centred in the
         * viewport marks the section you have half finished instead of the one
         * you have just arrived at.
         */
        observer = new IntersectionObserver(function (entries) {
            // The bottom rule below owns the last stretch of the page.
            if (atBottom()) return;

            var visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (visible.length) activate(visible[0].target.id);
        }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

        sections.forEach(function (section) { observer.observe(section); });

        /*
         * Once the page has run out of scroll, the last sections can never
         * reach the reading line — a short Memberships list at the end simply
         * never crosses it, so the index stayed pointing at whatever was above.
         * At the bottom of the document the last section is the one you are
         * reading, by definition.
         */
        onScroll = function () {
            if (atBottom()) activate(sections[sections.length - 1].id);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        /*
         * The page is a different height a second after it is parsed —
         * portraits load, the web font swaps in. Either can turn a scrollable
         * page into one that fits, or the other way round, without a scroll
         * event ever firing. Watching the body is the only thing that catches
         * all of them.
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
 * The faculty and department indexes: keep the chosen entry in view
 * ───────────────────────────────────────────────────────────────────────────
 * On a phone only a few faculties fit across the index, so on any faculty past
 * the first few the chosen one sits off-screen.
 *
 * Deliberately not re-run on every Livewire morph. The active entry in these
 * indexes only changes by navigating — faculty and department are links —
 * while morphs happen on every keystroke in the search field. Re-centring on
 * those would snatch back an index the reader had just scrolled by hand.
 *
 * Instant, not smooth: this runs as a page arrives, and an index visibly
 * sliding on arrival reads as the page still loading rather than as an answer.
 * ------------------------------------------------------------------------- */
(function () {
    function centreActive() {
        var rails = document.querySelectorAll('.index-scroll');

        Array.prototype.forEach.call(rails, function (rail) {
            var active = rail.querySelector('.index-link.is-active');

            if (active) centreInRail(rail, active, 'auto');
        });
    }

    document.addEventListener('livewire:navigated', centreActive);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', centreActive);
    } else {
        centreActive();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * The finder: parked or not, and staying put through a filter
 * ───────────────────────────────────────────────────────────────────────────
 * Two jobs.
 *
 * The first is cosmetic: CSS cannot ask whether a `position: sticky` element is
 * currently stuck, so `.is-stuck` comes from here. Nothing depends on it; if
 * this never runs the finder simply never draws its closing rule.
 *
 * The second is the real bug: filtering from a parked finder dropped it down
 * the page. Nothing was ever wrong with the finder — the ground moved under it,
 * for two unrelated reasons depending on which control was used.
 *
 *   Typing, designation and role are morphs. Fewer rows means a shorter
 *   document, the browser clamps scrollY to the new maximum, and the page
 *   slides up out from under the finder.
 *
 *   Faculty and department are links carrying wire:navigate, because a faculty
 *   is an address worth sharing. That is a page swap, and Livewire returns
 *   scroll to the top as it should for a new page.
 *
 * Both are answered the same way: if the finder was parked when the
 * interaction started, put the scroll back where it needs to be for it to
 * still be parked once the new content is in place.
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
     * away with the page. Asked of the computed style rather than re-declared
     * as a matchMedia query here, so that breakpoint lives in exactly one place
     * and the two cannot drift apart.
     */
    function sticky(el) {
        return !!el && window.getComputedStyle(el).position === 'sticky';
    }

    /* The breakpoint the compaction rules in theme.css use. */
    function narrow() {
        return window.matchMedia
            ? window.matchMedia('(max-width: 63.99rem)').matches
            : false;
    }

    /*
     * Shut the refine drawer as the finder parks itself, on a screen too small
     * to carry both it and the rows.
     *
     * It cannot be hidden from CSS, because x-show writes an inline style and
     * inline beats a class. So it is closed at the source instead — through a
     * method on the component, which knows not to record an automatic collapse
     * as the reader's own preference.
     */
    function collapseDrawer(el) {
        if (!window.Alpine || typeof Alpine.$data !== 'function') return;

        var data = Alpine.$data(el);

        if (data && typeof data.collapse === 'function') data.collapse();
    }

    var parked = false;

    function sync() {
        var el = finder();
        var mark = anchor();

        /*
         * Folded into the marker there is no finder to be parked, and "parked"
         * would read true from the anchor alone the moment the reader scrolls.
         * Bow out, and let the fold module's event bring us back.
         */
        if (!el || !mark || !sticky(el) || el.classList.contains('is-folded')) {
            parked = false;
            if (el) el.classList.remove('is-stuck');
            return;
        }

        var stuck = mark.getBoundingClientRect().top <= headerHeight() + 1;

        /*
         * Only on the way in. Doing it whenever the finder is parked would
         * fight anyone who taps Refine while parked — the drawer would shut
         * again on their next scroll, and the button would look broken.
         */
        if (stuck && !parked && narrow()) collapseDrawer(el);

        parked = stuck;
        el.classList.toggle('is-stuck', stuck);
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

    // Unfolding puts a finder back on a page that may already be scrolled past
    // where it would rest, so it has to be told whether it is parked before the
    // next scroll event rather than after it.
    document.addEventListener('ledger:finder-fold', sync);

    /*
     * The wire:navigate half of the problem. wire:navigate keeps the same JS
     * context, so whether the finder was parked survives the swap here.
     *
     * Only restored when the page being arrived at actually has a finder.
     * Clicking someone's row is also a wire:navigate, and a profile should
     * open at the top of the profile — not part-way down it.
     */
    var parkedBeforeNavigate = false;

    document.addEventListener('livewire:navigate', function () {
        parkedBeforeNavigate = parked;
    });

    document.addEventListener('livewire:navigated', function () {
        var restore = parkedBeforeNavigate;
        parkedBeforeNavigate = false;

        // Next frame: Livewire has finished its own scroll handling by then,
        // and the new page has been laid out.
        requestAnimationFrame(function () {
            if (restore && anchor() && sticky(finder())) park();

            sync();
        });
    });

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
 * Folding the finder into a marker you can put where you like
 * ───────────────────────────────────────────────────────────────────────────
 * A reader who has already found the right list still pays for the finder on
 * every screen of rows after that. So the minimise button folds it away into a
 * marker, the marker is dragged wherever the reader's thumb or pointer actually
 * lives, and a tap opens the finder again exactly as it was.
 *
 * Three things this owns that are worth knowing about.
 *
 *   The marker is built here rather than in Blade because it must outlive the
 *   component. Faculty and department entries are wire:navigate links, which
 *   replace the whole component; a marker rendered inside it would go with
 *   them. It lives on <body>, and is rebuilt from sessionStorage after each
 *   navigation — so folded stays folded, and stays where you left it.
 *
 *   Folding removes the finder from the flow, which drags everything below it
 *   up the page. Both directions are measured and corrected, so putting it
 *   away never scrolls the reader off the row they were looking at.
 *
 *   A drag and a tap start identically. The press only becomes a drag once it
 *   has travelled far enough to mean it, and the click that follows a real drag
 *   is swallowed — otherwise letting go of the marker would open the finder.
 * ------------------------------------------------------------------------- */
(function () {
    /* Kept between the marker and the edge of the window. */
    var EDGE = 16;

    /* How far a press has to travel before it counts as a drag rather than a
       tap. Fingers are not still, and 6px forgives that without swallowing a
       deliberate nudge. */
    var SLOP = 6;

    /* Matches .finder-bubble in theme.css. Only ever used before the marker
       has been laid out and can be measured. */
    var SIZE = 48;

    var FOLD_KEY = 'ledger-finder-folded';
    var POS_KEY = 'ledger-finder-bubble';

    var bubble = null;
    var pos = null;          // the marker's top-left corner, in viewport pixels
    var drag = null;         // live pointer state, only while one is down
    var swallowClick = false;

    function finder() { return document.querySelector('.finder'); }

    function store(key, value) {
        try { sessionStorage.setItem(key, value); } catch (e) {}
    }

    function recall(key) {
        try { return sessionStorage.getItem(key); } catch (e) { return null; }
    }

    /* How you are reading now, not a setting — the same reasoning as the refine
       drawer, which is remembered for the session and no longer. */
    var folded = recall(FOLD_KEY) === 'yes';

    /* ── where the marker sits ─────────────────────────────────────────────── */

    function size() {
        return bubble && bubble.offsetWidth ? bubble.offsetWidth : SIZE;
    }

    /* Always inside the window: a phone that rotates, or a browser that shows
       and hides its own bars, can otherwise strand the marker off-screen with
       no way back to it. */
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

    /* Let go of the marker and it settles against whichever side it is nearer,
       so it ends up somewhere the reader chose rather than over a row. */
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

    /* ── the drag ──────────────────────────────────────────────────────────── */

    function onDown(event) {
        if (event.button && event.button !== 0) return;

        // Any stale suppression belongs to an interaction that is over.
        swallowClick = false;

        drag = {
            id: event.pointerId,
            offsetX: event.clientX - pos.x,
            offsetY: event.clientY - pos.y,
            moved: false
        };

        /* Capture, so a finger that outruns the marker keeps moving it instead
           of dropping it the moment it leaves. */
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

        // A drag ends in a click on the same element, and that click would open
        // the finder the reader was only repositioning.
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

    /* ── building and removing it ──────────────────────────────────────────── */

    /*
     * What the marker offers is whatever the finder it replaced held, and that
     * is not the same on every page: the directory and a department's people
     * are searched and filtered, a department's contacts are only navigated.
     * So the fold button says what its finder is for — data-finder-glyph and
     * data-finder-restore — and the marker wears it. Nothing set means the
     * search finder, which is every other page.
     */
    var GLYPHS = {
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        nav: '<path d="M4 6h16M4 12h16M4 18h16"/>'
    };

    /* Inside the finder, so it survives being folded — display:none still
       answers querySelector — but not a wire:navigate, hence the re-read. */
    function trigger() { return document.querySelector('[data-finder-fold]'); }

    /* Applied on every apply(), not only when the marker is built: it outlives
       navigation on purpose, so the same one can be left over a page that
       wants it to say something else. */
    function dress(el) {
        var button = trigger();
        var label = (button && button.getAttribute('data-finder-restore')) || 'Show search and filters';
        var glyph = GLYPHS[button && button.getAttribute('data-finder-glyph')] || GLYPHS.search;

        el.setAttribute('aria-label', label);
        el.title = label;

        el.innerHTML =
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            glyph + '</svg>';
    }

    function build() {
        var el = document.createElement('button');

        el.type = 'button';
        el.className = 'finder-bubble';

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

    /* ── folding, without moving the page ──────────────────────────────────── */

    /*
     * Hiding or restoring the finder changes the height of everything above the
     * rows, so they slide up or down under a scroll position that has not
     * changed. Measure one fixed point either side of the change and put the
     * difference back.
     *
     * The finder's next sibling is the results block in both components — the
     * one thing the reader is actually looking at.
     */
    function withoutMovingThePage(change) {
        var el = finder();
        var mark = el ? el.nextElementSibling : null;

        /*
         * Except at the very top, where there is nothing to hold still.
         * Unfolding there should simply push the rows down and leave the reader
         * looking at the heading — not scroll them a finder's height into the
         * list to keep it from moving.
         */
        var before = (mark && window.scrollY > 1) ? mark.getBoundingClientRect().top : null;

        change();

        if (before === null) return;

        var shift = mark.getBoundingClientRect().top - before;

        // 'instant' for the reason park() gives.
        if (shift) window.scrollBy({ top: shift, behavior: 'instant' });
    }

    function apply(moveFocus) {
        var el = finder();

        // After a wire:navigate the old marker went with the old body, leaving
        // this pointing at a node that is no longer anywhere.
        if (bubble && !bubble.isConnected) bubble = null;

        // Profiles and other pages have no finder to fold.
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

        /*
         * Keyboard focus was on a control that has just been hidden, and lost
         * focus lands on <body> — leaving a keyboard reader with nothing to tab
         * from. preventScroll because the page has just been put exactly where
         * it should be, and focusing must not undo that.
         */
        if (moveFocus) {
            var next = folded ? bubble : el.querySelector('[data-finder-fold]');

            if (next) next.focus({ preventScroll: true });
        }

        // The parked/not-parked module has to re-decide: unfolding can put a
        // finder back onto a page already scrolled well past its resting place.
        document.dispatchEvent(new CustomEvent('ledger:finder-fold'));
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

    /* Delegated: the button lives inside a Livewire component, so the element
       itself is replaced on every morph and every navigation. */
    document.addEventListener('click', function (event) {
        var button = event.target.closest
            ? event.target.closest('[data-finder-fold]')
            : null;

        if (button) fold();
    });

    /* A rotation, or a window dragged smaller, changes what is still on screen
       — clamp() is what keeps the marker reachable, and it needs the new size. */
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
