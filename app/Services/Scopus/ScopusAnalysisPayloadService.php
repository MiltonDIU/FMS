<?php

namespace App\Services\Scopus;

use App\Models\Author;
use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Manages JSON analysis payloads for Web-based Interactive Reviewing.
 */
class ScopusAnalysisPayloadService
{
    protected array $positiveKeywords = ['approve', 'approved', 'import', 'yes', '1', 'add', 'accept'];

    public function diskPath(int $importId): string
    {
        return "scopus-analysis/{$importId}.json";
    }

    /**
     * Convert ScopusAnalysis result into lightweight arrays and save to local disk.
     */
    public function savePayload(int $importId, array $result): void
    {
        $papersArray = [];
        foreach ($result['papers'] as $key => $paper) {
            $pub = $paper['publication'];
            $papersArray[$key] = [
                'key' => (string) $key,
                'title' => $paper['title'],
                'year' => $paper['year'],
                'doi' => $paper['doi'],
                'eid' => $paper['eid'],
                'source_title' => $paper['source_title'],
                'link' => $paper['link'] ?? '',
                'document_type' => $paper['document_type'],
                'cited_by' => $paper['cited_by'],
                'all_authors' => $paper['all_authors'],
                'diu_authors' => $paper['diu_authors'],
                'existing_publication_id' => $pub?->id,
                'existing_publication_title' => $pub?->title,
                'confidence' => $paper['confidence'],
                'match_basis' => $paper['match_basis'],
                'authorship' => $paper['authorship'],
                'our_authors' => $paper['our_authors'],
                'decision' => '',
                'notes' => '',
            ];
        }

        $peopleArray = [];
        foreach ($result['people'] as $key => $person) {
            $teacher = $person['match']['teacher'] ?? null;
            $author = $person['match']['author'] ?? null;

            $peopleArray[$key] = [
                'key' => (string) $key,
                'name' => $person['name'],
                'scopus_id' => $person['scopus_id'],
                'email' => $person['email'],
                'units' => $person['units'],
                'papers' => $person['papers'],
                'faculty_name' => $person['faculty']?->name,
                'department_name' => $person['department']?->name,
                'match_kind' => $person['match']['kind'] ?? 'unknown',
                'confidence' => $person['match']['confidence'] ?? 'none',
                'match_basis' => $person['match']['basis'] ?? 'none',

                // How this person got into the run at all, and — when Scopus
                // put them somewhere else — where. A reviewer deciding whether
                // to bind an identifier needs to know that the export never
                // said this person was ours.
                'standing' => $person['standing'] ?? ScopusAnalysis::AFFILIATED_HERE,
                'other_affiliations' => $person['other_affiliations'] ?? [],
                'teacher_id' => $teacher?->id,
                'teacher_name' => $teacher?->full_name,
                'author_id' => $author?->id,
                'author_name' => $author?->name,
                'candidates' => $person['candidates'] ?? [],
                'decision' => '',
                'selected_teacher_id' => $teacher?->id,
                'notes' => '',
            ];
        }

        $payload = [
            'import_id' => $importId,
            'summary' => $result['summary'],
            'papers' => $papersArray,
            'people' => $peopleArray,
            'updated_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put(
            $this->diskPath($importId),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function getPayload(int $importId): ?array
    {
        $path = $this->diskPath($importId);
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $content = Storage::disk('local')->get($path);
        return json_decode($content, true) ?: null;
    }

    /**
     * The stored key for the one the page sent back.
     *
     * A paper without an EID or DOI is keyed by its title, and a key travelling
     * through a form field name can come back with its dots flattened to
     * underscores. Matching on the flattened form too means a decision is not
     * silently dropped on the floor.
     *
     * @param  array<array-key, mixed>  $bucket
     */
    protected function resolveKey(array $bucket, string $key): int|string|null
    {
        if (isset($bucket[$key])) {
            return $key;
        }

        $flattened = str_replace('.', '_', $key);

        foreach (array_keys($bucket) as $stored) {
            if (str_replace('.', '_', (string) $stored) === $flattened) {
                return $stored;
            }
        }

        return null;
    }

    public function setPaperDecision(int $importId, string $paperKey, string $decision, string $notes = ''): void
    {
        $payload = $this->getPayload($importId);
        if (! $payload) return;

        $foundKey = $this->resolveKey($payload['papers'], $paperKey);

        if ($foundKey !== null) {
            $payload['papers'][$foundKey]['decision'] = $decision;
            if ($notes !== '') {
                $payload['papers'][$foundKey]['notes'] = $notes;
            }
            $payload['updated_at'] = now()->toIso8601String();
            Storage::disk('local')->put($this->diskPath($importId), json_encode($payload, JSON_PRETTY_PRINT));
        }
    }

    public function setPersonDecision(int $importId, string $personKey, string $decision, ?int $teacherId = null): void
    {
        $payload = $this->getPayload($importId);
        if (! $payload) return;

        $foundKey = $this->resolveKey($payload['people'], $personKey);

        if ($foundKey !== null) {
            $payload['people'][$foundKey]['decision'] = $decision;
            if ($teacherId !== null) {
                $payload['people'][$foundKey]['selected_teacher_id'] = $teacherId;
            }
            $payload['updated_at'] = now()->toIso8601String();
            Storage::disk('local')->put($this->diskPath($importId), json_encode($payload, JSON_PRETTY_PRINT));
        }
    }

    /**
     * One decision across many rows, in a single read and write of the payload.
     *
     * @param  array<int, string>  $paperKeys
     * @return int  how many rows the decision reached
     */
    public function bulkSetPaperDecisions(int $importId, array $paperKeys, string $decision): int
    {
        return $this->bulkSetDecisions($importId, 'papers', $paperKeys, $decision);
    }

    /**
     * @param  array<int, string>  $personKeys
     * @return int  how many rows the decision reached
     */
    public function bulkSetPersonDecisions(int $importId, array $personKeys, string $decision): int
    {
        return $this->bulkSetDecisions($importId, 'people', $personKeys, $decision);
    }

    /**
     * @param  'papers'|'people'  $bucket
     * @param  array<int, string>  $keys
     */
    protected function bulkSetDecisions(int $importId, string $bucket, array $keys, string $decision): int
    {
        $payload = $this->getPayload($importId);
        if (! $payload) return 0;

        $applied = 0;

        foreach ($keys as $key) {
            $foundKey = $this->resolveKey($payload[$bucket], (string) $key);

            if ($foundKey === null) {
                continue;
            }

            // A row already imported is settled; re-deciding it would only put
            // it back in the queue to be applied a second time.
            if (($payload[$bucket][$foundKey]['decision'] ?? '') === 'imported') {
                continue;
            }

            $payload[$bucket][$foundKey]['decision'] = $decision;
            $applied++;
        }

        // The payload runs to megabytes, so nothing matching means nothing written.
        if ($applied === 0) {
            return 0;
        }

        $payload['updated_at'] = now()->toIso8601String();
        Storage::disk('local')->put($this->diskPath($importId), json_encode($payload, JSON_PRETTY_PRINT));

        return $applied;
    }

    /**
     * Cache/fetch teacher list for UI dropdowns.
     * @return array<int, array{id: int, name: string}>
     */
    public function getTeachersList(): array
    {
        return Teacher::query()
            ->select('id', 'first_name', 'last_name', 'middle_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => '#' . $t->id . ' ' . $t->full_name,
            ])
            ->all();
    }

    /**
     * Apply decisions stored in the online payload.
     *
     * @return array{created: int, skipped: int, people_linked: int, errors: array<int, string>}
     */
    public function importOnlineDecisions(int $importId, ?array $selectedPaperKeys = null): array
    {
        $payload = $this->getPayload($importId);
        if (! $payload) {
            throw new RuntimeException("No analysis payload found for import #{$importId}");
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $peopleLinked = 0;
        $errors = [];

        DB::transaction(function () use ($importId, &$payload, $selectedPaperKeys, &$created, &$updated, &$skipped, &$peopleLinked, &$errors) {
            $resolver = app(RecordResolver::class);

            /*
             * Every publication we could match against, read once.
             *
             * The title fallback below was `whereRaw('LOWER(title) = ?')`, which
             * no index can serve — 53ms of full table scan per paper, and it
             * runs for every paper the analysis called new. On a run with 793 of
             * those it was some forty seconds on its own, the bulk of the time
             * that made this too slow to survive a request at all.
             *
             * Held as maps rather than queried, and added to as publications are
             * created, so that two Scopus rows describing the same paper still
             * resolve to one record — which is what the repeated queries were
             * really buying.
             */
            $byTitle = [];
            $byDoi = [];
            $byEid = [];

            foreach (Publication::query()->select('id', 'title', 'doi', 'scopus_eid')->cursor() as $existing) {
                $title = mb_strtolower(trim((string) $existing->title));

                if ($title !== '') {
                    $byTitle[$title] ??= $existing->id;
                }

                if (filled($existing->doi)) {
                    $byDoi[trim((string) $existing->doi)] ??= $existing->id;
                }

                if (filled($existing->scopus_eid)) {
                    $byEid[trim((string) $existing->scopus_eid)] ??= $existing->id;
                }
            }

            $remember = function (Publication $publication) use (&$byTitle, &$byDoi, &$byEid): void {
                $title = mb_strtolower(trim((string) $publication->title));

                if ($title !== '') {
                    $byTitle[$title] ??= $publication->id;
                }

                if (filled($publication->doi)) {
                    $byDoi[trim((string) $publication->doi)] ??= $publication->id;
                }

                if (filled($publication->scopus_eid)) {
                    $byEid[trim((string) $publication->scopus_eid)] ??= $publication->id;
                }
            };

            // 1. Process People Bindings
            foreach ($payload['people'] as $pKey => $person) {
                $scopusId = $person['scopus_id'] ?? null;
                $teacherId = $person['selected_teacher_id'] ?? $person['teacher_id'] ?? null;
                $dec = strtolower(trim((string) ($person['decision'] ?? '')));

                if (empty($scopusId) || ! $teacherId) {
                    continue;
                }

                /*
                 * Only what the reviewer approved.
                 *
                 * This used to read `isPositive($dec) || $teacherId !== null`,
                 * which — a page or two above having already skipped everyone
                 * without a teacher — was always true. Every suggested match was
                 * bound on the first click, and Skip meant nothing.
                 */
                if ($this->isPositive($dec)) {
                    if (Teacher::where('id', $teacherId)->exists()) {
                        $exists = ScopusAuthorId::where('scopus_author_id', $scopusId)
                            ->where('authorable_type', Teacher::class)
                            ->where('authorable_id', $teacherId)
                            ->exists();

                        if (! $exists) {
                            ScopusAuthorId::create([
                                'scopus_author_id' => $scopusId,
                                'authorable_type' => Teacher::class,
                                'authorable_id' => $teacherId,
                                'source' => ScopusAuthorId::SOURCE_REVIEW,
                                'recorded_by' => Auth::id(),
                            ]);
                            $peopleLinked++;
                        }
                        $payload['people'][$pKey]['decision'] = 'imported';
                    }
                }
            }

            // 2. Process Papers
            foreach ($payload['papers'] as $pKey => $paper) {
                // If specific keys provided, only process those
                if ($selectedPaperKeys !== null && ! in_array($pKey, $selectedPaperKeys, true)) {
                    continue;
                }

                $dec = strtolower(trim((string) ($paper['decision'] ?? '')));

                // If explicit selection or positive decision keyword
                if (! $this->isPositive($dec) && $selectedPaperKeys === null) {
                    $skipped++;
                    continue;
                }

                try {
                    $title = $paper['title'];
                    $doi = $paper['doi'];
                    $eid = $paper['eid'];
                    $existingId = $paper['existing_publication_id'];

                    // The same order of preference as before — recorded id, then
                    // DOI, then EID, then an exact title — but answered from the
                    // maps above rather than with four queries a paper.
                    $foundId = $existingId
                        ?: (! empty($doi) ? ($byDoi[trim($doi)] ?? null) : null)
                        ?: (! empty($eid) ? ($byEid[trim($eid)] ?? null) : null)
                        ?: ($byTitle[mb_strtolower(trim($title))] ?? null);

                    $publication = $foundId ? Publication::find($foundId) : null;

                    if (! $publication) {
                        $typeId = 1;
                        $docTypeLower = strtolower(($paper['document_type'] ?? '') . ' ' . ($paper['source_title'] ?? ''));
                        if (
                            str_contains($docTypeLower, 'conference') ||
                            str_contains($docTypeLower, 'proceeding') ||
                            str_contains($docTypeLower, 'workshop')
                        ) {
                            $typeId = 2;
                        }

                        $pubYear = is_numeric($paper['year']) ? (int) $paper['year'] : null;
                        $citescore = is_numeric($paper['cited_by']) ? (float) $paper['cited_by'] : null;

                        $publication = Publication::create([
                            'title' => $title,
                            'publication_year' => $pubYear,
                            'doi' => $doi ?: null,
                            'scopus_eid' => $eid ?: null,
                            'journal_name' => $paper['source_title'] ?: null,
                            'citescore' => $citescore,
                            'publication_type_id' => $typeId,
                            'publication_linkage_id' => 1,
                            'status' => 'approved',
                            'student_involvement' => false,
                            'is_featured' => false,
                            'created_by' => Auth::id() ?? 1,
                        ]);

                        // So the next row describing this same paper finds it
                        // rather than creating it a second time.
                        $remember($publication);

                        $created++;
                    } else {
                        // Update missing fields on existing publication
                        $dirty = false;
                        if (empty($publication->doi) && ! empty($doi)) {
                            $publication->doi = $doi;
                            $dirty = true;
                        }
                        if (empty($publication->scopus_eid) && ! empty($eid)) {
                            $publication->scopus_eid = $eid;
                            $dirty = true;
                        }
                        if (empty($publication->journal_name) && ! empty($paper['source_title'])) {
                            $publication->journal_name = $paper['source_title'];
                            $dirty = true;
                        }
                        if ($dirty) {
                            $publication->save();

                            // A DOI or EID filled in just now is one the next
                            // row can be matched on.
                            $remember($publication);
                        }
                        $updated++;
                    }

                    /*
                     * The paper is in, whatever the author list does next.
                     *
                     * Recorded before the authors are attached, and deliberately
                     * so: a name that cannot be filed is worth a warning, but it
                     * is not a reason to leave a publication that exists showing
                     * as still awaiting import. That is what happened before —
                     * the row stayed on Approve, the reviewer clicked again, and
                     * the same publication was reconsidered every time.
                     */
                    $payload['papers'][$pKey]['decision'] = 'imported';
                    $payload['papers'][$pKey]['existing_publication_id'] = $publication->id;

                    try {
                        $this->attachAuthors($publication, $paper['all_authors'], $resolver);
                    } catch (\Throwable $e) {
                        $errors[] = "Authors of {$paper['title']}: " . $e->getMessage();
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Paper {$paper['title']}: " . $e->getMessage();
                }
            }

            Storage::disk('local')->put($this->diskPath($importId), json_encode($payload, JSON_PRETTY_PRINT));
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'people_linked' => $peopleLinked,
            'errors' => $errors,
        ];
    }

    protected function attachAuthors(Publication $publication, string $allAuthorsString, RecordResolver $resolver): void
    {
        if (empty($allAuthorsString)) {
            return;
        }

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

            $resolved = $resolver->resolveAuthor($name, null, null, [], $scopusId);
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
