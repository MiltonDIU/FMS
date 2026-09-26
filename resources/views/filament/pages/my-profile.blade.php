<x-filament-panels::page>
    <style>
        @media (max-width: 1024px) {
            .responsive-vertical-tabs {
                display: flex;
                flex-direction: column !important;
            }
            .responsive-vertical-tabs > .fi-tabs-nav {
                width: 100% !important;
                border-right: none !important;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 1rem;
                display: flex !important;
                flex-direction: row !important;
                overflow-x: auto !important;
                white-space: nowrap !important;
                gap: 0.5rem;
                padding-bottom: 0.5rem;
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            .responsive-vertical-tabs > .fi-tabs-nav::-webkit-scrollbar { display: none; }
            .responsive-vertical-tabs > .fi-tabs-nav > .fi-tabs-item { flex: 0 0 auto !important; }
            .responsive-vertical-tabs > .fi-tabs-content { width: 100% !important; }
        }
    </style>

    {{-- Gap Analysis Banner (above form tabs) --}}
    @include('filament.pages.partials.profile-assessment-banner', [
        'teacher'   => auth()->user()?->teacher,
        'gapReport' => $gapReport ?? [],
    ])

    {{-- Profile declaration: apart from saving, made once, then gone for good. --}}
    @if($this->needsDeclaration)
        <x-filament::section
            icon="heroicon-o-document-check"
            icon-color="primary"
            :heading="__('Please Review Your Profile')"
            :description="__('A one-time request. Thank you for your time.')"
        >
            <div style="display:flex;flex-direction:column;gap:1rem;font-size:0.875rem;line-height:1.65;">
                <div>
                    <p style="margin:0 0 0.5rem;">{{ __('Dear Faculty Member,') }}</p>
                    <p style="margin:0 0 0.5rem;">
                        {{ __('Your profile has been moved to this new system from the previous faculty website. As this was done automatically, a few details may not have come across correctly.') }}
                    </p>
                    <p style="margin:0;">
                        {{ __('We would be grateful if you could look through each tab, update anything that needs correcting, and save your changes. When you are happy with your profile, kindly confirm below.') }}
                    </p>
                </div>

                <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;padding:0.75rem 1rem;border-radius:0.5rem;border:1px solid rgba(99,102,241,0.35);background:rgba(99,102,241,0.06);">
                    <x-filament::input.checkbox wire:model.live="declarationAccepted" style="margin-top:0.25rem;" />
                    <span>
                        {{ __('I have reviewed my profile, and to the best of my knowledge the information is correct and ready to be shown on the faculty directory.') }}
                    </span>
                </label>

                <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                    <x-filament::button
                        wire:click="confirmVerification"
                        icon="heroicon-o-check-badge"
                        color="success"
                        :disabled="! $declarationAccepted"
                        wire:loading.attr="disabled"
                        wire:target="confirmVerification"
                    >
                        {{ __('Confirm My Profile') }}
                    </x-filament::button>
                    <span style="font-size:0.8rem;opacity:0.7;">{{ __('You only need to do this once.') }}</span>
                </div>
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div style="margin-top: 2rem !important; padding-top: 1.5rem !important; border-top: 1px solid #e5e7eb;" class="dark:border-gray-800">
            <x-filament::actions
                :actions="$this->getFormActions()"
            />
        </div>
    </form>

    <script>
        /**
         * Jump from a Needs Attention item to the field it is about.
         *
         * Written against Filament 5's markup. The version before this looked
         * for ".fi-tabs-content" and for panels with a `hidden` attribute,
         * neither of which Filament 5 renders: it always took Basic Info as the
         * open panel, then fell back to the first repeater items on the page,
         * which sit in another, hidden tab. Plain fields like those under
         * Contact Info were found by name and worked; anything inside a
         * repeated section (qualifications, publications, experience...)
         * scrolled to something invisible.
         *
         * @param {string}      fieldId     - field name ('degree_type_id') or section name ('educations')
         * @param {string}      tabLabel    - the Tab::make() label ('Educations')
         * @param {number|null} recordIndex - 0-based row of a repeated section, or null
         */
        function jumpToGap(fieldId, tabLabel, recordIndex) {
            const panel = openProfileTab(tabLabel);
            const scope = panel || document;

            const isRow = typeof recordIndex === 'number';
            // A row past what the section has loaded (publications load ten at
            // a time) is not on the page; point at the section, where "Load
            // more" is, rather than at some other row.
            const item = isRow ? (repeaterItemsIn(scope)[recordIndex] || null) : null;

            if (item) {
                // Filament's own reveal: the item listens for `expand` to open
                // itself, and the tab panel to switch to it.
                item.dispatchEvent(new CustomEvent('expand', { bubbles: true }));
            }

            // Give Alpine a moment to show the tab and the opened row.
            setTimeout(() => {
                const target = item
                    ? (findProfileField(item, fieldId) || item)
                    : (isRow ? scope.querySelector('.fi-fo-repeater') : findProfileField(scope, fieldId));

                if (target) {
                    highlightElement(target);
                    return;
                }

                // Not where the checklist files it (Bio is listed under
                // Personal Details but sits in Basic Info): find it anywhere
                // and let it open its own tab.
                const elsewhere = findProfileField(document, fieldId);
                if (elsewhere) {
                    elsewhere.dispatchEvent(new CustomEvent('expand', { bubbles: true }));
                    setTimeout(() => highlightElement(elsewhere), 300);
                } else if (panel) {
                    highlightElement(panel);
                }
            }, 300);
        }

        /** Clicks the tab with this exact label and returns its panel. */
        function openProfileTab(tabLabel) {
            if (!tabLabel) return null;

            const wanted = tabLabel.trim().toLowerCase();
            const tab = Array.from(document.querySelectorAll('.fi-sc-tabs [role="tab"]')).find((btn) => {
                const label = btn.querySelector('.fi-tabs-item-label');
                return (label ? label.textContent : btn.textContent).trim().toLowerCase() === wanted;
            });

            if (!tab) return null;
            tab.click();

            const key = tab.getAttribute('data-tab-key');
            return Array.from(document.querySelectorAll('[role="tabpanel"]'))
                .find((panel) => key && panel.id.endsWith(key)) || null;
        }

        /** The rows of the first repeated section in a tab, in screen order. */
        function repeaterItemsIn(scope) {
            const repeater = scope.querySelector('.fi-fo-repeater');
            if (!repeater) return [];

            return Array.from(repeater.querySelectorAll('li.fi-fo-repeater-item'))
                .filter((li) => li.closest('.fi-fo-repeater') === repeater);
        }

        /**
         * A field by name inside a container. Inputs carry
         * id="form.educations.record-12.passing_year"; selects are drawn by
         * Alpine and are found by their wire:model / wire:key instead; a whole
         * section is id="form.educations".
         */
        function findProfileField(container, fieldId) {
            if (!fieldId) return null;

            const name = String(fieldId).replace(/^data\./, '').replace(/^input_/, '');
            const suffix = '.' + name;
            const attrs = ['id', 'wire:model', 'wire:model.live', 'wire:key'];

            for (const el of container.querySelectorAll('[id], [wire\\:model], [wire\\:model\\.live], [wire\\:key]')) {
                for (const attr of attrs) {
                    const value = el.getAttribute(attr);
                    if (value && (value === 'form.' + name || value === 'data.' + name || value.endsWith(suffix) || value.includes(suffix + '.'))) {
                        return el;
                    }
                }
            }

            return null;
        }

        /**
         * Scrolls to the field — its whole wrapper, label included — and marks
         * it with one ring.
         *
         * One ring, drawn flush: this used to add an outline 4px out and also
         * focus the input, so Filament's own focus ring appeared inside it and
         * a row's border showed inside that — two borders every time.
         */
        function highlightElement(el) {
            const box = el.matches('[data-field-wrapper]')
                ? el
                : (el.querySelector('[data-field-wrapper]') || el.closest('[data-field-wrapper]') || el);

            box.scrollIntoView({ behavior: 'smooth', block: 'center' });

            const previous = { boxShadow: box.style.boxShadow, borderRadius: box.style.borderRadius, transition: box.style.transition };

            box.style.transition = 'box-shadow 0.3s ease';
            box.style.borderRadius = '0.5rem';
            box.style.boxShadow = '0 0 0 3px rgba(245, 158, 11, 0.85)';

            setTimeout(() => {
                box.style.boxShadow = previous.boxShadow;
                setTimeout(() => {
                    box.style.borderRadius = previous.borderRadius;
                    box.style.transition = previous.transition;
                }, 300);
            }, 3500);
        }
    </script>

    <x-filament-actions::modals />
</x-filament-panels::page>
