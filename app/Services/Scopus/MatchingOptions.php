<?php

namespace App\Services\Scopus;

use App\Helpers\Institution;

/**
 * The rules a run was told to match by.
 *
 * Chosen when the file is uploaded, stored on the import, and shown wherever
 * its results are — so anybody reading a workbook can see what produced it, and
 * two runs of the same file can be compared knowing what actually differed.
 *
 * Each one is a real switch, not a label: turning it off changes what the
 * matcher is allowed to conclude.
 */
class MatchingOptions
{
    public function __construct(
        /**
         * The Scopus author identifier against one we have already recorded.
         *
         * The only basis that cannot be wrong — it names one profile and one
         * person. Empty until a review records some, and every review makes the
         * next run need fewer name guesses.
         */
        public bool $matchByScopusId = true,

        /**
         * A paper's DOI or Scopus EID against one we have recorded.
         *
         * The same argument as the author identifier: exact, and not a guess.
         * Falls back to the title where a paper has neither recorded here.
         */
        public bool $matchPublicationsByIdentifier = true,

        /** Correspondence address against a user account or a secondary email. */
        public bool $matchByEmail = true,

        /** The distinguishing words of a name, in any order. */
        public bool $matchByName = true,

        /**
         * When a name matches several teachers, keep only the one in the
         * department Scopus named. Settles 45 of 109 ambiguous names in the
         * July export — "Rahman, Mizanur" has seven namesakes, one in Pharmacy.
         */
        public bool $useDepartmentTiebreak = true,

        /**
         * When a name matches several teachers, prefer one already credited on
         * our own copy of that paper. Of the 328 papers carrying an ambiguous
         * author, 169 already name them.
         */
        public bool $usePaperAuthorsTiebreak = true,

        /** A digit in the local part of one of our addresses suggests a student. */
        public bool $flagStudentsByEmail = true,

        /**
         * Sister institutions carry the name without being this university.
         * Off by default: counting their papers as ours overstates the output.
         */
        public bool $includeSisterInstitutions = false,

        /**
         * Consider an author whose affiliation names somebody else entirely.
         *
         * Affiliation is otherwise a gate: a name written under another
         * university's address never reaches the matcher at all. That loses
         * real people — 214 author slots in the August export carry another
         * institution's address and resolve by name to one of our teachers,
         * mostly staff publishing under a previous employer or a collaborator.
         *
         * Off by default, because a name alone under a foreign affiliation is
         * the weakest evidence in the system and there are 6,788 such slots to
         * sift. On, they arrive as their own reviewable group rather than as
         * matches — nothing is credited without somebody saying so.
         */
        public bool $includeAuthorsAffiliatedElsewhere = false,

        /**
         * The DoR workbook stacks ten cumulative downloads, so one paper can
         * arrive ten times. Off, and every copy is counted.
         */
        public bool $deduplicateByIdentifier = true,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $defaults = new self;

        return new self(
            matchByScopusId: (bool) ($data['match_by_scopus_id'] ?? $defaults->matchByScopusId),
            matchPublicationsByIdentifier: (bool) ($data['match_publications_by_identifier'] ?? $defaults->matchPublicationsByIdentifier),
            matchByEmail: (bool) ($data['match_by_email'] ?? $defaults->matchByEmail),
            matchByName: (bool) ($data['match_by_name'] ?? $defaults->matchByName),
            useDepartmentTiebreak: (bool) ($data['use_department_tiebreak'] ?? $defaults->useDepartmentTiebreak),
            usePaperAuthorsTiebreak: (bool) ($data['use_paper_authors_tiebreak'] ?? $defaults->usePaperAuthorsTiebreak),
            flagStudentsByEmail: (bool) ($data['flag_students_by_email'] ?? $defaults->flagStudentsByEmail),
            includeSisterInstitutions: (bool) ($data['include_sister_institutions'] ?? $defaults->includeSisterInstitutions),
            includeAuthorsAffiliatedElsewhere: (bool) ($data['include_authors_affiliated_elsewhere'] ?? $defaults->includeAuthorsAffiliatedElsewhere),
            deduplicateByIdentifier: (bool) ($data['deduplicate_by_identifier'] ?? $defaults->deduplicateByIdentifier),
        );
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'match_by_scopus_id' => $this->matchByScopusId,
            'match_publications_by_identifier' => $this->matchPublicationsByIdentifier,
            'match_by_email' => $this->matchByEmail,
            'match_by_name' => $this->matchByName,
            'use_department_tiebreak' => $this->useDepartmentTiebreak,
            'use_paper_authors_tiebreak' => $this->usePaperAuthorsTiebreak,
            'flag_students_by_email' => $this->flagStudentsByEmail,
            'include_sister_institutions' => $this->includeSisterInstitutions,
            'include_authors_affiliated_elsewhere' => $this->includeAuthorsAffiliatedElsewhere,
            'deduplicate_by_identifier' => $this->deduplicateByIdentifier,
        ];
    }

    /**
     * What each switch is called, and what it does, for the form and the report.
     *
     * @return array<string, array{label: string, help: string}>
     */
    public static function describe(): array
    {
        // Named rather than described, so the switch says what it will actually
        // count at this installation instead of naming Daffodil's sisters at
        // one that has none.
        $sisters = Institution::notUs();

        return [
            'match_by_scopus_id' => [
                'label' => 'Match by Scopus author id',
                'help' => 'Against identifiers already recorded for a teacher or author. Cannot be wrong — one identifier is one profile. Nothing is recorded yet, so this finds nobody until a review has been applied; from then on it grows with every review.',
            ],
            'match_publications_by_identifier' => [
                'label' => 'Match publications by DOI or Scopus EID',
                'help' => 'Exact, where we hold one. Falls back to the title, which is the weakest thing to match on — a trailing full stop or a "Retraction notice to ..." prefix is enough to miss.',
            ],
            'match_by_email' => [
                'label' => 'Match by email address',
                'help' => 'The correspondence address against a user account or a teacher\'s secondary email. Never a guess, but Scopus gives only the corresponding author\'s — 129 of 1,257 people in the July export.',
            ],
            'match_by_name' => [
                'label' => 'Match by name',
                'help' => 'The distinguishing words of a name in any order, so "Hossain, Mohammad Reyad" finds "Mohammad Reyad Hossain". Always a suggestion.',
            ],
            'use_department_tiebreak' => [
                'label' => 'Break ties using the department Scopus named',
                'help' => 'When a name matches several teachers, keep the one in that department. Settles roughly two in five ambiguous names.',
            ],
            'use_paper_authors_tiebreak' => [
                'label' => 'Break ties using the paper\'s existing authors',
                'help' => 'When a name matches several teachers, prefer one already credited on our own copy of that paper.',
            ],
            'flag_students_by_email' => [
                'label' => 'Treat a numbered ' . Institution::shortName() . ' address as a student',
                'help' => 'Students are addressed by admission number, staff by name and department. A suggestion only — staff can have digits too. The rule and the addresses it applies to are set under Institution Identity in System Settings.',
            ],
            'include_sister_institutions' => [
                'label' => $sisters === []
                    ? 'Count sister institutions as ours'
                    : 'Count ' . static::readable($sisters) . ' as ours',
                'help' => 'Separate institutions carrying the same name. Leave off unless the Directorate of Research counts them in the university\'s figures. Set the list under Institution Identity in System Settings.',
            ],
            'include_authors_affiliated_elsewhere' => [
                'label' => 'Consider authors whose affiliation names another institution',
                'help' => 'Affiliation is otherwise a gate — a name written under somebody else\'s address never reaches the matcher. Turning this on brings those names in as their own group to review, not as matches; nothing is credited without a decision. Expect a much longer list and a slower run. An author whose Scopus ID we have already recorded is always considered, whatever this is set to.',
            ],
            'deduplicate_by_identifier' => [
                'label' => 'Count a repeated paper once',
                'help' => 'Matched on EID, then DOI, then title. The Directorate\'s workbook stacks ten cumulative downloads of the same list.',
            ],
        ];
    }

    /** "A, B and C" — for naming a configured list inside a sentence. */
    protected static function readable(array $values): string
    {
        if (count($values) === 1) {
            return (string) $values[0];
        }

        $last = array_pop($values);

        return implode(', ', $values) . ' and ' . $last;
    }

    /**
     * The switches that are not at their default, for a short summary line.
     *
     * @return array<int, string>
     */
    public function differencesFromDefault(): array
    {
        $defaults = (new self)->toArray();
        $described = self::describe();
        $changed = [];

        foreach ($this->toArray() as $key => $value) {
            if ($value !== $defaults[$key]) {
                $changed[] = ($value ? 'on: ' : 'off: ') . $described[$key]['label'];
            }
        }

        return $changed;
    }
}
