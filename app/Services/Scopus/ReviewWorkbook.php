<?php

namespace App\Services\Scopus;

use App\Helpers\Institution;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The workbook a person checks before anything is applied.
 *
 * Laid out so both sides are on the same line: what Scopus says on the left,
 * what we think it is on the right, and a Decision column at the end. Reviewing
 * a name in isolation is guesswork — "Alom, Md. Masud" could be any of several
 * teachers — so each row carries the full author list from both sides. That is
 * usually what settles it.
 *
 * Xlsx rather than csv on purpose: twenty-odd columns, author lists that run to
 * several hundred characters, and confidence shown as colour. Excel splits long
 * quoted fields out of a csv often enough to make the review untrustworthy.
 */
class ReviewWorkbook
{
    protected const HEADER_PAPERS = '2F5597';

    protected const HEADER_PEOPLE = 'BF8F00';

    /** Green, amber, red — how far the suggestion can be trusted. */
    protected const CONFIDENCE_FILL = [
        RecordResolver::CERTAIN => 'C6EFCE',
        RecordResolver::LIKELY => 'FFF2CC',
        RecordResolver::AMBIGUOUS => 'FCE4D6',
        RecordResolver::NONE => 'F2F2F2',
    ];

    /**
     * What to call our own institution in a column heading.
     *
     * "Our authors" is already taken by the column holding what our copy of the
     * publication credits, so the Scopus-side column has to name the place.
     */
    protected string $ours;

    public function __construct()
    {
        $this->ours = Institution::shortName();
    }

    public function write(Collection $papers, Collection $people, array $summary, string $absolutePath): void
    {
        $spreadsheet = new Spreadsheet();

        $this->writeSummary($spreadsheet->getActiveSheet(), $summary);

        /*
         * Split by what a reviewer has to do with them, not by what they are.
         *
         * One sheet of 1,553 rows is a sheet nobody finishes. These three are
         * different jobs: the first needs a glance, the second needs a decision
         * about who wrote what, the third needs the paper entered.
         */
        $needsAttention = $papers->filter(fn ($paper) => $paper['publication']
            && $paper['authorship']['status'] !== AuthorshipComparison::CLEAN);

        $settled = $papers->filter(fn ($paper) => $paper['publication']
            && $paper['authorship']['status'] === AuthorshipComparison::CLEAN);

        $notHere = $papers->whereNull('publication');

        $this->writePapers($spreadsheet->createSheet(), $settled, 'Matched', '1F7A44');
        $this->writePapers($spreadsheet->createSheet(), $needsAttention, 'Needs attention', 'B45309');
        $this->writePapers($spreadsheet->createSheet(), $notHere, 'Not in our system', '9F1239');

        $this->writePeople($spreadsheet->createSheet(), $people);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($absolutePath);
    }

