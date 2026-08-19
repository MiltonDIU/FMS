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

    /*
     * These two are the whole story on Livewire v4, which dispatches exactly
     * three DOM events — init, navigate and navigated. The other themes also
     * listen for `livewire:morph` and `livewire:load`; neither is ever fired,
     * so they are left out here rather than carried forward as decoration.
     *
     * Morphing cannot strip the class anyway: it rewrites a component's own
     * markup, and the class lives on <html>.
     */
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
 * Profile section rail
 * ───────────────────────────────────────────────────────────────────────────
 * This theme shows a whole profile as one document rather than nine tabs, so
 * something has to say where you are in it. An IntersectionObserver marks the
 * section currently crossing the reading line and the rail follows.
 *
 * The rail is decoration over links that already work: with JavaScript off,
 * every entry is still an anchor to a section that is already on the page.
 * ------------------------------------------------------------------------- */
/**
 * Scroll a horizontal rail so that one of its children sits in the middle.
 *
 * Every strip of chips in this theme scrolls sideways, and on a phone two of
 * them fit. Whichever one is currently active is therefore usually off-screen —
 * so the strip shows you four things you are not looking at and hides the one
 * you are. Worse, with the active chip off to the left there is no way to tell
 * which direction the rest of them are in.
 *
 * Centring answers both: the selection is visible, and what remains on either
 * side of it is visible too.
 *
 * Written once and shared. The profile's section rail did this first, and the
 * filter rails need exactly the same behaviour; two copies of a scroll
 * calculation would drift the first time either was touched.
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

    function setup() {
        teardown();

        var links = Array.prototype.slice.call(document.querySelectorAll('[data-rail-link]'));
        if (!links.length) return;

        var sections = links
            .map(function (link) { return document.getElementById(link.dataset.railLink); })
            .filter(Boolean);

        if (!sections.length) return;

        var current = null;

        /*
         * On a narrow screen the rail is a horizontally scrolling strip, and the
         * active entry drifts off the edge as you read down the page — so the
         * one thing the strip exists to tell you is the one thing you cannot
         * see. Smoothly here, because the section changes while you are already
         * reading and an instant jump would register as a glitch.
         */
        function reveal(link) {
            // Null on the vertical rail, which does not scroll.
            centreInRail(link.closest('.rail-strip'), link, smooth());
        }

        function activate(id) {
            if (id === current) return;
            current = id;

            links.forEach(function (link) {
                var on = link.dataset.railLink === id;
                link.classList.toggle('is-active', on);

                if (on) reveal(link);
            });
        }

        /*
         * The reading line sits a third of the way down rather than at the very
         * top: a heading is "the section you are reading" from the moment it
         * comes comfortably into view, not the instant its first pixel appears
         * under the header.
         */
        /*
         * Only meaningful on a page that actually scrolls. When a sparse
         * profile fits on screen whole, nothing is "the section you are
         * reading" — everything is visible — and jumping the rail to the last
         * entry would be answering a question nobody asked.
         */
        function atBottom() {
            var doc = document.documentElement;

            if (doc.scrollHeight - window.innerHeight <= 4) return false;

            return window.innerHeight + window.scrollY >= doc.scrollHeight - 4;
        }

        observer = new IntersectionObserver(function (entries) {
            // The bottom rule below owns the last stretch of the page.
            if (atBottom()) return;

            var visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (visible.length) {
                activate(visible[0].target.id);
            }
        }, {
            rootMargin: '-20% 0px -66% 0px',
            threshold: 0,
        });

        sections.forEach(function (section) { observer.observe(section); });

        /*
         * Once the page has run out of scroll, the last sections can never reach
         * the reading line — a short Memberships list at the end simply never
         * crosses it, so the rail stayed pointing at whatever was above. At the
         * bottom of the document the last section is the one you are reading, by
         * definition.
         */
        onScroll = function () {
            if (atBottom()) activate(sections[sections.length - 1].id);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        /*
         * The page is a different height a second after it is parsed —
         * portraits load, the web font swaps in, Livewire morphs a list. Each
         * of those can turn a scrollable page into one that fits, or the other
         * way round, without a scroll event ever firing. Watching the body is
         * the only thing that catches all of them.
         */
        if (window.ResizeObserver) {
            resizeObserver = new ResizeObserver(function () { onScroll(); });
            resizeObserver.observe(document.body);
        }

        activate(sections[0].id);
        onScroll();

        // Clicking the rail should win immediately rather than waiting for the
        // smooth scroll to settle under the observer.
        links.forEach(function (link) {
            link.addEventListener('click', function () {
                activate(link.dataset.railLink);
            });
        });
    }

    document.addEventListener('livewire:navigated', setup);
    document.addEventListener('livewire:navigate', teardown);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * Filter chips: keep the chosen one in view
 * ───────────────────────────────────────────────────────────────────────────
 * The faculty and department rails have the same problem the profile's section
 * strip had. About two chips fit across a phone, so on any faculty past the
 * second the selected one sits off-screen: the rail shows what you have not
 * chosen and hides what you have, with no clue which way the rest lie.
 *
 * Centring the active chip fixes both halves of that at once.
 *
 * Deliberately not re-run on every Livewire morph. The active chip in these
 * rails only changes by navigating — faculty and department are links — while
 * morphs happen on every keystroke in the search box. Re-centring on those
 * would snatch back a rail the reader had just scrolled by hand.
 *
 * Instant, not smooth: this runs as a page arrives, and a rail visibly sliding
 * on arrival reads as the page still loading rather than as an answer.
 * ------------------------------------------------------------------------- */
