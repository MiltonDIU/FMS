<?php

namespace App\Services\Scopus;

use App\Models\Author;
use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Applies decisions made in a Scopus review workbook (.xlsx).
 *
 * Reads the workbook back after a user has marked their decisions, creating
 * new publications, linking authors, and binding Scopus Author IDs to teachers.
 */
class ScopusWorkbookImporter
{
    protected array $positiveKeywords = [
        'approve',
        'approved',
        'import',
        'yes',
        '1',
        'add',
        'accept',
    ];

    public function __construct(
        protected RecordResolver $resolver
    ) {}

    /**
     * Parse the checked workbook and apply decisions.
     *
     * @return array{
     *     publications_created: int,
     *     publications_skipped: int,
     *     people_linked: int,
     *     errors: array<int, string>
     * }
     */
    public function import(string $absolutePath): array
    {
        if (! file_exists($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        $spreadsheet = IOFactory::load($absolutePath);

        $stats = [
            'publications_created' => 0,
            'publications_skipped' => 0,
            'people_linked' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($spreadsheet, &$stats) {
            // 1. Process People sheet if present
            $peopleSheet = $spreadsheet->getSheetByName('People');
            if ($peopleSheet !== null) {
                $stats['people_linked'] = $this->importPeopleSheet($peopleSheet);
            }

            // 2. Process "Not in our system" sheet (New Publications)
            $newSheet = $spreadsheet->getSheetByName('Not in our system');
            if ($newSheet !== null) {
                $res = $this->importPublicationsSheet($newSheet, true);
                $stats['publications_created'] += $res['created'];
                $stats['publications_skipped'] += $res['skipped'];
                $stats['errors'] = array_merge($stats['errors'], $res['errors']);
            }

            // 3. Process "Needs attention" sheet
            $attentionSheet = $spreadsheet->getSheetByName('Needs attention');
            if ($attentionSheet !== null) {
                $res = $this->importPublicationsSheet($attentionSheet, false);
                $stats['publications_created'] += $res['created'];
                $stats['publications_skipped'] += $res['skipped'];
                $stats['errors'] = array_merge($stats['errors'], $res['errors']);
            }
        });

        return $stats;
    }

    /**
     * Binds Scopus Author IDs to Teachers when Decision is positive or explicit ID given.
     */
    protected function importPeopleSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
    {
        $lastRow = $sheet->getHighestDataRow();
        $linked = 0;

        for ($r = 2; $r <= $lastRow; $r++) {
            $scopusAuthorId = trim((string) $sheet->getCell("B{$r}")->getValue());
            $teacherIdVal = trim((string) $sheet->getCell("J{$r}")->getValue());
            $decision = strtolower(trim((string) $sheet->getCell("R{$r}")->getValue()));

            if (empty($scopusAuthorId)) {
                continue;
            }

            $teacherId = null;

            if (is_numeric($teacherIdVal) && (int) $teacherIdVal > 0) {
                $teacherId = (int) $teacherIdVal;
            } elseif ($this->isPositive($decision)) {
                // If decision says yes, check decision cell for explicit ID e.g. "123" or "Teacher #123"
                if (preg_match('/(\d+)/', $decision, $m)) {
                    $teacherId = (int) $m[1];
                }
            }

            if ($teacherId !== null && Teacher::where('id', $teacherId)->exists()) {
                $exists = ScopusAuthorId::where('scopus_author_id', $scopusAuthorId)
                    ->where('authorable_type', Teacher::class)
                    ->where('authorable_id', $teacherId)
                    ->exists();

                if (! $exists) {
                    ScopusAuthorId::create([
                        'scopus_author_id' => $scopusAuthorId,
                        'authorable_type' => Teacher::class,
                        'authorable_id' => $teacherId,
                        'source' => ScopusAuthorId::SOURCE_REVIEW,
                        'recorded_by' => Auth::id(),
                    ]);
                    $linked++;
                }
            }
        }

        return $linked;
    }

    /**
     * Process publication sheets ("Not in our system" or "Needs attention").
     *
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    protected function importPublicationsSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, bool $isNewSheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $created = 0;
        $skipped = 0;
        $errors = [];

        for ($r = 2; $r <= $lastRow; $r++) {
            $title = trim((string) $sheet->getCell("A{$r}")->getValue());
            $decision = strtolower(trim((string) $sheet->getCell("T{$r}")->getValue()));

            if (empty($title)) {
                continue;
            }

            if (! $this->isPositive($decision)) {
                $skipped++;
                continue;
            }

            try {
                $year = trim((string) $sheet->getCell("B{$r}")->getValue());
                $doi = trim((string) $sheet->getCell("C{$r}")->getValue());
                $eid = trim((string) $sheet->getCell("D{$r}")->getValue());
                $sourceTitle = trim((string) $sheet->getCell("E{$r}")->getValue());
                $docType = trim((string) $sheet->getCell("F{$r}")->getValue());
                $citedBy = trim((string) $sheet->getCell("G{$r}")->getValue());
                $allAuthors = trim((string) $sheet->getCell("H{$r}")->getValue());
                $existingIdVal = trim((string) $sheet->getCell("K{$r}")->getValue());

                // Check if already exists in DB by ID, DOI, EID, or Title
                $publication = null;
                if (! empty($existingIdVal) && is_numeric($existingIdVal)) {
                    $publication = Publication::find((int) $existingIdVal);
                }

                if (! $publication && ! empty($doi)) {
                    $publication = Publication::where('doi', $doi)->first();
                }

                if (! $publication && ! empty($eid)) {
                    $publication = Publication::where('scopus_eid', $eid)->first();
                }

                if (! $publication) {
                    $publication = Publication::whereRaw('LOWER(title) = ?', [strtolower($title)])->first();
                }

                if (! $publication) {
                    // Determine publication type ID (1: Journal Article, 2: Conference Proceeding)
                    $typeId = 1;
                    $docTypeLower = strtolower($docType . ' ' . $sourceTitle);
                    if (
                        str_contains($docTypeLower, 'conference') ||
                        str_contains($docTypeLower, 'proceeding') ||
                        str_contains($docTypeLower, 'workshop') ||
                        str_contains($docTypeLower, 'symposium')
                    ) {
                        $typeId = 2;
                    }

                    $pubYear = is_numeric($year) ? (int) $year : null;
                    $citescore = is_numeric($citedBy) ? (float) $citedBy : null;

                    $publication = Publication::create([
                        'title' => $title,
                        'publication_year' => $pubYear,
                        'doi' => $doi ?: null,
                        'scopus_eid' => $eid ?: null,
                        'journal_name' => $sourceTitle ?: null,
                        'citescore' => $citescore,
                        'publication_type_id' => $typeId,
                        'publication_linkage_id' => 1, // Scopus
                        'status' => 'approved',
                        'student_involvement' => false,
                        'is_featured' => false,
                        'created_by' => Auth::id() ?? 1,
                    ]);

                    $created++;
                }

                // Process author linkages
                $this->attachAuthors($publication, $allAuthors);

            } catch (\Throwable $e) {
                $errors[] = "Row {$r} ({$title}): " . $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Resolves author names/IDs from Scopus format and attaches to publication_authors pivot.
     */
    protected function attachAuthors(Publication $publication, string $allAuthorsString): void
    {
        if (empty($allAuthorsString)) {
            return;
        }

        // Format: "Fu, Xiang (111); Mahmud, Sakil (222); Luo, Zhen (333)" or "Name1; Name2"
        $authorEntries = array_filter(array_map('trim', explode(';', $allAuthorsString)));
        $sortOrder = 0;

        foreach ($authorEntries as $entry) {
            $name = $entry;
            $scopusId = null;

            if (preg_match('/^(.*?)\s*\((\d+)\)$/', $entry, $matches)) {
                $name = trim($matches[1]);
                $scopusId = trim($matches[2]);
            }

            $name = static::formatAuthorName($name);

            $resolved = $this->resolver->resolveAuthor($name, null, null, [], $scopusId);
            $role = ($sortOrder === 0) ? 'first' : 'co_author';

            if ($resolved['kind'] === 'teacher' && $resolved['teacher'] !== null) {
                $teacher = $resolved['teacher'];

                $exists = DB::table('publication_authors')
                    ->where('publication_id', $publication->id)
                    ->where('authorable_type', Teacher::class)
                    ->where('authorable_id', $teacher->id)
                    ->exists();

                if (! $exists) {
                    DB::table('publication_authors')->insert([
                        'publication_id' => $publication->id,
                        'authorable_type' => Teacher::class,
                        'authorable_id' => $teacher->id,
                        'author_role' => $role,
                        'sort_order' => $sortOrder,
                        'incentive_amount' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // Attach or create external author
                $externalAuthor = null;
                if (! empty($scopusId)) {
                    $externalAuthor = Author::whereHas('scopusAuthorIds', function ($q) use ($scopusId) {
                        $q->where('scopus_author_id', $scopusId);
                    })->first();
                }

                if (! $externalAuthor) {
                    $externalAuthor = Author::where('name', $name)->first();
                }

                if (! $externalAuthor) {
                    $externalAuthor = Author::createExternal($name);

                    // createExternal can hand back somebody already here under
                    // a different spelling of the same name, so the id is only
                    // recorded if it is not on them already.
                    if (! empty($scopusId)) {
                        ScopusAuthorId::firstOrCreate(
                            [
                                'scopus_author_id' => $scopusId,
                                'authorable_type' => Author::class,
                                'authorable_id' => $externalAuthor->id,
                            ],
                            [
                                'source' => ScopusAuthorId::SOURCE_REVIEW,
                                'recorded_by' => Auth::id(),
                            ],
                        );
                    }
                }

                $exists = DB::table('publication_authors')
                    ->where('publication_id', $publication->id)
                    ->where('authorable_type', Author::class)
                    ->where('authorable_id', $externalAuthor->id)
                    ->exists();

                if (! $exists) {
                    DB::table('publication_authors')->insert([
                        'publication_id' => $publication->id,
                        'authorable_type' => Author::class,
                        'authorable_id' => $externalAuthor->id,
                        'author_role' => $role,
                        'sort_order' => $sortOrder,
                        'incentive_amount' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $sortOrder++;
        }
    }

    public static function formatAuthorName(string $name): string
    {
        $name = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $name));

        if (str_contains($name, ',')) {
            $parts = array_map('trim', explode(',', $name, 2));
            if (count($parts) === 2 && filled($parts[0]) && filled($parts[1])) {
                return $parts[1] . ' ' . $parts[0];
            }
        }

        return $name;
    }

    protected function isPositive(string $decision): bool
    {
        if (empty($decision)) {
            return false;
        }

        foreach ($this->positiveKeywords as $kw) {
            if (str_contains($decision, $kw)) {
                return true;
            }
        }

        return false;
    }
}