    /** The report the first step is meant to produce, on the first sheet. */
    protected function writeSummary(Worksheet $sheet, array $summary): void
    {
        $sheet->setTitle('Summary');

        $sheet->setCellValue('A1', 'Scopus review');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

        $sheet->setCellValue('A2', 'Nothing has been changed. This file is for checking; the decisions in it are applied separately.');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 4;

        foreach ([
            'Publications' => [
                'In the file' => $summary['papers']['total'],
                'Already in our system' => $summary['papers']['already_here'],
                'New to us' => $summary['papers']['new'],
                'Carrying a DOI' => $summary['papers']['with_doi'],
                'Rows with no author of ours' => $summary['papers']['rows_without_a_diu_author'],
                'Rows whose author columns did not line up' => $summary['papers']['rows_whose_columns_did_not_line_up'],
            ],
            'People connected to us' => [
                'Distinct people' => $summary['people']['total'],
                'Matched to a teacher' => $summary['people']['teacher'],
                'Matched to an external author' => $summary['people']['external_author'],
                'Look like students' => $summary['people']['looks_like_student'],
                'Not found anywhere' => $summary['people']['not_found'],
                'Certain (matched by email)' => $summary['people']['certain'],
                'Likely (matched by name)' => $summary['people']['likely'],
                'Ambiguous (several candidates)' => $summary['people']['ambiguous'],
                'Carrying an email address' => $summary['people']['with_email'],
                'Carrying a Scopus author id' => $summary['people']['with_scopus_id'],
            ],
            /*
             * Papers and people are different units and the numbers above read
             * as though they were comparable. This section is what makes them
             * comparable: every author position in the file, and how many of
             * them belong to somebody we can name.
             */
            'Authorship covered (one line per author, per paper)' => [
                'Author positions in the file' => $summary['coverage']['author_slots'],
                'Average authors of ours per paper' => $summary['coverage']['slots_per_paper'],
                'Held by one of our teachers' => $summary['coverage']['slots_teacher'],
                'Held by an external author we hold' => $summary['coverage']['slots_external_author'],
                'Held by someone who looks like a student' => $summary['coverage']['slots_student'],
                'Held by someone we cannot name' => $summary['coverage']['slots_unknown'],
                'Accounted for' => $summary['coverage']['slots_accounted_for']
                    . '  (' . $summary['coverage']['percent_accounted_for'] . '%)',
            ],
            'Papers, by how much of their authorship we can name' => [
                'Every author of ours known' => $summary['coverage']['papers_all_authors_known'],
                'Some known, some not' => $summary['coverage']['papers_some_authors_known'],
                'None of them known' => $summary['coverage']['papers_no_authors_known'],
                'At least one of our teachers on it' => $summary['coverage']['papers_with_a_matched_teacher'],
            ],
            'Faculty and department' => [
                'Faculty worked out' => $summary['units']['faculty_resolved'],
                'Department worked out' => $summary['units']['department_resolved'],
            ],
            /*
             * The three sheets, and why a paper is on the one it is on. Stated
             * here because the tab names alone do not say what to do with them.
             */
            'The three publication sheets' => [
                'Matched — authorship agrees, nothing to do' => $summary['authorship'][AuthorshipComparison::CLEAN] ?? 0,
                'Needs attention — authors missing here' => $summary['authorship'][AuthorshipComparison::MISSING_AUTHORS] ?? 0,
                'Needs attention — first author differs' => $summary['authorship'][AuthorshipComparison::FIRST_AUTHOR_DIFFERS] ?? 0,
                'Needs attention — nobody credited here' => $summary['authorship'][AuthorshipComparison::NOBODY_CREDITED] ?? 0,
                'Not in our system — the paper itself is new' => $summary['papers']['new'],
            ],
            // Which import left the gaps. The two flags are not alternatives:
            // a paper can have come from both, and 1,898 of the library did.
            'Where our copies came from, and how their authorship held up' => collect($summary['by_source'] ?? [])
                ->map(fn ($counts, $source) => [
                    $source => sprintf('%d — %d agree, %d need attention',
                        $counts['total'] ?? 0,
                        $counts['clean'] ?? 0,
                        $counts['needs_attention'] ?? 0),
                ])
                ->collapse()
                ->all(),
            'How each publication was found' => [
                'By Scopus EID — exact' => $summary['publication_basis']['eid'] ?? 0,
                'By DOI — exact' => $summary['publication_basis']['doi'] ?? 0,
                'By title — the weakest of the three' => $summary['publication_basis']['title'] ?? 0,
                'Not found' => $summary['publication_basis']['none'] ?? 0,
            ],
            'What each match rested on' => [
                'Scopus author id — cannot be wrong' => $summary['basis']['scopus_id'] ?? 0,
                'Email address — never a guess' => $summary['basis']['email'] ?? 0,
                'Name alone' => $summary['basis']['name'] ?? 0,
                'Name, settled by department' => $summary['basis']['name_and_department'] ?? 0,
                "Name, settled by the paper's own authors" => $summary['basis']['name_and_paper_authors'] ?? 0,
                'An author already merged into a teacher' => $summary['basis']['already_merged_author'] ?? 0,
                'Nothing matched' => $summary['basis']['nothing'] ?? 0,
            ],
            // Without these the counts above cannot be explained: the same file
            // with the tie-breakers off gives different numbers.
            'Rules this run was told to match by' => collect(MatchingOptions::describe())
                ->map(fn ($described, $key) => [$described['label'] => ($summary['options'][$key] ?? false) ? 'Yes' : 'No'])
                ->collapse()
                ->all(),
        ] as $section => $lines) {
            $sheet->setCellValue('A' . $row, $section);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
            $row++;

            foreach ($lines as $label => $value) {
                $sheet->setCellValue('A' . $row, $label);
                $sheet->setCellValue('B' . $row, $value);
                $row++;
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(46);
        $sheet->getColumnDimension('B')->setWidth(14);
    }

    /** How a person came to be in the run at all, in the reviewer's words. */
    protected const STANDING = [
        ScopusAnalysis::AFFILIATED_HERE => 'Scopus says ours',
        ScopusAnalysis::IDENTIFIED_HERE => 'Scopus ID we recorded',
        ScopusAnalysis::AFFILIATED_ELSEWHERE => 'Name only — affiliation elsewhere',
    ];

    /** What each authorship problem is called, and the colour it is marked in. */
    protected const AUTHORSHIP = [
        AuthorshipComparison::CLEAN => ['Authorship agrees', 'C6EFCE'],
        AuthorshipComparison::MISSING_AUTHORS => ['Authors missing here', 'FFE0B2'],
        AuthorshipComparison::FIRST_AUTHOR_DIFFERS => ['First author differs', 'F8BBD0'],
        AuthorshipComparison::NOBODY_CREDITED => ['Nobody credited here', 'FFCDD2'],
    ];

    protected function writePapers(Worksheet $sheet, Collection $papers, string $title, string $headerColour): void
    {
        $sheet->setTitle($title);

        $headers = [
            'Scopus Title', 'Year', 'DOI', 'EID', 'Source Title', 'Document Type', 'Cited by',
            'Scopus — all authors',
            // Beside the names rather than inside them, so the list stays
            // readable and the importer still has something to bind. Without
            // this the workbook round trip loses every identifier the export
            // carried, which is what left scopus_author_ids empty.
            'Scopus author ids',
            /*
             * The affiliation lines, in the same order as the names.
             *
             * The importer has looked for this column since affiliations were
             * added and never found it, because nothing wrote it — so every
             * publication imported from a checked workbook went in with no
             * record of where any of its authors were writing from, while the
             * same review done in the browser recorded all of it.
             */
            'Authors with affiliations',
            $this->ours . ' authors on this paper',
            // What the correspondence address said, and room to disagree with
            // it. Names, not positions: a reviewer should not have to count.
            'Corresponding author(s)', 'Corresponding override',
            'Matched on', 'Our Publication ID', 'Our Title', 'Our Year', 'Our record came from',
            'Our authors', 'Authorship', 'What differs', 'Scopus 1st author', 'Our 1st author',
            'Decision', 'Notes',
        ];

        $this->headerRow($sheet, $headers, $headerColour);

        $row = 2;

        foreach ($papers as $paper) {
            $publication = $paper['publication'];
            $authorship = $paper['authorship'];

            [$label, $colour] = self::AUTHORSHIP[$authorship['status']] ?? ['—', 'FFFFFF'];

            $sheet->fromArray([
                $paper['title'],
                $paper['year'],
                $paper['doi'],
                $paper['eid'],
                $paper['source_title'],
                $paper['document_type'],
                $paper['cited_by'],
                $paper['all_authors'],
                $paper['all_author_ids'] ?? '',
                $paper['all_author_affiliations'] ?? '',
                collect($paper['diu_authors'])->pluck('name')->implode('; '),
                $this->correspondingNames($paper),
                '',
                $publication ? strtoupper($paper['match_basis']) : 'Not found',
                $publication?->id,
                $publication?->title,
                $publication?->publication_year,
                $this->sourceOf($publication),
                $paper['our_authors'],
                $publication ? $label : '',
                $authorship['note'],
                $authorship['scopus_first_author'],
                $authorship['our_first_author'],
                '',
                '',
            ], null, 'A' . $row, true);

            /*
             * Three columns wider than the layout these letters were written
             * for — affiliations at J, and the two corresponding-author columns
             * at L and M — so everything from "Matched on" onwards has moved
             * three to the right. The letters are still hand-written because
             * only styling needs them; the importer reads by heading, which is
             * what stopped the last such move from breaking it.
             */
            if ($publication) {
                // The authorship verdict is the cell a reviewer scans for, so it
                // carries the colour rather than the whole row.
                $sheet->getStyle("T{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($colour);

                // Where our copy came from, coloured too: an authorship problem
                // on a PD-imported record means something different from one on
                // a record somebody entered here.
                $sheet->getStyle("R{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($this->sourceColour($publication));

                // A disagreement about who came first changes the incentive
                // split, so both names are marked, not just the verdict.
                if ($authorship['status'] === AuthorshipComparison::FIRST_AUTHOR_DIFFERS) {
                    $sheet->getStyle("V{$row}:W{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8BBD0');
                }
            } else {
                $sheet->getStyle("N{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC');
            }

            // Nothing named a corresponding author, which is the case a reviewer
            // has to fill in by hand — 10% of the July export.
            if (($paper['corresponding_positions'] ?? []) === []) {
                $sheet->getStyle("L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC');
            }

            $row++;
        }

        $this->finish($sheet, $headers, $row - 1, [
            'A' => 55, 'B' => 8, 'C' => 26, 'D' => 22, 'E' => 34, 'F' => 16, 'G' => 9,
            'H' => 60, 'I' => 30, 'J' => 34, 'K' => 12, 'L' => 12, 'M' => 55, 'N' => 10,
            'O' => 20, 'P' => 60, 'Q' => 22, 'R' => 60, 'S' => 26, 'T' => 26, 'U' => 16,
            'V' => 28,
        ]);
    }

    protected function writePeople(Worksheet $sheet, Collection $people): void
    {
        $sheet->setTitle('People');

        $headers = [
            'Scopus Name', 'Scopus Author ID', 'Email', 'Papers', 'Unit as Scopus wrote it',
            // Whether the export said this person was ours at all, and if not,
            // who it said they were with. A name that only resembles a teacher
            // is a different proposition from one Scopus filed under us.
            'How they got here', 'Affiliation Scopus gave',
            'Suggested Faculty', 'Suggested Department', 'How the unit was found',
            'Our guess', 'Teacher ID', 'Author ID', 'Our Name', 'Our Department',
            'Matched by', 'Confidence', 'Candidates', 'Who it might be', 'Decision', 'Notes',
        ];

        $this->headerRow($sheet, $headers, self::HEADER_PEOPLE);

        $row = 2;

        // Most papers first: a name with sixty papers is worth ten minutes of
        // somebody's attention, one with a single paper is not.
        foreach ($people->sortByDesc('papers') as $person) {
            $match = $person['match'];
            $teacher = $match['teacher'];
            $author = $match['author'];

            $sheet->fromArray([
                $person['name'],
                $person['scopus_id'],
                $person['email'],
                $person['papers'],
                implode(' | ', $person['units']),
                self::STANDING[$person['standing'] ?? ScopusAnalysis::AFFILIATED_HERE] ?? '—',
                implode(' | ', $person['other_affiliations'] ?? []),
                $person['faculty']?->name,
                $person['department']?->name,
                $person['unit_source'],
                $this->kindLabel($match['kind']),
                $teacher?->id,
                $author?->id,
                $teacher?->full_name ?? $author?->name,
                $teacher?->department?->name,
                $match['basis'],
                $match['confidence'],
                $match['candidates'] ?: null,
                // Somebody to choose between, rather than a blank cell: "Al-Amin,
                // Md." matches 21 teachers and the useful thing is to show them.
                implode(' | ', $person['candidates'] ?? []),
                '',
                '',
            ], null, 'A' . $row, true);

            // Two columns wider than before, so the confidence fill moved from
            // O to Q along with everything after "Unit as Scopus wrote it".
            $sheet->getStyle("Q{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::CONFIDENCE_FILL[$match['confidence']] ?? 'FFFFFF');

            // A name that only resembles one of ours is marked in the same
            // amber the workbook uses elsewhere for "somebody has to look".
            if (($person['standing'] ?? null) === ScopusAnalysis::AFFILIATED_ELSEWHERE) {
                $sheet->getStyle("F{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFE0B2');
            }

            $row++;
        }

        $this->finish($sheet, $headers, $row - 1, [
            'A' => 32, 'B' => 16, 'C' => 32, 'D' => 8, 'E' => 44,
            'F' => 24, 'G' => 40,
            'H' => 30, 'I' => 30, 'J' => 20, 'K' => 18, 'L' => 11, 'M' => 10,
            'N' => 32, 'O' => 26, 'P' => 20, 'Q' => 13, 'R' => 11, 'S' => 70, 'T' => 16, 'U' => 28,
        ]);
    }

    /**
     * Where our copy of a paper came from.
     *
     * The two flags are not alternatives — 1,898 of the 17,510 carry both,
     * because the paper was on the old website *and* in the PD export and the
     * import matched them to one record. Saying "Old Site + PD" rather than
     * picking one keeps the fact that two sources agreed.
     *
     * Worth having beside the authorship verdict: a missing author on a
     * PD-imported record is an import that did not match a name, while the same
     * on a record somebody entered here is somebody's mistake.
     */
    /**
     * The corresponding authors, by name rather than by position.
     *
     * A reviewer reading "3; 7" would have to count along the author list to
     * check it, and counting is the part people get wrong. The override column
     * beside this one is read back the same way — names, matched by the same
     * rules the correspondence address is.
     *
     * @param  array<string, mixed>  $paper
     */
    protected function correspondingNames(array $paper): string
    {
        $positions = $paper['corresponding_positions'] ?? [];

        if ($positions === []) {
            return '';
        }

        $names = array_values(array_filter(
            array_map('trim', explode(';', (string) ($paper['all_authors'] ?? ''))),
            'strlen',
        ));

        return implode('; ', array_filter(array_map(
            fn ($position) => $names[$position] ?? null,
            $positions,
        )));
    }

    protected function sourceOf($publication): string
    {
        if ($publication === null) {
            return '';
        }

        $sources = [];

        if ($publication->come_from_old_site) {
            $sources[] = 'Old Site';
        }

        if ($publication->come_from_pd) {
            $sources[] = 'PD';
        }

        return $sources ? implode(' + ', $sources) : 'Entered here';
    }

    protected function sourceColour($publication): string
    {
        return match (true) {
            $publication->come_from_old_site && $publication->come_from_pd => 'D9D2E9',
            (bool) $publication->come_from_old_site => 'E2E2E2',
            (bool) $publication->come_from_pd => 'CFE2F3',
            default => 'D9EAD3',
        };
    }

    /** The people already credited on a publication here, for comparison. */
    protected function ourAuthorsOf($publication): string
    {
        $publication->loadMissing(['teachers', 'externalAuthors']);

        return collect()
            ->concat($publication->teachers->map(fn ($t) => $t->full_name . ' (Teacher)'))
            ->concat($publication->externalAuthors->map(fn ($a) => $a->name . ' (External)'))
            ->implode('; ');
    }

    protected function kindLabel(string $kind): string
    {
        return match ($kind) {
            'teacher' => 'Our teacher',
            'author' => 'External author',
            'student' => 'Looks like a student',
            default => 'Not found',
        };
    }

    /** @param  array<int, string>  $headers */
    protected function headerRow(Worksheet $sheet, array $headers, string $rgb): void
    {
        $sheet->fromArray($headers, null, 'A1', true);

        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
    }

    /** @param  array<string, int>  $widths */
    protected function finish(Worksheet $sheet, array $headers, int $lastRow, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $lastColumn = $sheet->getHighestColumn();

        // Frozen header and filters: this is a sheet somebody scrolls through a
        // thousand rows of, not one they read top to bottom.
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        if ($lastRow >= 2) {
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(false);
        }
    }
}