(function () {
    function centreActiveChips() {
        var rails = document.querySelectorAll('.chip-rail');

        Array.prototype.forEach.call(rails, function (rail) {
            var active = rail.querySelector('.chip.is-active');

            if (active) centreInRail(rail, active, 'auto');
        });
    }

    document.addEventListener('livewire:navigated', centreActiveChips);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', centreActiveChips);
    } else {
        centreActiveChips();
    }
})();

/* ───────────────────────────────────────────────────────────────────────────
 * Command bar: stuck or floating, and staying put through a filter
 * ───────────────────────────────────────────────────────────────────────────
 * Two jobs.
 *
 * The first is cosmetic: CSS cannot ask whether a `position: sticky` element is
 * currently stuck, so the class comes from here. Parked under the header the
 * bar tightens up — smaller chips, tighter rows — squares off its top corners
 * and takes a shadow. Readability does not depend on any of that — the resting
 * surface is already opaque enough — so if this never runs the bar simply keeps
 * floating, and every control it holds still works.
 *
 * The second is the real bug: filtering from a parked bar dropped it down the
 * page. Nothing was ever wrong with the bar — the ground moved under it, and it
 * moved for two unrelated reasons depending on which control was used.
 *
 *   Designation and role are wire:click, so Livewire morphs the list in place.
 *   Fewer results means a shorter document, the browser clamps scrollY to the
 *   new maximum, and the page slides up out from under the bar.
 *
 *   Faculty and department are links carrying wire:navigate, because a faculty
 *   is an address worth sharing. That is a page swap, and Livewire returns
 *   scroll to the top as it should for a new page.
 *
 * Both are answered the same way: if the bar was parked when the interaction
 * started, put the scroll back where it needs to be for the bar to still be
 * parked once the new content is in place.
 *
 * Both need to know where the bar would sit if it were not sticky, and a
 * sticky element will not say — it reports its stuck position. Hence the
 * [data-command-anchor] marker, which sits in normal flow just above it.
 * ------------------------------------------------------------------------- */
