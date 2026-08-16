<?php

namespace App\Services\Scopus;

use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads a Scopus export and works out what it means for us.
 *
 * The step the registrar's office called "convert": it changes nothing, it
 * produces two things to look at — the papers, and the people. Applying any of
 * it is a separate job that does not exist yet.
 *
 * The shape of the source is what makes this fiddly. One row is one paper, and
 * its authors are three parallel semicolon-separated lists — full names,
 * Scopus ids, and affiliations — that line up by position. They do line up: in
 * the July export all 872 rows have the same count in each. Only some of those
 * positions are ours; a paper with twelve authors may have one of ours.
 */
class ScopusAnalysis
{
    /** Scopus wrote our own institution against this author. */
    public const AFFILIATED_HERE = 'affiliated-here';

    /**
     * Scopus wrote somebody else's institution, but the author identifier is
     * one we have already bound to a person here.
     */
    public const IDENTIFIED_HERE = 'identified-here';

    /**
     * Scopus wrote somebody else's institution and the only thing tying this
     * author to us is their name. A candidate for review, never a match.
     */
    public const AFFILIATED_ELSEWHERE = 'affiliated-elsewhere';

    /** Best first, for deciding a person who appears under several addresses. */
    protected const STANDING_ORDER = [
        self::AFFILIATED_HERE => 0,
        self::IDENTIFIED_HERE => 1,
        self::AFFILIATED_ELSEWHERE => 2,
    ];

    public function __construct(
        protected ScopusFileReader $reader,
        protected AffiliationMatcher $affiliations,
        protected RecordResolver $resolver,
    ) {}

