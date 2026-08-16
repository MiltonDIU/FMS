<style>
    .fms-review-container {
        --fms-line: #d1d5db;
        --fms-head: #f3f4f6;
        --fms-muted: #4b5563;
        --fms-strong: #111827;
        --fms-bg: #ffffff;
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
</style>

<div x-data="{
    activeTab: 'summary',
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

    keys(group) {
        return this.rows(group).map((el) => el.dataset.decisionKey);
    },

    allPicked(group) {
        const total = this.keys(group).length;

        return total > 0 && this.picked[group].length === total;
    },

    toggle(group, key) {
        const at = this.picked[group].indexOf(key);

        at === -1 ? this.picked[group].push(key) : this.picked[group].splice(at, 1);
    },

    toggleAll(group, checked) {
        this.picked[group] = checked ? this.keys(group) : [];
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
                            <th>Matched Teacher</th>
                            <th>Confidence</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($importedPeopleList as $pKey => $person)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="font-medium text-gray-900 dark:text-gray-100">{{ $person['name'] }}</td>
                                <td class="font-mono text-gray-600 dark:text-gray-400">{{ $person['scopus_id'] ?: '—' }}</td>
                                <td class="font-bold">{{ $person['papers'] }}</td>
                                <td>
                                    @if($person['teacher_name'])
                                        <span class="font-bold text-primary-600 dark:text-primary-400">
                                            #{{ $person['teacher_id'] }} {{ $person['teacher_name'] }}
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
        @include('filament.pages.partials.scopus-bulk-bar', [
            'group' => 'people',
            'pending' => count(array_filter($payload['people'] ?? [], fn($p) => ($p['decision'] ?? '') !== 'imported')),
            'actions' => [
                ['decision' => 'approve', 'label' => 'Bind Scopus ID for selected', 'class' => 'bg-success-100 text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-300 dark:hover:bg-success-900'],
                ['decision' => 'skip', 'label' => 'Skip selected', 'class' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'],
            ],
        ])

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
                            <th>Matched Teacher</th>
                            <th>Confidence</th>
                            <th>Decision / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payload['people'] ?? [] as $pKey => $person)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
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
                                    @if($person['teacher_name'])
                                        <span class="font-bold text-primary-600 dark:text-primary-400">
                                            #{{ $person['teacher_id'] }} {{ $person['teacher_name'] }}
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