(function () {
    // Re-queried every time: both elements live inside a Livewire component and
    // are replaced wholesale on each morph.
    function anchor() { return document.querySelector('[data-command-anchor]'); }
    function bar() { return document.querySelector('.command'); }

    function headerHeight() {
        var header = document.querySelector('header');
        return header ? header.offsetHeight : 0;
    }

    var wasStuck = false;

    /* The breakpoint the collapse rules in theme.css use. */
    function narrow() {
        return window.matchMedia
            ? window.matchMedia('(max-width: 63.99rem)').matches
            : false;
    }

    /*
     * Shut the filter drawer as the bar parks itself, on a screen too small to
     * carry both it and the results.
     *
     * The drawer is the expensive part — the two long lists stand about 180px
     * on a phone, against the ~70px the chip rails cost once .is-stuck has
     * compacted them, so the rails stay and only this goes.
     *
     * It cannot be hidden from CSS the way the rails are compacted, because
     * x-show writes an inline style and inline beats a class. So it is closed
     * at the source instead — through a method on the component, which knows
     * not to record an automatic collapse as the reader's own preference.
     */
    function collapseDrawer(el) {
        if (! window.Alpine || typeof Alpine.$data !== 'function') return;

        var data = Alpine.$data(el);

        if (data && typeof data.collapse === 'function') data.collapse();
    }

    function sync() {
        var el = bar();
        var mark = anchor();
        if (!el || !mark) return;

        /*
         * Folded into the bubble, there is no bar to be stuck or floating, and
         * `stuck` would read true from the anchor alone the moment the reader
         * scrolls — leaving the shadow and the square corners waiting on a bar
         * nobody can see. Bow out, and let the fold module's event bring us
         * back when the bar returns.
         */
        if (el.classList.contains('is-folded')) {
            wasStuck = false;
            el.classList.remove('is-stuck');
            return;
        }

        var stuck = mark.getBoundingClientRect().top <= headerHeight() + 1;

        /*
         * Only on the way in. Doing it whenever the bar is stuck would fight
         * anyone who taps Filters while parked — the drawer would shut again
         * on their next scroll, and the button would look broken.
         */
        var justStuck = stuck && ! wasStuck;

        if (justStuck && narrow()) {
            collapseDrawer(el);
        }

        wasStuck = stuck;
        el.classList.toggle('is-stuck', stuck);
    }

    /** Put the page where it needs to be for the bar to be parked. */
    function park() {
        var mark = anchor();
        if (!mark) return;

        var target = mark.getBoundingClientRect().top + window.scrollY - headerHeight();
        var max = document.documentElement.scrollHeight - window.innerHeight;

        /*
         * 'instant', not 'auto': the page sets `scroll-behavior: smooth` for
         * anchor links, and 'auto' would inherit it — turning a correction the
         * reader should never notice into a visible glide.
         */
        window.scrollTo({ top: Math.max(0, Math.min(target, max)), behavior: 'instant' });
    }

    function hold() {
        if (wasStuck && anchor()) park();

        sync();
    }

    window.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync, { passive: true });

    // Unfolding puts a bar back on a page that may already be scrolled past
    // where it would rest, so it has to be told whether it is parked before the
    // next scroll event rather than after it.
    document.addEventListener('aurora:command-fold', sync);

    /*
     * The other half of the same problem.
     *
     * Designation and role are wire:click — a morph, caught by the hook below.
     * But faculty and department are real links carrying wire:navigate, because
     * a faculty is an address worth sharing. That is a navigation, not a morph:
     * Livewire swaps the page and puts scroll back to the top, so filtering by
     * faculty from a parked bar dropped it down the page exactly as clamping
     * did, for an entirely different reason.
     *
     * wire:navigate keeps the same JS context, so whether the bar was parked
     * survives the swap in this variable.
     *
     * Only restored when the page being arrived at actually has a command bar.
     * Clicking someone's photograph is also a wire:navigate, and a profile
     * should open at the top of the profile — not part-way down it.
     */
    var parkedBeforeNavigate = false;

    document.addEventListener('livewire:navigate', function () {
        parkedBeforeNavigate = wasStuck;
    });

    document.addEventListener('livewire:navigated', function () {
        var restore = parkedBeforeNavigate;
        parkedBeforeNavigate = false;

        // Next frame: Livewire has finished its own scroll handling by then,
        // and the new page has been laid out.
        requestAnimationFrame(function () {
            if (restore && anchor()) park();

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

        // Measured on the next frame, so layout has settled and scrollHeight is
        // the new one rather than the one we are trying to correct for.
        Livewire.hook('morphed', function () {
            requestAnimationFrame(hold);
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
 * Folding the command bar into a bubble you can put where you like
 * ───────────────────────────────────────────────────────────────────────────
 * Compacting the parked bar was half an answer. A reader who has already found
 * the right list still pays for the bar on every screen of faces after that,
 * and the one thing a phone has none of is vertical space.
 *
 * So the minimise button folds the whole bar away into a bubble, the bubble is
 * dragged wherever the reader's thumb actually lives — left-handers exist, and
 * so does the bottom-right corner every browser puts its own chrome in — and a
 * tap opens the bar again exactly as it was.
 *
 * Three things this owns that are worth knowing about.
 *
 *   The bubble is built here rather than in Blade because it must outlive the
 *   component. Faculty and department chips are wire:navigate links, which
 *   replace the whole component; a bubble rendered inside it would go with
 *   them. It lives on <body>, and is rebuilt from sessionStorage after each
 *   navigation — so folded stays folded, and stays where you left it.
 *
 *   Folding removes the bar from the flow, which drags everything below it up
 *   the page. Left alone, putting the bar away would scroll the reader off the
 *   face they were looking at. Both directions are measured and corrected.
 *
 *   A drag and a tap start identically. The press only becomes a drag once it
 *   has travelled far enough to mean it, and the click that follows a real drag
 *   is swallowed — otherwise letting go of the bubble would open the bar.
 * ------------------------------------------------------------------------- */
(function () {
    /* Kept between the bubble and the edge of the window. */
    var EDGE = 16;

    /* How far a press has to travel before it counts as a drag rather than a
       tap. Fingers are not still, and 6px forgives that without swallowing a
       deliberate nudge. */
    var SLOP = 6;

    /* Matches .command-bubble in theme.css. Only ever used before the bubble
       has been laid out and can be measured. */
    var SIZE = 52;

    var FOLD_KEY = 'aurora-command-folded';
    var POS_KEY = 'aurora-command-bubble';

    var bubble = null;
    var pos = null;          // the bubble's top-left corner, in viewport pixels
    var drag = null;         // live pointer state, only while one is down
    var swallowClick = false;

    function bar() { return document.querySelector('.command'); }

    /* The breakpoint theme.css folds at. Written out rather than shared with
       the module above, so neither can quietly change it for the other. */
    function narrow() {
        return window.matchMedia
            ? window.matchMedia('(max-width: 63.99rem)').matches
            : false;
    }

    function store(key, value) {
        try { sessionStorage.setItem(key, value); } catch (e) {}
    }

    function recall(key) {
        try { return sessionStorage.getItem(key); } catch (e) { return null; }
    }

    /*
     * How you are reading now, not a setting — the same reasoning as the filter
     * drawer, which is remembered for the session and no longer.
     */
    var folded = recall(FOLD_KEY) === 'yes';

    /* ── where the bubble sits ─────────────────────────────────────────────── */

    function size() {
        return bubble && bubble.offsetWidth ? bubble.offsetWidth : SIZE;
    }

    /* Always inside the window: a phone that rotates, or a browser that shows
       and hides its own bars, can otherwise strand the bubble off-screen with
       no way back to it. */
    function clamp(point) {
        var s = size();

        return {
            x: Math.max(EDGE, Math.min(point.x, window.innerWidth - s - EDGE)),
            y: Math.max(EDGE, Math.min(point.y, window.innerHeight - s - EDGE))
        };
    }

    /* Bottom right, but lifted clear of the browser's own bottom bar. */
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

    /*
     * Let go of the bubble and it settles against whichever side it is nearer.
     * Free-floating it ends up over a face as often as not; against an edge it
     * is somewhere the reader chose and nothing has to be read around it.
     */
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

        /* Capture, so a finger that outruns the bubble keeps moving it instead
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
        // the bar the reader was only repositioning.
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
     * What the bubble offers is whatever the bar it replaced held, and that is
     * not the same on every page: the directory and a department's people are
     * searched and filtered, a department's contacts are only navigated. A
     * magnifier floating over a page with no search field promises something
     * that is not there.
     *
     * So the fold button says what its bar is for — data-command-glyph and
     * data-command-restore, see department-search — and the bubble wears it.
     * Nothing set means the search bar, which is every other page.
     */
    var GLYPHS = {
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        nav: '<path d="M4 6h16M4 12h16M4 18h16"/>'
    };

    /* Inside the bar, so it survives being folded — display:none still answers
       querySelector — but not a wire:navigate, hence the re-read below. */
    function trigger() { return document.querySelector('[data-command-fold]'); }

    /*
     * Applied on every apply(), not only when the bubble is built: the bubble
     * outlives navigation on purpose, so the same one can be left over a page
     * that wants it to say something else.
     */
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

    /* ── folding, without moving the page ──────────────────────────────────── */

    /*
     * Hiding or restoring the bar changes the height of everything above the
     * results, so the results slide up or down the page under a scroll position
     * that has not changed. Measure one fixed point either side of the change
     * and put the difference back.
     *
     * The bar's next sibling is the results block in both components — the one
     * thing the reader is actually looking at.
     */
    function withoutMovingThePage(change) {
        var el = bar();
        var mark = el ? el.nextElementSibling : null;

        /*
         * Except at the very top, where there is nothing to hold still. The bar
         * belongs at the top of the page, so unfolding there should simply push
         * the results down and leave the reader looking at the heading — not
         * scroll them a bar's height into a list to keep it from moving.
         */
        var before = (mark && window.scrollY > 1) ? mark.getBoundingClientRect().top : null;

        change();

        if (before === null) return;

        var shift = mark.getBoundingClientRect().top - before;

        // 'instant' for the reason park() gives: the page sets smooth scrolling
        // for anchor links, and a correction nobody should notice must not
        // inherit it.
        if (shift) window.scrollBy({ top: shift, behavior: 'instant' });
    }

    function apply(moveFocus) {
        var el = bar();

        // After a wire:navigate the old bubble went with the old body, leaving
        // this pointing at a node that is no longer anywhere.
        if (bubble && !bubble.isConnected) bubble = null;

        // Profiles and other pages have no command bar to fold.
        if (!el) {
            remove();
            return;
        }

        var away = folded && narrow();

        if (away) {
            if (!bubble) bubble = build();

            dress(bubble);
            place(pos || recallPlace() || restingPlace());
        } else {
            remove();
        }

        withoutMovingThePage(function () {
            el.classList.toggle('is-folded', away);
        });

        /*
         * Keyboard focus was on a control that has just been hidden, and lost
         * focus lands on <body> — leaving a keyboard reader with nothing to tab
         * from. preventScroll because the page has just been put exactly where
         * it should be, and focusing must not undo that.
         */
        if (moveFocus) {
            var next = away ? bubble : el.querySelector('[data-command-fold]');

            if (next) next.focus({ preventScroll: true });
        }

        // The stuck/floating module has to re-decide: unfolding can put a bar
        // back onto a page already scrolled well past its resting place.
        document.dispatchEvent(new CustomEvent('aurora:command-fold'));
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
        var trigger = event.target.closest
            ? event.target.closest('[data-command-fold]')
            : null;

        if (trigger) fold();
    });

    /*
     * A rotation changes both which side is nearer and what is still on screen,
     * and crossing back to a wide window has to give the bar back — nobody
     * expects a phone-sized decision to follow them onto a desktop.
     */
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
