@php
    /**
     * The stored summary, read straight off the record — no work is repeated to
     * show this, and no file has to be downloaded first.
     *
     * Styled with a real stylesheet rather than Tailwind classes: this panel has
     * no custom Filament theme, so utility classes written here are never
     * compiled and render as nothing at all.
     */
    $papers   = $import->summary['papers'] ?? [];
    $people   = $import->summary['people'] ?? [];
    $coverage = $import->summary['coverage'] ?? [];
    $units    = $import->summary['units'] ?? [];

    $slots = max($coverage['author_slots'] ?? 1, 1);

    // An HtmlString, because stat-table escapes anything that is not one.
    $swatch = fn (string $colour, string $label) => new \Illuminate\Support\HtmlString(
        '<span class="fms-swatch" style="background:' . $colour . '"></span>' . e($label)
    );

    $share = fn (int $positions) => number_format($positions / $slots * 100, 1) . '%';

    $basis = $import->summary['basis'] ?? [];

    // What this run was told to match by. Kept with the record, so a workbook
    // from last month can still explain itself.
    $options = $import->matchingOptions();
    $described = \App\Services\Scopus\MatchingOptions::describe();
    $chosen = $options->toArray();

    $bands = [
        ['slots_teacher', '#10b981', 'Our teachers'],
        ['slots_external_author', '#0ea5e9', 'External authors'],
        ['slots_student', '#fbbf24', 'Look like students'],
        ['slots_unknown', '#d1d5db', 'Cannot name'],
    ];
@endphp

