<x-filament-panels::page>
    {{-- Two things have to survive a long scroll on this page: the only save
         button (in the page header) and the vertical tab rail. Both are pinned
         below the panel topbar.

         The offsets are hand-tied: topbar 4rem + header 4.5rem = 8.5rem, so the
         header gets a min-height to keep that sum honest. --}}
    <style>
        .fi-page-header-main-ctn > .fi-header {
            position: sticky;
            top: 4rem;
            z-index: 20;
            min-height: 4.5rem;
            padding-block: 1rem;
            margin-block: -1rem;
            padding-inline: 1rem;
            margin-inline: -1rem;
            background-color: rgb(249 250 251);
        }

        .dark .fi-page-header-main-ctn > .fi-header {
            background-color: rgb(3 7 18);
        }

        @media (min-width: 768px) {
            .fi-page-header-main-ctn > .fi-header {
                padding-inline: 1.5rem;
                margin-inline: -1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .fi-page-header-main-ctn > .fi-header {
                padding-inline: 2rem;
                margin-inline: -2rem;
            }

            /* The rail keeps its own height instead of stretching, which is
               what lets it stick; the divider moves to the card so the line
               still runs the full height of the tallest tab. */
            .fi-sc-tabs.fi-vertical {
                position: relative;
            }

            .fi-sc-tabs.fi-vertical > nav.fi-tabs.fi-vertical {
                position: sticky;
                top: 9rem;
                align-self: flex-start;
                flex: none;
                width: 16rem;
                max-height: calc(100dvh - 10rem);
                border-inline-end-width: 0;
            }

            .fi-sc-tabs.fi-contained.fi-vertical::before {
                content: '';
                position: absolute;
                inset-block: 0;
                inset-inline-start: 16rem;
                width: 1px;
                background-color: rgb(229 231 235);
            }

            .dark .fi-sc-tabs.fi-contained.fi-vertical::before {
                background-color: rgb(255 255 255 / 0.1);
            }

            /* Fixing the rail width means long labels have to wrap rather than
               run off the edge. */
            .fi-sc-tabs.fi-vertical > nav.fi-tabs.fi-vertical .fi-tabs-item {
                white-space: normal;
                text-align: start;
            }
        }

        /* Below lg the rail and the panel were still sitting side by side, and
           the rail ate nearly the whole width - the settings themselves were
           squeezed into what was left. Lay the tabs out as a scrollable strip
           above the panel instead. */
        @media (max-width: 1023.98px) {
            .fi-sc-tabs.fi-vertical {
                flex-direction: column;
            }

            .fi-sc-tabs.fi-vertical > nav.fi-tabs.fi-vertical {
                flex-direction: row;
                width: auto;
                max-width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                gap: 0.25rem;
                border-inline-end-width: 0;
                border-bottom-width: 1px;
            }

            .fi-sc-tabs.fi-vertical > nav.fi-tabs.fi-vertical .fi-tabs-item {
                flex: none;
            }

            .fi-sc-tabs.fi-vertical > .fi-sc-tabs-tab.fi-active {
                margin-inline-start: 0;
                margin-top: 0;
                min-width: 0;
            }
        }

        @media (max-width: 639.98px) {
            .fi-sc-tabs.fi-contained > .fi-sc-tabs-tab.fi-active {
                padding: 1rem;
            }
        }
    </style>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
