{{--
    System Settings → Maintenance Mode → Internal Notice, just under the admin
    panel's top bar. See App\Helpers\AdminNotice.

    Closing it hides it for the rest of that day, in that browser. The choice is
    kept against this notice's text and colour, so a new or edited notice shows
    again straight away. Remembered in localStorage, which can be unavailable —
    a private window, blocked site data — and then the notice simply shows.

    Starts hidden and is revealed by Alpine, so a notice already closed today
    does not flash up on every page load.
--}}
<div
    x-data="{
        key: 'fms-admin-notice-closed',
        id: @js($id),
        open: false,
        today() { return new Date().toLocaleDateString('en-CA') },
        init() {
            try {
                const closed = JSON.parse(localStorage.getItem(this.key) || 'null');
                this.open = ! (closed && closed.id === this.id && closed.day === this.today());
            } catch (e) {
                this.open = true;
            }
        },
        close() {
            this.open = false;
            try {
                localStorage.setItem(this.key, JSON.stringify({ id: this.id, day: this.today() }));
            } catch (e) {}
        },
    }"
    x-show="open"
    style="display:none;"
    role="status"
>
    {{-- The panel switches to dark with a class on <html>, which an inline style
         cannot see, so the colours are custom properties a rule picks between. --}}
    <style>
        .fms-admin-notice { background: var(--notice-bg); border-bottom: 1px solid var(--notice-border); color: var(--notice-text); }
        .dark .fms-admin-notice { background: var(--notice-dark-bg); border-bottom-color: var(--notice-dark-border); color: var(--notice-dark-text); }
    </style>

    <div
        class="fms-admin-notice"
        style="--notice-bg:{{ $style['bg'] }};--notice-border:{{ $style['border'] }};--notice-text:{{ $style['text'] }};--notice-dark-bg:{{ $style['dark_bg'] }};--notice-dark-border:{{ $style['dark_border'] }};--notice-dark-text:{{ $style['dark_text'] }};display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem 1.25rem;font-size:1rem;line-height:1.6;"
    >
        <x-filament::icon icon="heroicon-o-megaphone" style="width:1.5rem;height:1.5rem;flex-shrink:0;margin-top:0.05rem;" />

        <div style="flex:1;min-width:0;font-weight:500;overflow-wrap:anywhere;">{!! nl2br(e($message)) !!}</div>

        <button
            type="button"
            x-on:click="close()"
            aria-label="{{ __('Close this notice for today') }}"
            title="{{ __('Close for today') }}"
            style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:1.75rem;height:1.75rem;border-radius:9999px;color:inherit;opacity:0.75;cursor:pointer;background:transparent;border:0;"
            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.75"
        >
            <x-filament::icon icon="heroicon-o-x-mark" style="width:1.25rem;height:1.25rem;" />
        </button>
    </div>
</div>