<style>
    .fms-summary { display: flex; flex-direction: column; gap: 1.5rem; font-size: .875rem;
        --fms-line: #e5e7eb; --fms-soft: #f9fafb; --fms-muted: #6b7280; }
    .dark .fms-summary { --fms-line: #374151; --fms-soft: rgba(31, 41, 55, .5); --fms-muted: #9ca3af; }

    .fms-summary__panel { border: 1px solid var(--fms-line); border-radius: .5rem; padding: 1rem; }

    .fms-summary__headline { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
    .fms-summary__headline b { font-weight: 600; }
    .fms-summary__big { font-size: 1.875rem; font-weight: 700; font-variant-numeric: tabular-nums; }

    .fms-bar { display: flex; height: .75rem; margin-top: .75rem; overflow: hidden;
        border: 1px solid var(--fms-line); border-radius: 9999px; background: var(--fms-soft); }

    .fms-summary__note { margin-top: .75rem; color: var(--fms-muted); }
    .fms-summary__fine { margin-top: .5rem; font-size: .75rem; color: var(--fms-muted); }

    .fms-summary__grid { display: grid; gap: 1.5rem; }
    @media (min-width: 1024px) { .fms-summary__grid { grid-template-columns: 1fr 1fr; } }

    .fms-summary__footer { border: 1px solid var(--fms-line); border-radius: .375rem;
        background: var(--fms-soft); padding: .75rem; color: var(--fms-muted); }
</style>

<div class="fms-summary">

    {{-- The headline: the one number that answers "how much do we already have". --}}
    @if (filled($coverage))
        <div class="fms-summary__panel">
            <div class="fms-summary__headline">
                <b>Authorship we can already name</b>
                <span class="fms-summary__big">{{ $coverage['percent_accounted_for'] }}%</span>
            </div>

            <div class="fms-bar">
                @foreach ($bands as [$key, $colour, $label])
                    @php $value = $coverage[$key] ?? 0; @endphp
                    @if ($value > 0)
                        <div style="width: {{ $value / $slots * 100 }}%; background: {{ $colour }}"
                             title="{{ $label }}: {{ number_format($value) }}"></div>
                    @endif
                @endforeach
            </div>

            <p class="fms-summary__note">
                {{ number_format($coverage['slots_accounted_for']) }} of {{ number_format($slots) }}
                author positions across {{ number_format($papers['total'] ?? 0) }} papers &mdash;
                an average of {{ $coverage['slots_per_paper'] }} Daffodil authors per paper.
            </p>
        </div>
    @endif

    {{-- The table that reconciles people against papers. --}}
    @if (filled($coverage))
        <div>
            @include('filament.pages.partials.stat-table', [
                'title' => 'Who holds the authorship',
                'headers' => ['Category', 'People', 'Author positions', 'Share'],
                'rows' => [
                    [$swatch('#10b981', 'Our teachers'), $people['teacher'] ?? 0, $coverage['slots_teacher'] ?? 0, $share($coverage['slots_teacher'] ?? 0)],
                    [$swatch('#0ea5e9', 'External authors we hold'), $people['external_author'] ?? 0, $coverage['slots_external_author'] ?? 0, $share($coverage['slots_external_author'] ?? 0)],
                    [$swatch('#fbbf24', 'Look like students'), $people['looks_like_student'] ?? 0, $coverage['slots_student'] ?? 0, $share($coverage['slots_student'] ?? 0)],
                    [$swatch('#d1d5db', 'Cannot name them'), $people['not_found'] ?? 0, $coverage['slots_unknown'] ?? 0, $share($coverage['slots_unknown'] ?? 0)],
                ],
                'footer' => [
                    'Accounted for',
                    ($people['teacher'] ?? 0) + ($people['external_author'] ?? 0),
                    $coverage['slots_accounted_for'] ?? 0,
                    $coverage['percent_accounted_for'] . '%',
                ],
            ])

            {{--
                Said out loud, because reading the People and Author-positions
                columns as if they were the same thing is exactly what makes
                "1,518 papers but only 447 teachers" look wrong.
            --}}
            <p class="fms-summary__fine">
                One person writes many papers, and one paper has many authors &mdash; so the two
                number columns are not comparable. Judge coverage by the last one.
            </p>
        </div>
    @endif

    <div class="fms-summary__grid">
        @include('filament.pages.partials.stat-table', [
            'title' => 'Publications',
            'rows' => [
                'In the file' => $papers['total'] ?? 0,
                'Already in our system' => $papers['already_here'] ?? 0,
                'New to us' => $papers['new'] ?? 0,
                'Carrying a DOI' => $papers['with_doi'] ?? 0,
                'Rows with no Daffodil author' => $papers['rows_without_a_diu_author'] ?? 0,
            ],
        ])

        @if (filled($coverage))
            @include('filament.pages.partials.stat-table', [
                'title' => 'Papers, by how much of their authorship we can name',
                'rows' => [
                    'Every Daffodil author known' => $coverage['papers_all_authors_known'] ?? 0,
                    'Some known, some not' => $coverage['papers_some_authors_known'] ?? 0,
                    'None of them known' => $coverage['papers_no_authors_known'] ?? 0,
                    'At least one of our teachers on it' => $coverage['papers_with_a_matched_teacher'] ?? 0,
                ],
            ])
        @endif

        @include('filament.pages.partials.stat-table', [
            'title' => 'What each match rested on',
            'rows' => array_filter([
                'Scopus author id — cannot be wrong' => $basis['scopus_id'] ?? 0,
                'Email address — never a guess' => $basis['email'] ?? 0,
                'Name alone' => $basis['name'] ?? 0,
                'Name, settled by department' => $basis['name_and_department'] ?? 0,
                "Name, settled by the paper's own authors" => $basis['name_and_paper_authors'] ?? 0,
                'An author already merged into a teacher' => $basis['already_merged_author'] ?? 0,
                'Nothing matched' => $basis['nothing'] ?? 0,
            ], fn ($value) => $value > 0),
        ])

        @include('filament.pages.partials.stat-table', [
            'title' => 'How the people were matched',
            'rows' => [
                'Distinct people' => $people['total'] ?? 0,
                'Certain — matched by email' => $people['certain'] ?? 0,
                'Likely — matched by name' => $people['likely'] ?? 0,
                'Ambiguous — several candidates' => $people['ambiguous'] ?? 0,
                'Carrying an email address' => $people['with_email'] ?? 0,
                'Carrying a Scopus author id' => $people['with_scopus_id'] ?? 0,
            ],
        ])

        @include('filament.pages.partials.stat-table', [
            'title' => 'Faculty and department',
            'rows' => [
                'Faculty worked out' => $units['faculty_resolved'] ?? 0,
                'Department worked out' => $units['department_resolved'] ?? 0,
            ],
        ])
    </div>

    {{--
        The rules this run was told to use. Without them the numbers above are
        unexplainable: a run with the tie-breakers off produces different counts
        from the same file, and there would be no way to tell which you had.
    --}}
    @include('filament.pages.partials.stat-table', [
        'title' => 'Rules this run was told to match by',
        'headers' => ['Rule', 'Used'],
        'rows' => collect($described)->map(fn ($d, $key) => [
            $d['label'],
            ($chosen[$key] ?? false) ? 'Yes' : 'No',
        ])->values()->all(),
    ])

    <p class="fms-summary__footer">
        Nothing in the system has been changed. Download the workbook to see the publications
        and people one by one, with our suggestion beside each.
    </p>
</div>
