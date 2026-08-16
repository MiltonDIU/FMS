<style>
    .fms-review-container {
        --fms-line: #d1d5db;
        --fms-head: #f3f4f6;
        --fms-muted: #4b5563;
        --fms-strong: #111827;
        --fms-bg: #ffffff;
        /* The panel's primary is Amber; the selection badge follows it. */
        --fms-accent: #d97706;
        --fms-accent-text: #ffffff;
        max-height: calc(85vh - 80px);
        display: flex;
        flex-direction: column;
    }
    .dark .fms-review-container {
        --fms-line: #374151;
        --fms-head: #1f2937;
        --fms-muted: #9ca3af;
        --fms-strong: #f3f4f6;
        --fms-bg: #111827;
        --fms-accent: #f59e0b;
        --fms-accent-text: #1f2937;
    }

    .fms-custom-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }
    .fms-custom-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .fms-custom-scroll::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.04);
        border-radius: 4px;
    }
    .dark .fms-custom-scroll::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.04);
    }
    .fms-custom-scroll::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.5);
        border-radius: 4px;
    }
    .fms-custom-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(107, 114, 128, 0.8);
    }

    .fms-review-table-wrapper {
        border: 1px solid var(--fms-line);
        border-radius: 0.75rem;
        overflow: hidden;
        background: var(--fms-bg);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }

    .fms-review-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .fms-review-table th,
    .fms-review-table td {
        border: 1px solid var(--fms-line) !important;
        padding: 0.65rem 0.85rem;
        vertical-align: top;
    }

    .fms-review-table th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: var(--fms-head);
        font-weight: 600;
        color: var(--fms-strong);
        text-transform: uppercase;
        font-size: 0.725rem;
        letter-spacing: 0.04em;
    }

    .fms-tab-content-scroll {
        max-height: calc(85vh - 160px);
        overflow-y: auto;
    }

    /*
     * The bulk toolbar.
     *
     * Everything in it used to be 11px text in one flex row — the count, both
     * actions, the clear link — so it read as a sentence rather than as
     * controls, and the disabled buttons at 40% opacity looked like greyed-out
     * words. It needs to be a bar: its own bounds, the count on one side, the
     * buttons on the other, and edges on the buttons so they are recognisable
     * as buttons whether or not they are usable.
     */
    .fms-bulk-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.55rem 0.8rem;
        border: 1px solid var(--fms-line);
        border-radius: 0.75rem;
        background-color: var(--fms-head);
    }

    .fms-bulk-status,
    .fms-bulk-count {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.75rem;
        min-width: 0;
    }

    .fms-bulk-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 0.4rem;
        border-radius: 999px;
        font-weight: 700;
        /* So the bar does not jitter as the count goes 9 -> 10 -> 100. */
        font-variant-numeric: tabular-nums;
        background-color: var(--fms-accent);
        color: var(--fms-accent-text);
    }

    .fms-bulk-selected {
        font-weight: 600;
        color: var(--fms-strong);
    }

    .fms-bulk-hint {
        color: var(--fms-muted);
    }

    .fms-bulk-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
    }

    .fms-bulk-btn {
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25rem;
        padding: 0.25rem 0.7rem;
        border-radius: 0.5rem;
        /* Neutral rather than a colour, so one rule gives every tint an edge. */
        border: 1px solid rgba(0, 0, 0, 0.12);
        white-space: nowrap;
        transition: filter 0.15s ease, opacity 0.15s ease;
    }

    .dark .fms-bulk-btn {
        border-color: rgba(255, 255, 255, 0.14);
    }

    .fms-bulk-btn:not(:disabled):hover {
        filter: brightness(0.96);
    }

    .fms-bulk-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .fms-bulk-clear {
        font-size: 0.7rem;
        color: var(--fms-muted);
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
    }

    .fms-bulk-clear:hover {
        color: var(--fms-strong);
        background-color: rgba(127, 127, 127, 0.12);
    }

    /* People filter bar & match badges */
    .fms-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--fms-line);
        border-radius: 0.75rem;
        background-color: var(--fms-head);
    }

    .fms-filter-title {
        font-size: 0.725rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--fms-muted);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .fms-filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
    }

    .fms-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.6rem;
        border-radius: 0.5rem;
        border: 1px solid var(--fms-line);
        background-color: var(--fms-bg);
        color: var(--fms-strong);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .fms-filter-pill:hover {
        background-color: rgba(127, 127, 127, 0.08);
    }

    .fms-filter-pill.active-all {
        background-color: #1f2937;
        color: #ffffff;
        border-color: #1f2937;
    }
    .dark .fms-filter-pill.active-all {
        background-color: #f3f4f6;
        color: #111827;
        border-color: #f3f4f6;
    }

    .fms-filter-pill.active-teacher {
        background-color: #059669;
        color: #ffffff;
        border-color: #059669;
    }

    .fms-filter-pill.active-author {
        background-color: #7c3aed;
        color: #ffffff;
        border-color: #7c3aed;
    }

    .fms-filter-pill.active-unmatched {
        background-color: #d97706;
        color: #ffffff;
        border-color: #d97706;
    }

    .fms-filter-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
        font-size: 0.675rem;
        font-weight: 700;
        background-color: rgba(127, 127, 127, 0.15);
        color: inherit;
    }

    .fms-icon-sm {
        width: 14px !important;
        height: 14px !important;
        min-width: 14px !important;
        min-height: 14px !important;
        max-width: 14px !important;
        max-height: 14px !important;
        flex-shrink: 0;
        display: inline-block;
        vertical-align: middle;
    }

    .fms-match-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.375rem;
        font-weight: 700;
        font-size: 0.75rem;
        line-height: 1.1;
    }

    .fms-match-badge-teacher {
        background-color: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .dark .fms-match-badge-teacher {
        background-color: rgba(6, 78, 59, 0.5);
        color: #6ee7b7;
        border-color: #047857;
    }

    .fms-match-badge-author {
        background-color: #f5f3ff;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .dark .fms-match-badge-author {
        background-color: rgba(76, 29, 149, 0.5);
        color: #c4b5fd;
        border-color: #6d28d9;
    }

    .fms-match-unmatched {
        color: var(--fms-muted);
        font-style: italic;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
</style>

<div x-data="{
    activeTab: 'summary',
    peopleFilter: 'all',
    importId: {{ $import->id }},

    /*
     * Deciding a batch at a time.
     *
     * Row by row is fine for a handful, but a run brings hundreds — 879 papers
     * in the export this was built against — and most of them get the same
     * answer. One key per pending row, per tab; a row already imported has no
     * checkbox and so can never be in here.
     */
    picked: { attention: [], new: [], people: [] },
    working: false,

    /*
     * Keys are read off the elements rather than written into the markup.
     *
     * A paper without an EID or DOI is keyed by its title, which can hold
     * quotes and apostrophes — spliced into a JS string those end the string
     * early and the row silently stops working.
     */
    rows(group) {
        return Array.from(this.$root.querySelectorAll('select[data-decision-group=\'' + group + '\']'));
    },

    visibleRows(group) {
        return this.rows(group).filter((el) => {
            const tr = el.closest('tr');
            if (!tr) return true;
            return tr.style.display !== 'none' && window.getComputedStyle(tr).display !== 'none';
        });
    },

    keys(group) {
        return this.visibleRows(group).map((el) => el.dataset.decisionKey);
    },

    allPicked(group) {
        const vKeys = this.keys(group);

        return vKeys.length > 0 && vKeys.every((k) => this.picked[group].includes(k));
    },

    toggle(group, key) {
        const at = this.picked[group].indexOf(key);

        at === -1 ? this.picked[group].push(key) : this.picked[group].splice(at, 1);
    },

    toggleAll(group, checked) {
        const vKeys = this.keys(group);

        if (checked) {
            this.picked[group] = Array.from(new Set([...this.picked[group], ...vKeys]));
        } else {
            this.picked[group] = this.picked[group].filter((k) => !vKeys.includes(k));
        }
    },

    async applyBulk(group, decision) {
        if (this.working || this.picked[group].length === 0) {
            return;
        }

        this.working = true;
        const chosen = [...this.picked[group]];

        try {
            await (group === 'people'
                ? this.$wire.bulkUpdatePersonDecisions(this.importId, chosen, decision)
                : this.$wire.bulkUpdatePaperDecisions(this.importId, chosen, decision));

            // The stored payload is what counts, but the dropdowns have to agree
            // with it or the next glance at the tab is a lie.
            this.rows(group).forEach((el) => {
                if (chosen.includes(el.dataset.decisionKey)) {
                    el.value = decision;
                }
            });

            this.picked[group] = [];
        } finally {
            this.working = false;
        }
    }
}" class="fms-review-container space-y-4">

    <!-- Sticky Navigation Tabs -->
    <div style="border-bottom: 2px solid var(--fms-line);" class="sticky top-0 bg-white dark:bg-gray-900 z-20 pb-1">
        <nav class="-mb-px flex space-x-2 overflow-x-auto" aria-label="Tabs">
            <button type="button" 
                @click="activeTab = 'summary'"
                :class="activeTab === 'summary' ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 bg-primary-50/50 dark:bg-primary-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>📊 Summary</span>
            </button>

            <button type="button" 
                @click="activeTab = 'matched'"
                :class="activeTab === 'matched' ? 'border-success-500 text-success-600 dark:text-success-400 font-bold border-b-2 bg-success-50/50 dark:bg-success-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>✅ Matched</span>
                <span class="bg-success-100 text-success-800 dark:bg-success-900/60 dark:text-success-200 text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ count(array_filter($payload['papers'] ?? [], fn($p) => $p['existing_publication_id'] && ($p['authorship']['status'] ?? '') === 'clean')) }}
                </span>
            </button>

            <button type="button" 
                @click="activeTab = 'attention'"
                :class="activeTab === 'attention' ? 'border-warning-500 text-warning-600 dark:text-warning-400 font-bold border-b-2 bg-warning-50/50 dark:bg-warning-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>⚠️ Needs Attention</span>
                <span class="bg-warning-100 text-warning-800 dark:bg-warning-900/60 dark:text-warning-200 text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ count(array_filter($payload['papers'] ?? [], fn($p) => $p['existing_publication_id'] && ($p['authorship']['status'] ?? '') !== 'clean')) }}
                </span>
            </button>

            <button type="button" 
                @click="activeTab = 'new'"
                :class="activeTab === 'new' ? 'border-danger-500 text-danger-600 dark:text-danger-400 font-bold border-b-2 bg-danger-50/50 dark:bg-danger-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>🆕 Not in Our System</span>
                <span class="bg-danger-100 text-danger-800 dark:bg-danger-900/60 dark:text-danger-200 text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ count(array_filter($payload['papers'] ?? [], fn($p) => !$p['existing_publication_id'])) }}
                </span>
            </button>

            <button type="button" 
                @click="activeTab = 'imported'"
                :class="activeTab === 'imported' ? 'border-info-500 text-info-600 dark:text-info-400 font-bold border-b-2 bg-info-50/50 dark:bg-info-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>📥 Imported</span>
                <span class="bg-info-100 text-info-800 dark:bg-info-900/60 dark:text-info-200 text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ count(array_filter($payload['papers'] ?? [], fn($p) => ($p['decision'] ?? '') === 'imported')) + count(array_filter($payload['people'] ?? [], fn($p) => ($p['decision'] ?? '') === 'imported')) }}
                </span>
            </button>

            <button type="button" 
                @click="activeTab = 'people'"
                :class="activeTab === 'people' ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 bg-primary-50/50 dark:bg-primary-950/20' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="whitespace-nowrap py-2.5 px-4 text-sm flex items-center gap-2 rounded-t-lg transition">
                <span>👥 People</span>
                <span class="bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ count($payload['people'] ?? []) }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Tab 1: Summary -->
    <div x-show="activeTab === 'summary'" class="space-y-4 fms-tab-content-scroll fms-custom-scroll pr-1">
        @include('filament.pages.partials.scopus-summary', ['import' => $import])
    </div>

    <!-- Tab 2: Matched -->
    <div x-show="activeTab === 'matched'" class="space-y-4" x-cloak>
        <div class="fms-review-table-wrapper">
            <div class="overflow-x-auto fms-tab-content-scroll fms-custom-scroll">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th>Scopus Title</th>
                            <th>Year</th>
                            <th>DOI</th>
                            <th>Authors</th>
                            <th>Matched DB Record</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_filter($payload['papers'] ?? [], fn($p) => $p['existing_publication_id'] && ($p['authorship']['status'] ?? '') === 'clean') as $paper)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="font-medium text-gray-900 dark:text-gray-100">{{ $paper['title'] }}</td>
                                <td>{{ $paper['year'] }}</td>
                                <td>{{ $paper['doi'] ?: '—' }}</td>
                                <td class="text-gray-600 dark:text-gray-400">{{ $paper['all_authors'] }}</td>
                                <td>
                                    <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-mono text-xs font-semibold">
                                        #{{ $paper['existing_publication_id'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-gray-500 dark:text-gray-400">No clean matched papers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Needs Attention -->
    <div x-show="activeTab === 'attention'" class="space-y-4" x-cloak>
        @php
            /*
             * The same three verdicts the workbook prints, in the same words.
             *
             * The tab used to show only the sentence from `note`, unlabelled, so
             * what kind of problem a row had — and which two names disagreed
             * about first authorship — could only be had by downloading the
             * spreadsheet. These are the workbook's "Authorship", "What differs",
             * "Scopus 1st author" and "Our 1st author" columns, brought here.
             */
            $attentionVerdicts = [
                'nobody-credited' => ['Nobody credited here', 'bg-danger-100 text-danger-800 dark:bg-danger-900/50 dark:text-danger-300'],
                'first-author-differs' => ['First author differs', 'bg-danger-50 text-danger-700 ring-1 ring-danger-300 dark:bg-danger-950/40 dark:text-danger-300 dark:ring-danger-800'],
                'missing-authors' => ['Authors missing here', 'bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300'],
            ];

            $attentionPapers = array_filter(
                $payload['papers'] ?? [],
                fn($p) => $p['existing_publication_id'] && ($p['authorship']['status'] ?? '') !== 'clean'
            );
        @endphp

        @include('filament.pages.partials.scopus-bulk-bar', [
            'group' => 'attention',
            'pending' => count(array_filter($attentionPapers, fn($p) => ($p['decision'] ?? '') !== 'imported')),
            'actions' => [
                ['decision' => 'approve', 'label' => 'Approve update for selected', 'class' => 'bg-success-100 text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-300 dark:hover:bg-success-900'],
                ['decision' => 'ignore', 'label' => 'Ignore selected', 'class' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'],
            ],
        ])

        <div class="fms-review-table-wrapper">
            <div class="overflow-x-auto fms-tab-content-scroll fms-custom-scroll">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th class="w-8 text-center">
                                <input type="checkbox"
                                    @change="toggleAll('attention', $event.target.checked)"
                                    :checked="allPicked('attention')"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                            </th>
                            <th>Scopus Title</th>
                            <th>Problem</th>
                            <th>What differs</th>
                            <th>First author</th>
                            <th>Scopus Authors</th>
                            <th>Our Stored Authors</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attentionPapers as $pKey => $paper)
                            @php
                                $authorship = $paper['authorship'] ?? [];
                                $status = $authorship['status'] ?? '';
                                [$verdictLabel, $verdictClass] = $attentionVerdicts[$status]
                                    ?? ['Discrepancy', 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'];
                                $scopusFirst = $authorship['scopus_first_author'] ?? null;
                                $ourFirst = $authorship['our_first_author'] ?? null;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="text-center align-middle">
                                    @if(($paper['decision'] ?? '') !== 'imported')
                                        <input type="checkbox"
                                            data-decision-key="{{ $pKey }}"
                                            @change="toggle('attention', $event.target.dataset.decisionKey)"
                                            :checked="picked.attention.includes($el.dataset.decisionKey)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                    @endif
                                </td>
                                <td class="font-medium text-gray-900 dark:text-gray-100">
                                    <div class="max-w-[230px]">
                                        {{ $paper['title'] }}
                                        <div class="text-[10px] text-gray-400 font-mono mt-0.5">DB #{{ $paper['existing_publication_id'] }}</div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="{{ $verdictClass }} px-2.5 py-1 rounded-md font-semibold text-[11px]">
                                        {{ $verdictLabel }}
                                    </span>
                                </td>
                                <td>
                                    <div class="max-w-[260px] space-y-1.5">
                                        <div class="text-[11px] text-gray-700 dark:text-gray-300 leading-snug">
                                            {{ $authorship['note'] ?? 'Discrepancy' }}
                                        </div>

                                        {{-- Named one by one as well as counted, so a row can be judged
                                             without opening the publication to see who is meant. --}}
                                        @if(!empty($authorship['missing']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($authorship['missing'] as $missingName)
                                                    <span class="bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300 px-1.5 py-0.5 rounded text-[10px] font-medium">
                                                        + {{ $missingName }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Stated, not flagged: a teacher Scopus does not name at
                                             Daffodil is often legitimately on the paper. --}}
                                        @if(!empty($authorship['extra_here']))
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug">
                                                Credited here but not named by Scopus:
                                                {{ implode('; ', $authorship['extra_here']) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($scopusFirst || $ourFirst)
                                        {{-- Both names, side by side: a disagreement about who came
                                             first decides the share of the incentive. --}}
                                        <div class="max-w-[190px] space-y-1 text-[11px] {{ $status === 'first-author-differs' ? 'text-danger-700 dark:text-danger-300 font-medium' : 'text-gray-600 dark:text-gray-400' }}">
                                            <div>
                                                <span class="text-[9px] uppercase tracking-wide text-gray-400 dark:text-gray-500 block">Scopus</span>
                                                {{ $scopusFirst ?: '—' }}
                                            </div>
                                            <div>
                                                <span class="text-[9px] uppercase tracking-wide text-gray-400 dark:text-gray-500 block">Ours</span>
                                                {{ $ourFirst ?: '—' }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-gray-600 dark:text-gray-400"><div class="max-w-[200px]">{{ $paper['all_authors'] }}</div></td>
                                <td class="text-gray-600 dark:text-gray-400"><div class="max-w-[200px]">{{ $paper['our_authors'] ?: 'None' }}</div></td>
                                <td class="whitespace-nowrap">
                                    @if(($paper['decision'] ?? '') === 'imported')
                                        <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-bold text-[11px] inline-flex items-center gap-1">
                                            <span>✓ Updated</span>
                                            @if(!empty($paper['existing_publication_id']))
                                                <span class="font-mono">#{{ $paper['existing_publication_id'] }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <select
                                            name="decisions[{{ $pKey }}]"
                                            data-decision-group="attention"
                                            data-decision-key="{{ $pKey }}"
                                            @change="$wire.updatePaperDecision(importId, $event.target.dataset.decisionKey, $event.target.value)"
                                            class="text-xs bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-2.5 py-1 focus:ring-primary-500 font-medium">
                                            <option value="pending" {{ ($paper['decision'] ?? '') === '' || ($paper['decision'] ?? '') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                                            <option value="approve" {{ in_array(($paper['decision'] ?? ''), ['approve', 'import', 'yes']) ? 'selected' : '' }}>Approve Update</option>
                                            <option value="ignore" {{ ($paper['decision'] ?? '') === 'ignore' ? 'selected' : '' }}>Ignore</option>
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-6 text-center text-gray-500 dark:text-gray-400">No papers needing attention.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 4: Not in Our System -->
    <div x-show="activeTab === 'new'" class="space-y-4" x-cloak>
        @php
            $newPapers = array_filter($payload['papers'] ?? [], fn($p) => !$p['existing_publication_id']);
        @endphp

        @include('filament.pages.partials.scopus-bulk-bar', [
            'group' => 'new',
            'pending' => count(array_filter($newPapers, fn($p) => ($p['decision'] ?? '') !== 'imported')),
            'actions' => [
                ['decision' => 'approve', 'label' => 'Approve (import) selected', 'class' => 'bg-success-100 text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-300 dark:hover:bg-success-900'],
                ['decision' => 'reject', 'label' => 'Reject selected', 'class' => 'bg-danger-100 text-danger-800 hover:bg-danger-200 dark:bg-danger-900/50 dark:text-danger-300 dark:hover:bg-danger-900'],
            ],
        ])

        <div class="fms-review-table-wrapper">
            <div class="overflow-x-auto fms-tab-content-scroll fms-custom-scroll">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th class="w-8 text-center">
                                <input type="checkbox"
                                    @change="toggleAll('new', $event.target.checked)"
                                    :checked="allPicked('new')"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                            </th>
                            <th>Scopus Title</th>
                            <th>Year / DOI</th>
                            <th>Journal / Source</th>
                            <th>Authors</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($newPapers as $pKey => $paper)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="text-center align-middle">
                                    @if(($paper['decision'] ?? '') !== 'imported')
                                        <input type="checkbox"
                                            data-decision-key="{{ $pKey }}"
                                            @change="toggle('new', $event.target.dataset.decisionKey)"
                                            :checked="picked.new.includes($el.dataset.decisionKey)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                    @endif
                                </td>
                                <td class="font-medium text-gray-900 dark:text-gray-100 max-w-[280px]">{{ $paper['title'] }}</td>
                                <td>
                                    <div class="font-bold">{{ $paper['year'] }}</div>
                                    <div class="text-[10px] text-gray-400 truncate max-w-[140px]">{{ $paper['doi'] ?: 'No DOI' }}</div>
                                </td>
                                <td class="text-gray-600 dark:text-gray-400 max-w-[180px] truncate" title="{{ $paper['source_title'] }}">{{ $paper['source_title'] }}</td>
                                <td class="text-gray-600 dark:text-gray-400 max-w-[200px] truncate" title="{{ $paper['all_authors'] }}">
                                    {{ $paper['all_authors'] }}
                                </td>
                                <td class="whitespace-nowrap">
                                    @if(($paper['decision'] ?? '') === 'imported')
                                        <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-bold text-[11px] inline-flex items-center gap-1">
                                            <span>✓ Imported</span>
                                            @if(!empty($paper['existing_publication_id']))
                                                <span class="font-mono">#{{ $paper['existing_publication_id'] }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <select
                                            name="decisions[{{ $pKey }}]"
                                            data-decision-group="new"
                                            data-decision-key="{{ $pKey }}"
                                            @change="$wire.updatePaperDecision(importId, $event.target.dataset.decisionKey, $event.target.value)"
                                            class="text-xs bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-2.5 py-1 focus:ring-primary-500 font-medium">
                                            <option value="pending" {{ ($paper['decision'] ?? '') === '' || ($paper['decision'] ?? '') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                                            <option value="approve" {{ in_array(($paper['decision'] ?? ''), ['approve', 'import', 'yes']) ? 'selected' : '' }}>Approve (Import)</option>
                                            <option value="reject" {{ ($paper['decision'] ?? '') === 'reject' ? 'selected' : '' }}>Reject</option>
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500 dark:text-gray-400">No new publications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 5: Imported in this Batch -->
    <div x-show="activeTab === 'imported'" class="space-y-6 fms-tab-content-scroll fms-custom-scroll pr-1" x-cloak>
        @php
            $importedPapersList = array_filter($payload['papers'] ?? [], fn($p) => ($p['decision'] ?? '') === 'imported');
            $importedPeopleList = array_filter($payload['people'] ?? [], fn($p) => ($p['decision'] ?? '') === 'imported');
        @endphp

        <!-- Section 1: Imported Publications (Same format as Not in Our System) -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider flex items-center gap-1.5">
                    <span>📜 Imported Publications</span>
                    <span class="bg-success-100 text-success-800 dark:bg-success-900/60 dark:text-success-200 text-xs px-2 py-0.5 rounded-full font-bold">
                        {{ count($importedPapersList) }}
                    </span>
                </h4>
            </div>

            <div class="fms-review-table-wrapper">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th>Scopus Title</th>
                            <th>Year / DOI</th>
                            <th>Journal / Source</th>
                            <th>Authors</th>
                            <th>Decision / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($importedPapersList as $pKey => $paper)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="font-medium text-gray-900 dark:text-gray-100 max-w-[280px]">{{ $paper['title'] }}</td>
                                <td>
                                    <div class="font-bold">{{ $paper['year'] }}</div>
                                    <div class="text-[10px] text-gray-400 truncate max-w-[140px]">{{ $paper['doi'] ?: 'No DOI' }}</div>
                                </td>
                                <td class="text-gray-600 dark:text-gray-400 max-w-[180px] truncate" title="{{ $paper['source_title'] }}">{{ $paper['source_title'] }}</td>
                                <td class="text-gray-600 dark:text-gray-400 max-w-[200px] truncate" title="{{ $paper['all_authors'] }}">
                                    {{ $paper['all_authors'] }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-bold text-[11px] inline-flex items-center gap-1">
                                        <span>✓ Imported</span>
                                        @if(!empty($paper['existing_publication_id']))
                                            <span class="font-mono">#{{ $paper['existing_publication_id'] }}</span>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center text-gray-500 dark:text-gray-400 text-xs">No publications imported in this batch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Imported Teacher Bindings (Same format as People) -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider flex items-center gap-1.5">
                    <span>👥 Bound Teacher Author IDs</span>
                    <span class="bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200 text-xs px-2 py-0.5 rounded-full font-bold">
                        {{ count($importedPeopleList) }}
                    </span>
                </h4>
            </div>

            <div class="fms-review-table-wrapper">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th>Scopus Name</th>
                            <th>Scopus Author ID</th>
                            <th>Papers</th>
                            <th>Matched Teacher / Author</th>
                            <th>Confidence</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($importedPeopleList as $pKey => $person)
                            @php
                                $isTeacherMatch = !empty($person['teacher_name']) || !empty($person['teacher_id']) || ($person['match_kind'] ?? '') === 'teacher';
                                $isAuthorMatch = !$isTeacherMatch && (!empty($person['author_name']) || !empty($person['author_id']) || ($person['match_kind'] ?? '') === 'author');
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="font-medium text-gray-900 dark:text-gray-100">{{ $person['name'] }}</td>
                                <td class="font-mono text-gray-600 dark:text-gray-400">{{ $person['scopus_id'] ?: '—' }}</td>
                                <td class="font-bold">{{ $person['papers'] }}</td>
                                <td>
                                    @if($isTeacherMatch)
                                        <span class="inline-flex items-center gap-1 font-bold text-primary-600 dark:text-primary-400">
                                            #{{ $person['teacher_id'] }} {{ $person['teacher_name'] }}
                                        </span>
                                    @elseif($isAuthorMatch)
                                        <span class="inline-flex items-center gap-1 font-bold text-purple-600 dark:text-purple-400">
                                            #{{ $person['author_id'] }} {{ $person['author_name'] }}
                                            <span class="text-[9px] uppercase px-1 py-0.2 bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-200 rounded font-semibold ml-0.5">Author Table</span>
                                        </span>
                                    @else
                                        <span class="text-gray-400 italic">Not matched</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if(($person['confidence'] ?? '') === 'certain') bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300
                                        @elseif(($person['confidence'] ?? '') === 'likely') bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 @endif">
                                        {{ $person['confidence'] ?? 'none' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-bold text-[11px]">
                                        ✓ Scopus ID Bound
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500 dark:text-gray-400 text-xs">No teacher Scopus IDs bound in this batch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 6: People -->
    <div x-show="activeTab === 'people'" class="space-y-4" x-cloak>
        @php
            $peopleList = $payload['people'] ?? [];
            $teacherMatchCount = count(array_filter($peopleList, function($p) {
                return !empty($p['teacher_name']) || !empty($p['teacher_id']) || ($p['match_kind'] ?? '') === 'teacher';
            }));
            $authorMatchCount = count(array_filter($peopleList, function($p) {
                $isTeacher = !empty($p['teacher_name']) || !empty($p['teacher_id']) || ($p['match_kind'] ?? '') === 'teacher';
                $isAuthor = !empty($p['author_name']) || !empty($p['author_id']) || ($p['match_kind'] ?? '') === 'author';
                return !$isTeacher && $isAuthor;
            }));
            $notMatchedCount = count(array_filter($peopleList, function($p) {
                $isTeacher = !empty($p['teacher_name']) || !empty($p['teacher_id']) || ($p['match_kind'] ?? '') === 'teacher';
                $isAuthor = !empty($p['author_name']) || !empty($p['author_id']) || ($p['match_kind'] ?? '') === 'author';
                return !$isTeacher && !$isAuthor;
            }));
            $totalPeopleCount = count($peopleList);
        @endphp

        @include('filament.pages.partials.scopus-bulk-bar', [
            'group' => 'people',
            'pending' => count(array_filter($payload['people'] ?? [], fn($p) => ($p['decision'] ?? '') !== 'imported')),
            'actions' => [
                ['decision' => 'approve', 'label' => 'Bind Scopus ID for selected', 'class' => 'bg-success-100 text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-300 dark:hover:bg-success-900'],
                ['decision' => 'skip', 'label' => 'Skip selected', 'class' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'],
            ],
        ])

        {{-- Filter & Counters Bar for People --}}
        <div class="fms-filter-bar">
            <div class="fms-filter-title">
                <svg class="fms-icon-sm" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter:
            </div>

            <div class="fms-filter-group">
                {{-- All --}}
                <button type="button"
                    @click="peopleFilter = 'all'"
                    :class="peopleFilter === 'all' ? 'active-all' : ''"
                    class="fms-filter-pill">
                    <span>🌐 All People</span>
                    <span class="fms-filter-num">{{ $totalPeopleCount }}</span>
                </button>

                {{-- Teacher Match --}}
                <button type="button"
                    @click="peopleFilter = 'teacher'"
                    :class="peopleFilter === 'teacher' ? 'active-teacher' : ''"
                    class="fms-filter-pill">
                    <span>🎓 Match with Teacher Table</span>
                    <span class="fms-filter-num">{{ $teacherMatchCount }}</span>
                </button>

                {{-- Author Match --}}
                <button type="button"
                    @click="peopleFilter = 'author'"
                    :class="peopleFilter === 'author' ? 'active-author' : ''"
                    class="fms-filter-pill">
                    <span>👤 Match with Author Table</span>
                    <span class="fms-filter-num">{{ $authorMatchCount }}</span>
                </button>

                {{-- Not Matched --}}
                <button type="button"
                    @click="peopleFilter = 'unmatched'"
                    :class="peopleFilter === 'unmatched' ? 'active-unmatched' : ''"
                    class="fms-filter-pill">
                    <span>❓ Not Matched</span>
                    <span class="fms-filter-num">{{ $notMatchedCount }}</span>
                </button>
            </div>
        </div>

        <div class="fms-review-table-wrapper">
            <div class="overflow-x-auto fms-tab-content-scroll fms-custom-scroll">
                <table class="fms-review-table">
                    <thead>
                        <tr>
                            <th class="w-8 text-center">
                                <input type="checkbox"
                                    @change="toggleAll('people', $event.target.checked)"
                                    :checked="allPicked('people')"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                            </th>
                            <th>Scopus Name</th>
                            <th>Scopus Author ID</th>
                            <th>Papers</th>
                            <th>Matched Teacher / Author</th>
                            <th>Confidence</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payload['people'] ?? [] as $pKey => $person)
                            @php
                                $isTeacherMatch = !empty($person['teacher_name']) || !empty($person['teacher_id']) || ($person['match_kind'] ?? '') === 'teacher';
                                $isAuthorMatch = !$isTeacherMatch && (!empty($person['author_name']) || !empty($person['author_id']) || ($person['match_kind'] ?? '') === 'author');
                                $rowMatchKind = $isTeacherMatch ? 'teacher' : ($isAuthorMatch ? 'author' : 'unmatched');
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition"
                                x-show="peopleFilter === 'all' || peopleFilter === '{{ $rowMatchKind }}'"
                                x-cloak>
                                <td class="text-center align-middle">
                                    @if(($person['decision'] ?? '') !== 'imported')
                                        <input type="checkbox"
                                            data-decision-key="{{ $pKey }}"
                                            @change="toggle('people', $event.target.dataset.decisionKey)"
                                            :checked="picked.people.includes($el.dataset.decisionKey)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                    @endif
                                </td>
                                <td class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $person['name'] }}

                                    {{-- The export never said this person was ours; only their name
                                         suggests it. Worth knowing before binding an identifier. --}}
                                    @if(($person['standing'] ?? '') === 'affiliated-elsewhere')
                                        <div class="mt-1">
                                            <span class="bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300 px-1.5 py-0.5 rounded text-[10px] font-semibold">
                                                Affiliation says elsewhere
                                            </span>
                                            @if(!empty($person['other_affiliations']))
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 max-w-[220px] leading-snug">
                                                    {{ implode('; ', $person['other_affiliations']) }}
                                                </div>
                                            @endif
                                        </div>
                                    @elseif(($person['standing'] ?? '') === 'identified-here')
                                        <div class="mt-1">
                                            <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-1.5 py-0.5 rounded text-[10px] font-semibold">
                                                Known Scopus ID, affiliation elsewhere
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="font-mono text-gray-600 dark:text-gray-400">{{ $person['scopus_id'] ?: '—' }}</td>
                                <td class="font-bold">{{ $person['papers'] }}</td>
                                <td>
                                    @if($isTeacherMatch)
                                        <span class="fms-match-badge fms-match-badge-teacher">
                                            <svg class="fms-icon-sm" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                            </svg>
                                            #{{ $person['teacher_id'] }} {{ $person['teacher_name'] }}
                                        </span>
                                    @elseif($isAuthorMatch)
                                        <span class="fms-match-badge fms-match-badge-author">
                                            <svg class="fms-icon-sm" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            #{{ $person['author_id'] }} {{ $person['author_name'] }} (Author Table)
                                        </span>
                                    @else
                                        <span class="fms-match-unmatched">
                                            <svg class="fms-icon-sm" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                            Not matched
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if(($person['confidence'] ?? '') === 'certain') bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300
                                        @elseif(($person['confidence'] ?? '') === 'likely') bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 @endif">
                                        {{ $person['confidence'] ?? 'none' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if(($person['decision'] ?? '') === 'imported')
                                        <span class="bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-300 px-2.5 py-1 rounded font-bold text-[11px]">
                                            ✓ Bound
                                        </span>
                                    @else
                                        <select
                                            name="people_decisions[{{ $pKey }}]"
                                            data-decision-group="people"
                                            data-decision-key="{{ $pKey }}"
                                            @change="$wire.updatePersonDecision(importId, $event.target.dataset.decisionKey, $event.target.value)"
                                            class="text-xs bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-2.5 py-1 focus:ring-primary-500 font-medium">
                                            <option value="pending" {{ ($person['decision'] ?? '') === '' || ($person['decision'] ?? '') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                                            <option value="approve" {{ in_array(($person['decision'] ?? ''), ['approve', 'yes', '1']) ? 'selected' : '' }}>Bind Scopus ID</option>
                                            <option value="skip" {{ ($person['decision'] ?? '') === 'skip' ? 'selected' : '' }}>Skip</option>
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-gray-500 dark:text-gray-400">No people records extracted.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
