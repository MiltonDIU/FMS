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
         * Jump to a specific gap field inside Filament 5 vertical tabs.
         * Handles repeater items: expands the correct collapsed item, then focuses the field.
         *
         * @param {string}      fieldId     - The Filament field name (e.g. 'membership_organization_id', 'degree_type_id')
         * @param {string}      tabLabel    - The EXACT Filament Tab::make() label (e.g. 'Memberships', 'Educations')
         * @param {number|null} recordIndex - The 0-based index of the repeater item (null for non-repeater fields)
         */
        function jumpToGap(fieldId, tabLabel, recordIndex) {
            // Step 1: Click the correct Filament 5 tab
            if (tabLabel) {
                const needle = tabLabel.trim().toLowerCase();
                const allTabBtns = document.querySelectorAll('.fi-tabs-item button, [role="tab"], nav button');
                for (const btn of allTabBtns) {
                    if (btn.textContent.trim().toLowerCase().includes(needle)) {
                        btn.click();
                        break;
                    }
                }
            }

            // Step 2: Retry loop to wait for Livewire/Filament tab switch to settle
            let attempts = 0;
            const checkAndRun = () => {
                attempts++;
                if (recordIndex !== null && recordIndex !== undefined && typeof recordIndex === 'number') {
                    // Scope search to the active tab panel
                    const activeTabContent = document.querySelector('.fi-tabs-content:not([style*="display: none"]), [role="tabpanel"]:not([hidden]), .fi-tabs-content');
                    const items = activeTabContent ? activeTabContent.querySelectorAll('.fi-fo-repeater-item') : document.querySelectorAll('.fi-fo-repeater-item');

                    if (items.length > 0 || attempts >= 4) {
                        expandRepeaterAndFocus(fieldId, recordIndex, items);
                    } else {
                        setTimeout(checkAndRun, 150);
                    }
                } else {
                    focusField(fieldId);
                }
            };

            setTimeout(checkAndRun, 250);
        }

        /**
         * Expand the Nth collapsed repeater item in active tab, then focus a field inside it.
         */
        function expandRepeaterAndFocus(fieldId, recordIndex, items) {
            const repeaterItems = items && items.length > 0 ? items : document.querySelectorAll('.fi-fo-repeater-item');

            if (!repeaterItems || repeaterItems.length === 0 || recordIndex >= repeaterItems.length) {
                focusField(fieldId);
                return;
            }

            const targetItem = repeaterItems[recordIndex];

            // Locate collapse toggle button inside repeater item header
            const headerBtns = targetItem.querySelectorAll('.fi-fo-repeater-item-header button, button[x-on\\:click*="collapse"], button[x-on\\:click*="isCollapsed"], button');
            
            // Check if item is currently collapsed
            const contentArea = targetItem.querySelector('.fi-fo-repeater-item-content') || targetItem.querySelector('[x-show*="Collapsed"], [x-show*="collapsed"]');
            const isCollapsed = !contentArea || (
                contentArea.style.display === 'none' ||
                contentArea.hasAttribute('x-cloak') ||
                contentArea.offsetHeight === 0
            );

            if (isCollapsed && headerBtns.length > 0) {
                // Click the toggle button to expand the item
                headerBtns[0].click();
                setTimeout(() => focusFieldInContainer(fieldId, targetItem), 350);
            } else {
                focusFieldInContainer(fieldId, targetItem);
            }

            // Scroll item into view
            targetItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Visual highlight box around repeater item
            targetItem.style.outline = '3px solid #6366F1';
            targetItem.style.outlineOffset = '2px';
            targetItem.style.borderRadius = '8px';
            setTimeout(() => {
                targetItem.style.outline = '';
                targetItem.style.outlineOffset = '';
            }, 4000);
        }

        /**
         * Focus a specific field inside a container element (repeater item).
         */
        function focusFieldInContainer(fieldId, container) {
            const cleanId = (fieldId || '').replace('input_', '').replace('data.', '');

            // Search for the field inside this specific repeater item
            let el = container.querySelector(`[name*="${cleanId}"]`)
                  || container.querySelector(`select[name*="${cleanId}"]`)
                  || container.querySelector(`input[name*="${cleanId}"]`)
                  || container.querySelector(`textarea[name*="${cleanId}"]`);

            // Fallback: search by label text inside the container
            if (!el) {
                const labels = container.querySelectorAll('label');
                const searchText = cleanId.replaceAll('_', ' ').toLowerCase();
                for (const lbl of labels) {
                    if (lbl.textContent.toLowerCase().includes(searchText)) {
                        const forId = lbl.getAttribute('for');
                        if (forId) el = document.getElementById(forId);
                        if (!el) el = lbl.closest('.fi-fo-field-wrp')?.querySelector('input, select, textarea, button');
                        if (el) break;
                    }
                }
            }

            if (el) {
                highlightElement(el);
            }
        }

        /**
         * Focus a field anywhere in the page (non-repeater fields).
         */
        function focusField(fieldId) {
            const cleanId = (fieldId || '').replace('input_', '').replace('data.', '');

            let el = document.querySelector(`[name="data.${cleanId}"]`)
                  || document.querySelector(`[name="${cleanId}"]`)
                  || document.getElementById(`data.${cleanId}`)
                  || document.getElementById(cleanId)
                  || document.querySelector(`input[name*="${cleanId}"]`)
                  || document.querySelector(`textarea[name*="${cleanId}"]`)
                  || document.querySelector(`select[name*="${cleanId}"]`);

            // Fallback: search by label text
            if (!el) {
                const labels = document.querySelectorAll('label');
                const searchText = cleanId.replaceAll('_', ' ').toLowerCase();
                for (const lbl of labels) {
                    if (lbl.textContent.toLowerCase().includes(searchText)) {
                        const forId = lbl.getAttribute('for');
                        if (forId) el = document.getElementById(forId);
                        if (!el) el = lbl.closest('.fi-fo-field-wrp')?.querySelector('input, select, textarea');
                        if (el) break;
                    }
                }
            }

            if (el) {
                highlightElement(el);
            }
        }

        /**
         * Scroll to, focus, and visually highlight an element.
         */
        function highlightElement(el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof el.focus === 'function') el.focus();

            // Amber outline glow
            el.style.outline = '3px solid #f59e0b';
            el.style.outlineOffset = '2px';
            el.style.transition = 'outline 0.3s ease';
            setTimeout(() => {
                el.style.outline = '';
                el.style.outlineOffset = '';
            }, 3500);
        }
    </script>

    <x-filament-actions::modals />
</x-filament-panels::page>