    /**
     * @return array{papers: Collection, people: Collection, summary: array<string, mixed>}
     */
    public function run(string $path, ?MatchingOptions $options = null): array
    {
        $options ??= new MatchingOptions;

        // The two matchers hold indexes built at construction, so a run with
        // different rules gets its own rather than mutating a shared one.
        $this->affiliations = new AffiliationMatcher($options);
        $this->resolver = new RecordResolver($options);

        $papers = [];
        $people = [];

        $skippedNoDiuAuthor = 0;
        $unpaired = 0;

        foreach ($this->reader->rows($path) as $row) {
            $title = (string) ($row['Title'] ?? '');

            $names = $this->split($row['Author full names'] ?? '');
            $ids = $this->split($row['Author(s) ID'] ?? '');
            $segments = $this->split($row['Authors with affiliations'] ?? '');

            // Where the columns disagree the positions cannot be trusted, so the
            // author side of the row is dropped rather than guessed at. The
            // paper is still reported.
            $paired = count($names) > 0 && count($names) === count($segments);

            if (! $paired && $segments !== []) {
                $unpaired++;
            }

            $email = $this->emailIn((string) ($row['Correspondence Address'] ?? ''));

            $ours = [];

            foreach ($segments as $position => $segment) {
                $name = $paired ? ($names[$position] ?? '') : $this->nameFromSegment($segment);
                $name = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $name));

                if ($name === '') {
                    continue;
                }

                $scopusId = $paired ? ($ids[$position] ?? null) : null;
                $scopusId = filled($scopusId) ? trim($scopusId) : $this->idInName($names[$position] ?? '');

                /*
                 * Affiliation classifies rather than gates.
                 *
                 * It used to be a plain `continue`, which meant a teacher who
                 * published under a previous employer or a collaborator's
                 * address was never seen at all — not matched and not
                 * reported, just gone. Three ways in now, in descending order
                 * of how much they can be trusted:
                 *
                 *   - the affiliation is ours, as before;
                 *   - it is not, but we have already recorded this Scopus
                 *     identifier against somebody here, which no affiliation
                 *     can contradict;
                 *   - it is not, and the run was told to consider such names
                 *     anyway — a candidate, kept apart from the rest until
                 *     resolution says whether it is anybody we know.
                 */
                $ourAffiliation = $this->affiliations->isOurs($segment);
                $recordedId = ! $ourAffiliation && $this->resolver->hasRecordedScopusId($scopusId);

                if (! $ourAffiliation && ! $recordedId) {
                    if (! $options->includeAuthorsAffiliatedElsewhere) {
                        continue;
                    }

                    // Nothing but the name is vouching for this one, so the name
                    // has to be worth something.
                    if ($this->tooAbbreviatedToJudge($name)) {
                        continue;
                    }
                }

                $standing = match (true) {
                    $ourAffiliation => self::AFFILIATED_HERE,
                    $recordedId => self::IDENTIFIED_HERE,
                    default => self::AFFILIATED_ELSEWHERE,
                };

                /*
                 * A foreign affiliation says nothing about our own structure.
                 *
                 * Resolving "University of Dhaka, Department of Physics" against
                 * our departments would file that person under our Physics, and
                 * the department is then used to break ties between namesakes.
                 */
                $unit = $ourAffiliation
                    ? $this->affiliations->resolve($segment)
                    : ['faculty' => null, 'department' => null, 'unit' => null, 'source' => 'elsewhere'];

                // The correspondence address belongs to one author. Attributing
                // it to everyone would put one person's email on a dozen
                // records, so it is only kept when the surname agrees.
                $theirEmail = $this->emailBelongsTo($email, $name) ? $email : null;

                $key = $scopusId ?: 'name:' . $this->resolver->nameKey($name);

                if (! isset($people[$key])) {
                    $people[$key] = [
                        'scopus_id' => $scopusId,
                        'name' => $name,
                        'email' => $theirEmail,
                        'units' => [],
                        'papers' => 0,
                        'faculty' => $unit['faculty'],
                        'department' => $unit['department'],
                        'unit_source' => $unit['source'],
                        'standing' => $standing,
                        'other_affiliations' => [],
                    ];
                }

                /*
                 * One person, many papers, not always the same address.
                 *
                 * Somebody who appears once under our name and twice under a
                 * former employer's is ours; the best standing across their
                 * appearances is the one that counts.
                 *
                 * The name moves with the standing, and has to. A person is
                 * keyed by Scopus identifier and stored under whatever spelling
                 * first created them, so admitting foreign affiliations let a
                 * row reading "Sohel M.S." claim the key before the row reading
                 * "Sohel, Md. Salman" got to it — and the abbreviated form
                 * matches no teacher, so turning the switch on lost a match
                 * that was working. The line naming our own institution is the
                 * one to believe.
                 */
                if (self::STANDING_ORDER[$standing] < self::STANDING_ORDER[$people[$key]['standing']]) {
                    $people[$key]['standing'] = $standing;
                    $people[$key]['name'] = $name;
                }

                if (! $ourAffiliation && ($other = $this->institutionIn($segment)) !== null) {
                    $people[$key]['other_affiliations'][$other] = true;
                }

                $people[$key]['papers']++;
                $people[$key]['email'] ??= $theirEmail;

                if ($unit['unit']) {
                    $people[$key]['units'][$unit['unit']] = true;
                }

                // A person's faculty is taken from the first affiliation that
                // resolved to one; later papers do not overwrite a known answer.
                if (! $people[$key]['faculty'] && $unit['faculty']) {
                    $people[$key]['faculty'] = $unit['faculty'];
                    $people[$key]['department'] = $unit['department'];
                    $people[$key]['unit_source'] = $unit['source'];
                }

                $ours[] = [
                    'name' => $name,
                    'scopus_id' => $scopusId,
                    'key' => $key,
                    'position' => $position,
                    'standing' => $standing,
                ];
            }

            if ($ours === []) {
                $skippedNoDiuAuthor++;

                continue;
            }

            $eid = trim((string) ($row['EID'] ?? ''));
            $doi = trim((string) ($row['DOI'] ?? ''));
            $identity = $eid !== '' ? 'eid:' . $eid : ($doi !== '' ? 'doi:' . strtolower($doi) : 'title:' . $this->resolver->normaliseTitle($title));

            // Without deduplication every copy is its own record, so the key
            // has to differ. The DoR workbook stacks ten cumulative downloads.
            if (! $options->deduplicateByIdentifier) {
                $identity .= '#' . count($papers);
            }

            if (isset($papers[$identity])) {
                $papers[$identity]['seen']++;

                continue;
            }

            $match = $this->resolver->resolvePublication($title, $doi, $eid);

            $papers[$identity] = [
                'title' => $title,
                'year' => (string) ($row['Year'] ?? ''),
                'doi' => $doi,
                'eid' => $eid,
                'source_title' => (string) ($row['Source title'] ?? ''),
                'link' => (string) ($row['Link'] ?? ''),
                'document_type' => (string) ($row['Document Type'] ?? ''),
                'cited_by' => (string) ($row['Cited by'] ?? ''),
                'all_authors' => implode('; ', array_map(
                    fn ($n) => trim(preg_replace('/\s*\(\d+\)\s*$/', '', $n)),
                    $names,
                )),
                /*
                 * The identifiers the names were stripped of, kept beside them.
                 *
                 * "Author full names" arrives as "Murshid, Md Mahmud (57190123)"
                 * and the id is taken off so the column reads as a list of
                 * people. That left the importers with nothing to bind: both of
                 * them parse an id back out of all_authors, which by then has
                 * none, so every author went in without one and the
                 * scopus_author_ids table stayed empty through 793 papers.
                 *
                 * Aligned by position with all_authors, and written only when
                 * the two lists are the same length — without that the
                 * positions mean nothing and an id would be filed against the
                 * wrong person, which the unique constraint would then make
                 * permanent.
                 */
                'all_author_ids' => count($ids) === count($names)
                    ? implode('; ', array_map(fn ($id) => trim((string) $id), $ids))
                    : '',
                'all_author_affiliations' => count($segments) === count($names)
                    ? implode('; ', array_map(fn ($segment) => trim((string) $segment), $segments))
                    : '',
                'diu_authors' => $ours,
                'publication' => $match['publication'],
                'confidence' => $match['confidence'],
                'match_basis' => $match['basis'],
                // Whether the paper's first-listed author is one of ours, which
                // is what makes a disagreement about first authorship meaningful.
                'first_position_is_ours' => ($ours[0]['position'] ?? null) === 0,
                'seen' => 1,
            ];
        }

        $papers = collect($papers);

        /*
         * Who our own copies of these papers already credit.
         *
         * Collected per person across all of their papers, so an ambiguous name
         * can be settled against the handful of teachers already on the work
         * rather than against all 2,000. Of the 328 papers carrying an ambiguous
         * author, 169 already name them.
         */
        $paperTeacherIds = $options->usePaperAuthorsTiebreak
            ? $this->teachersAlreadyCredited($papers)
            : [];

        $people = collect($people)->map(function (array $person, string $key) use ($paperTeacherIds) {
            $resolved = $this->resolver->resolveAuthor(
                $person['name'],
                $person['email'],
                $person['department']?->id,
                $paperTeacherIds[$key] ?? [],
                $person['scopus_id'],
            );

            return array_merge($person, [
                'units' => array_keys($person['units']),
                'other_affiliations' => array_keys($person['other_affiliations']),
                'match' => $resolved,
            ]);
        });

        /*
         * Candidates that turned out to be nobody are dropped here.
         *
         * A name under a foreign affiliation was only ever admitted on the
         * chance that it was one of ours; once resolution has looked, most of
         * them are not, and keeping them would drown the review and wreck every
         * count — 6,788 slots in the August export against 214 worth showing.
         *
         * An external-author match is not enough either: an outsider is an
         * outsider whether or not we happen to hold a row for them.
         */
        [$people, $papers, $skippedNoDiuAuthor] = $this->dropUnprovenCandidates(
            $people,
            $papers,
            $skippedNoDiuAuthor,
        );

        /*
         * Now that every person is resolved, say how our own record of each
         * paper differs from Scopus's — who is missing, and whether the two
         * agree on who wrote it first.
         */
        $comparison = new AuthorshipComparison($this->resolver);
        $comparison->prepare($papers);

        $papers = $papers->map(function (array $paper) use ($comparison, $people) {
            $paper['authorship'] = $comparison->compare($paper, $people);
            $paper['our_authors'] = $comparison->creditedOn($paper['publication']);

            return $paper;
        });

        // Candidates for the names nobody could place, so a reviewer has
        // something to choose between rather than an empty cell.
        $people = $people->map(function (array $person) {
            $match = $person['match'];

            $person['candidates'] = in_array($match['confidence'], [RecordResolver::AMBIGUOUS, RecordResolver::NONE], true)
                ? $this->resolver->candidatesFor($person['name'])
                    ->map(fn ($teacher) => '#' . $teacher->id . ' ' . $teacher->full_name
                        . ($teacher->department ? ' — ' . $teacher->department->name : ''))
                    ->all()
                : [];

            return $person;
        });

        return [
            'papers' => $papers,
            'people' => $people,
            'summary' => array_merge(
                $this->summarise($papers, $people, $skippedNoDiuAuthor, $unpaired),
                [
                    'options' => $options->toArray(),
                    'authorship' => $papers->countBy(fn ($paper) => $paper['authorship']['status'])->all(),
                    'publication_basis' => $papers->countBy(fn ($paper) => $paper['match_basis'])->all(),
                    'by_source' => $this->bySource($papers),
                ],
            ),
        ];
    }

    /**
     * Removes the foreign-affiliation candidates that resolution did not place,
     * and the author slots and papers left empty by their going.
     *
     * @return array{0: Collection, 1: Collection, 2: int}
     */
    protected function dropUnprovenCandidates(Collection $people, Collection $papers, int $skipped): array
    {
        $unproven = $people
            ->filter(fn (array $person) => $person['standing'] === self::AFFILIATED_ELSEWHERE
                && ! ($person['match']['kind'] === 'teacher' && $person['match']['teacher'] !== null))
            ->keys()
            ->flip();

        if ($unproven->isEmpty()) {
            return [$people, $papers, $skipped];
        }

        $people = $people->reject(fn (array $person, string $key) => $unproven->has($key));

        $papers = $papers->map(function (array $paper) use ($unproven) {
            $paper['diu_authors'] = array_values(array_filter(
                $paper['diu_authors'],
                fn (array $author) => ! $unproven->has($author['key']),
            ));

            // Recomputed, not carried over: the first-listed author of ours may
            // have been one of the names just dropped.
            $paper['first_position_is_ours'] = ($paper['diu_authors'][0]['position'] ?? null) === 0;

            return $paper;
        });

        // A paper that only ever had candidates is a paper with no author of
        // ours, which is what it would have been counted as before.
        $emptied = $papers->filter(fn (array $paper) => $paper['diu_authors'] === []);

        return [
            $people,
            $papers->reject(fn (array $paper) => $paper['diu_authors'] === []),
            $skipped + $emptied->count(),
        ];
    }

    /**
     * Whether a name is too abbreviated to stand on its own as evidence.
     *
     * Applied only to authors whose affiliation names somebody else, where the
     * name is the entire case for them being ours. Scopus writes "Ali K." and
     * "Azad, A.K.M." — a surname and some initials, which matches every teacher
     * sharing that surname. Three separate Scopus profiles, at a Saudi medical
     * college, a Chinese university and a Pakistani one, all resolved to the
     * same teacher here on exactly that basis.
     *
     * A full given name is still only a suggestion, but it is one worth putting
     * in front of a reviewer. Initials are not.
     */
    protected function tooAbbreviatedToJudge(string $name): bool
    {
        // Commas, spaces and the full stops between initials all separate; what
        // is left is either a name word or a single letter standing for one.
        $words = preg_split('/[\s.,\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $spelledOut = array_filter($words, fn (string $word) => mb_strlen($word) > 1);

        // Fewer than two words spelled out is a surname and some initials.
        return count($spelledOut) < 2;
    }

    /**
     * The institution named in an affiliation segment that is not ours.
     *
     * Shown beside a candidate so a reviewer can tell "published at Southeast
     * University while working here" from "somebody else with the same name".
     */
    public function institutionIn(string $segment): ?string
    {
        foreach (array_map('trim', explode(',', $segment)) as $piece) {
            if (preg_match('/\b(universit|institut|college|hospital|academy|laborator|research cent)/i', $piece)) {
                return $piece;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function summarise(Collection $papers, Collection $people, int $skipped, int $unpaired): array
    {
        $byKind = $people->countBy(fn ($p) => $this->bucket($p));
        $byConfidence = $people->countBy(fn ($p) => $p['match']['confidence']);

        // What each answer rested on. Shown beside the counts so a run can be
        // judged on how much of it was certain and how much was a name guess.
        $byBasis = $people->countBy(fn ($p) => $p['match']['basis']);

        return [
            'coverage' => $this->coverage($papers, $people),
            'basis' => [
                'scopus_id' => $byBasis['scopus id'] ?? 0,
                'email' => $byBasis['email'] ?? 0,
                'name' => $byBasis['name'] ?? 0,
                'name_and_department' => $byBasis['name + department'] ?? 0,
                'name_and_paper_authors' => $byBasis['name + paper authors'] ?? 0,
                'already_merged_author' => $byBasis['merged-author'] ?? 0,
                'nothing' => $byBasis['none'] ?? 0,
            ],
            'papers' => [
                'total' => $papers->count(),
                'already_here' => $papers->where('publication', '!=', null)->count(),
                'new' => $papers->whereNull('publication')->count(),
                'with_doi' => $papers->filter(fn ($p) => $p['doi'] !== '')->count(),
                'rows_without_a_diu_author' => $skipped,
                'rows_whose_columns_did_not_line_up' => $unpaired,
            ],
            'people' => [
                'total' => $people->count(),
                'teacher' => $byKind['teacher'] ?? 0,
                'external_author' => $byKind['author'] ?? 0,
                'ambiguous_people' => $byKind['ambiguous'] ?? 0,
                'looks_like_student' => $byKind['student'] ?? 0,
                'not_found' => $byKind['unknown'] ?? 0,
                'certain' => $byConfidence[RecordResolver::CERTAIN] ?? 0,
                'likely' => $byConfidence[RecordResolver::LIKELY] ?? 0,
                'ambiguous' => $byConfidence[RecordResolver::AMBIGUOUS] ?? 0,
                'with_email' => $people->filter(fn ($p) => filled($p['email']))->count(),
                'with_scopus_id' => $people->filter(fn ($p) => filled($p['scopus_id']))->count(),

                /*
                 * How each person got in. Reported because the three are worth
                 * very different amounts: the first is what the export says,
                 * the second is an identifier we bound ourselves, and the third
                 * is a name under somebody else's address and nothing more.
                 */
                'affiliated_here' => $people->where('standing', self::AFFILIATED_HERE)->count(),
                'identified_here' => $people->where('standing', self::IDENTIFIED_HERE)->count(),
                'affiliated_elsewhere' => $people->where('standing', self::AFFILIATED_ELSEWHERE)->count(),
            ],
            'units' => [
                'faculty_resolved' => $people->filter(fn ($p) => $p['faculty'] !== null)->count(),
                'department_resolved' => $people->filter(fn ($p) => $p['department'] !== null)->count(),
            ],
        ];
    }

    /**
     * For each person, the teachers our own copies of their papers already name.
     *
     * One query for the lot rather than one per paper: 1,518 of these papers are
     * already here, and asking the pivot table that many times would dominate
     * the run.
     *
     * @return array<string, array<int, int>>  person key => teacher ids
     */
    protected function teachersAlreadyCredited(Collection $papers): array
    {
        $held = $papers->filter(fn ($paper) => $paper['publication'] !== null);

        if ($held->isEmpty()) {
            return [];
        }

        $byPublication = DB::table('publication_authors')
            ->whereIn('publication_id', $held->map(fn ($paper) => $paper['publication']->id)->all())
            ->where('authorable_type', Teacher::class)
            ->get(['publication_id', 'authorable_id'])
            ->groupBy('publication_id')
            ->map(fn ($rows) => $rows->pluck('authorable_id')->map(fn ($id) => (int) $id)->all());

        $forPerson = [];

        foreach ($held as $paper) {
            $teacherIds = $byPublication[$paper['publication']->id] ?? [];

            if ($teacherIds === []) {
                continue;
            }

            foreach ($paper['diu_authors'] as $author) {
                $forPerson[$author['key']] = array_unique(
                    array_merge($forPerson[$author['key']] ?? [], $teacherIds)
                );
            }
        }

        return $forPerson;
    }

    /**
     * How much of the authorship we can already account for.
     *
     * The summary counts papers and people side by side, and the two are easy
     * to read as comparable when they are not: 1,553 papers hold 2,944 author
     * slots between them, filled by 1,257 distinct people — one of whom appears
     * on 91 of them. So "1,518 papers already here" and "447 teachers matched"
     * describe different things, and being asked why they disagree is fair.
     *
     * This measures the thing that is actually being asked: of all the author
     * slots, what share belongs to somebody we can already name.
     *
     * @return array<string, mixed>
     */
    /**
     * Where our copies came from, and how their authorship held up.
     *
     * The interesting question this answers: which import left the gaps. A
     * missing author on a record the PD export brought in is a name the import
     * could not match; the same on a record somebody entered here is a
     * different kind of problem and a different fix.
     *
     * @return array<string, array<string, int>>
     */
    protected function bySource(Collection $papers): array
    {
        $rows = [];

        foreach ($papers->filter(fn ($paper) => $paper['publication'] !== null) as $paper) {
            $publication = $paper['publication'];

            $source = match (true) {
                $publication->come_from_old_site && $publication->come_from_pd => 'Old Site + PD',
                (bool) $publication->come_from_old_site => 'Old Site',
                (bool) $publication->come_from_pd => 'PD',
                default => 'Entered here',
            };

            $rows[$source]['total'] = ($rows[$source]['total'] ?? 0) + 1;

            $key = $paper['authorship']['status'] === AuthorshipComparison::CLEAN ? 'clean' : 'needs_attention';
            $rows[$source][$key] = ($rows[$source][$key] ?? 0) + 1;
        }

        return $rows;
    }

    /**
     * Which bucket a person really falls in.
     *
     * A name that matched several teachers comes back with kind 'teacher' and
     * nobody named. Counting that as a teacher was wrong: it made the coverage
     * figure sit at 75.5% whether the tie-breakers were on or off, because the
     * people they settle were already being counted as settled.
     */
    protected function bucket(array $person): string
    {
        $match = $person['match'];

        if ($match['confidence'] === RecordResolver::AMBIGUOUS) {
            return 'ambiguous';
        }

        return match ($match['kind']) {
            'teacher' => $match['teacher'] ? 'teacher' : 'unknown',
            'author' => $match['author'] ? 'author' : 'unknown',
            default => $match['kind'],
        };
    }

    protected function coverage(Collection $papers, Collection $people): array
    {
        $kinds = $people->map(fn ($person) => $this->bucket($person));

        $slots = max($papers->sum(fn ($paper) => count($paper['diu_authors'])), 1);

        $slotsBy = [];

        foreach ($people as $person) {
            $kind = $this->bucket($person);
            $slotsBy[$kind] = ($slotsBy[$kind] ?? 0) + $person['papers'];
        }

        $known = ($slotsBy['teacher'] ?? 0) + ($slotsBy['author'] ?? 0);

        // Per paper: not whether we hold the paper, but whether we can name the
        // people on it. A paper where one of three authors is a stranger still
        // needs somebody to look at it.
        $allKnown = 0;
        $someKnown = 0;
        $noneKnown = 0;
        $withTeacher = 0;

        foreach ($papers as $paper) {
            $total = count($paper['diu_authors']);
            $namedHere = 0;
            $hasTeacher = false;

            foreach ($paper['diu_authors'] as $author) {
                $kind = $kinds[$author['key']] ?? 'unknown';

                if (in_array($kind, ['teacher', 'author'], true)) {
                    $namedHere++;
                }

                $hasTeacher = $hasTeacher || $kind === 'teacher';
            }

            $hasTeacher and $withTeacher++;

            match (true) {
                $namedHere === $total => $allKnown++,
                $namedHere > 0 => $someKnown++,
                default => $noneKnown++,
            };
        }

        return [
            'author_slots' => $slots,
            'slots_per_paper' => round($slots / max($papers->count(), 1), 1),
            'slots_teacher' => $slotsBy['teacher'] ?? 0,
            'slots_external_author' => $slotsBy['author'] ?? 0,
            'slots_ambiguous' => $slotsBy['ambiguous'] ?? 0,
            'slots_student' => $slotsBy['student'] ?? 0,
            'slots_unknown' => $slotsBy['unknown'] ?? 0,
            'slots_accounted_for' => $known,
            'percent_accounted_for' => round($known / $slots * 100, 1),
            'papers_all_authors_known' => $allKnown,
            'papers_some_authors_known' => $someKnown,
            'papers_no_authors_known' => $noneKnown,
            'papers_with_a_matched_teacher' => $withTeacher,
        ];
    }

    /** @return array<int, string> */
    protected function split(mixed $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/;\s*/', $value)),
            fn ($part) => $part !== '',
        ));
    }

    protected function emailIn(string $text): ?string
    {
        return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)
            ? mb_strtolower($m[0])
            : null;
    }

    /** The correspondence email is this author's only if the surname agrees. */
    protected function emailBelongsTo(?string $email, string $name): bool
    {
        if (blank($email)) {
            return false;
        }

        $surname = mb_strtolower(trim(explode(',', $name)[0] ?? ''));

        if (mb_strlen($surname) < 4) {
            return false;
        }

        return str_contains(strstr($email, '@', true) ?: '', mb_substr($surname, 0, 4));
    }

    /** "Murshid M.M., Daffodil ..." — the name is what precedes the first comma. */
    protected function nameFromSegment(string $segment): string
    {
        return trim(explode(',', $segment)[0] ?? '');
    }

    protected function idInName(string $name): ?string
    {
        return preg_match('/\((\d+)\)/', $name, $m) ? $m[1] : null;
    }
}
