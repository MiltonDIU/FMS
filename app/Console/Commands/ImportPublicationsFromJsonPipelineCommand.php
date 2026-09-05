<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\Publication;
use App\Models\PublicationType;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;
use App\Models\ResearchCollaboration;
use App\Models\Teacher;

class ImportPublicationsFromJsonPipelineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:import-pipeline {--limit= : Limit the number of publications to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and update publications from third_step.json to FMS database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonPath = storage_path('app/public/document/third_step.json');

        // Fallback for documnt typo
        if (!file_exists($jsonPath)) {
            $jsonPath = storage_path('app/public/documnt/third_step.json');
        }

        if (!file_exists($jsonPath)) {
            $this->error("Error: third_step.json not found at {$jsonPath}");
            return 1;
        }

        $publications = json_decode(File::get($jsonPath), true);
        if (!is_array($publications)) {
            $this->error("Error: Invalid JSON structure in third_step.json");
            return 1;
        }

        $limit = $this->option('limit');
        $totalToProcess = count($publications);
        if ($limit && is_numeric($limit)) {
            $totalToProcess = min((int)$limit, $totalToProcess);
        }

        $this->info("Starting FMS Database Import/Update Pipeline for {$totalToProcess} publications...");

        // Preload caches to speed up resolution
        $typesCache = PublicationType::all()->keyBy('slug');
        $linkagesCache = PublicationLinkage::all()->keyBy('slug');
        $quartilesCache = PublicationQuartile::all()->keyBy('slug');
        $collaborationsCache = ResearchCollaboration::all()->keyBy('slug');

        /*
         * The users that created_by is allowed to point at.
         *
         * publications.created_by and publication_incentives.created_by are
         * both foreign keys into users. The ids in the file are written by
         * publications:convert-pipeline, which resolves each "Created by" name
         * against the users table as it stands on the day it runs — so they are
         * only good for as long as that table keeps those rows.
         *
         * A migrate:fresh breaks that. The file kept ids 2023 to 2031 from an
         * earlier run while the rebuilt users table stopped at 2016, and every
         * one of the 7,378 publications failed on the foreign key: not a few
         * bad rows, the entire import.
         *
         * The column is nullable with ON DELETE SET NULL, so an id nobody
         * matches becomes null rather than taking the record down with it.
         * Everything created inside the system from now on sets created_by
         * itself; this is only about what the old file remembers.
         */
        $knownUserIds = DB::table('users')->pluck('id')->flip();

        $resolveCreator = static function ($id) use ($knownUserIds): ?int {
            if ($id === null || $id === '') {
                return null;
            }

            return $knownUserIds->has((int) $id) ? (int) $id : null;
        };

        $creatorsDropped = 0;

        // Counted so the run says how the collaboration came out, rather than
        // leaving it to be discovered in the table afterwards.
        $collaborationCounts = ['diu' => 0, 'external' => 0, 'unknown' => 0];

        $importedCount = 0;
        $updatedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $pivotCount = 0;
        $notFoundCount = 0;
        $uniqueTeachers = [];

        for ($i = 0; $i < $totalToProcess; $i++) {
            $pub = $publications[$i];
            $title = trim($pub['title'] ?? '');

            if ($title === '') {
                $skippedCount++;
                continue;
            }

            // Resolve Type ID
            $type_id = null;
            if (!empty($pub['remarks'])) {
                $slug = $this->toSlug($pub['remarks']);
                $type_id = isset($typesCache[$slug]) ? $typesCache[$slug]->id : null;
            }

            // Resolve Linkage ID
            $linkage_id = null;
            if (!empty($pub['indexed'])) {
                $slug = $this->toSlug($pub['indexed']);
                $linkage_id = isset($linkagesCache[$slug]) ? $linkagesCache[$slug]->id : null;
            }

            // Resolve Quartile ID
            $quartile_id = null;
            if (!empty($pub['q_index'])) {
                $slug = $this->toSlug($pub['q_index']);
                $quartile_id = isset($quartilesCache[$slug]) ? $quartilesCache[$slug]->id : null;
            }

            /*
             * Resolve the collaboration from who wrote the paper.
             *
             * PD does not record it — research_collaboration_id appears nowhere
             * in the file — so the field was read straight out of the JSON and
             * came back null for all 7,378 rows every run. The authors do say
             * it, though, so it is worked out here instead.
             *
             * A paper with one of our own teachers on it is DIU research; a
             * paper with none is an external collaboration. Note that a visiting
             * faculty member does not change the answer: with a DIU teacher the
             * paper is still DIU research, and without one it is still external.
             * That leaves 'Visiting Faculty + DIU Researcher' unused, which is
             * deliberate rather than an oversight — say the word and VF takes
             * precedence in whichever direction you want it to.
             *
             * A paper with no authors at all is left alone. Two of them are like
             * that, and neither statement is true of a paper we know nothing
             * about.
             */
            $collaboration_id = null;
            $authors = is_array($pub['authors'] ?? null) ? $pub['authors'] : [];

            if ($authors !== []) {
                $hasOurTeacher = false;

                foreach ($authors as $author) {
                    if (($author['authorable_type'] ?? null) === Teacher::class) {
                        $hasOurTeacher = true;
                        break;
                    }
                }

                $collaborationSlug = $hasOurTeacher ? 'diu-researcher' : 'collaboration-external';
                $collaboration_id = isset($collaborationsCache[$collaborationSlug])
                    ? $collaborationsCache[$collaborationSlug]->id
                    : null;

                $collaborationCounts[$hasOurTeacher ? 'diu' : 'external']++;
            } else {
                $collaborationCounts['unknown']++;
            }

            DB::beginTransaction();
            try {
                // Check if publication already exists by normalized slug match.
                // Slug matching is more forgiving than exact title (handles spacing,
                // punctuation and casing differences between sources).
                $titleSlug = \Illuminate\Support\Str::slug($title);
                $existingPub = Publication::where('slug', $titleSlug)->first();

                // Source tracking flags:
                // - Every publication in this import comes from PD → come_from_pd = 1
                // - If it already exists in the system, it also came from the old site → come_from_old_site = 1
                // - Otherwise it is PD-only → come_from_old_site = 0
                $comeFromPd = 1;
                $comeFromOldSite = $existingPub ? 1 : 0;

                // Map all fillable fields
                $pubData = [
                    'faculty_id' => $pub['faculty_id'] ?? null,
                    'department_id' => $pub['department_id'] ?? null,
                    'publication_type_id' => $type_id,
                    'publication_linkage_id' => $linkage_id,
                    'publication_quartile_id' => $quartile_id,
                    'grant_type_id' => $pub['grant_type_id'] ?? null,
                    'research_collaboration_id' => $collaboration_id,
                    'title' => $title,
                    'slug' => $titleSlug,
                    'journal_name' => $pub['journal_name'] ?? null,
                    'journal_link' => $pub['journal_link'] ?? null,
                    'publication_date' => !empty($pub['publication_date']) ? $pub['publication_date'] : null,
                    'publication_year' => $this->extractInt($pub['publication_year'] ?? null),
                    'research_area' => $pub['research_area'] ?? null,
                    'h_index' => $this->extractInt($pub['h_index'] ?? null),
                    'citescore' => $this->extractFloat($pub['citescore'] ?? null),
                    'impact_factor' => $this->extractFloat($pub['impact_factor'] ?? null),
                    'student_involvement' => (strtolower(trim($pub['student_involvement'] ?? '')) === 'yes'),
                    'keywords' => $pub['keywords'] ?? null,
                    'abstract' => $pub['abstract'] ?? null,
                    'status' => $pub['status'] ?? 'approved',
                    'is_featured' => (bool)($pub['is_featured'] ?? false),
                    'sort_order' => isset($pub['sort_order']) ? (int)$pub['sort_order'] : 1,
                    'come_from_old_site' => $comeFromOldSite,
                    'come_from_pd' => $comeFromPd,
                    'created_by' => $resolveCreator($pub['created_by_id'] ?? $pub['created_by'] ?? null),
                ];

                if (($pub['created_by_id'] ?? null) !== null && $pubData['created_by'] === null) {
                    $creatorsDropped++;
                }

                if ($existingPub) {
                    // Update existing publication
                    $existingPub->update($pubData);
                    $pubId = $existingPub->id;
                    $updatedCount++;
                } else {
                    // Create new publication
                    $newPub = Publication::create($pubData);
                    $pubId = $newPub->id;
                    $importedCount++;
                }

                // Sync Authors (clean old ones and insert new ones)
                DB::table('publication_authors')->where('publication_id', $pubId)->delete();

                if (isset($pub['authors']) && is_array($pub['authors'])) {
                    foreach ($pub['authors'] as $author) {
                        if (!empty($author['authorable_type']) && !empty($author['authorable_id'])) {
                            DB::table('publication_authors')->insert([
                                'publication_id' => $pubId,
                                'authorable_type' => $author['authorable_type'],
                                'authorable_id' => $author['authorable_id'],
                                'author_role' => $author['author_role'] ?? 'co_author',
                                'incentive_amount' => (float)($author['incentive_amount'] ?? 0.00),
                                'sort_order' => (int)($author['sort_order'] ?? 1),
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $pivotCount++;

                            // Track metrics
                            if ($author['authorable_type'] === 'App\Models\Teacher') {
                                $uniqueTeachers[$author['authorable_id']] = true;
                            } else {
                                $notFoundCount++;
                            }
                        }
                    }
                }

                // Sync/Insert Incentive if present in JSON
                DB::table('publication_incentives')->where('publication_id', $pubId)->delete();

                if (isset($pub['incentive']) && is_array($pub['incentive'])) {
                    $inc = $pub['incentive'];
                    DB::table('publication_incentives')->insert([
                        'publication_id' => $pubId,
                        'total_amount' => (float)($inc['total_amount'] ?? 0.00),
                        'status' => $inc['status'] ?? 'pending',
                        // Same foreign key, same treatment. The 5 that used to
                        // stand here as a fallback was a user id nobody had
                        // checked for either.
                        'created_by' => $resolveCreator($inc['created_by'] ?? $pub['created_by_id'] ?? null),
                        'created_at' => $inc['created_at'] ?? now(),
                        'updated_at' => $inc['updated_at'] ?? now()
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to import/update: {$title}. Error: " . $e->getMessage());
                $failedCount++;
            }
        }

        $this->info("\n--- Import Pipeline Execution Completed ---");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed', count($uniqueTeachers)],
                ['Publications → NEW (created)', $importedCount],
                ['Publications → UPDATED', $updatedCount],
                ['Publications → SHARED (existing linked)', $updatedCount],
                ['Authorships Linked (Pivot records)', $pivotCount],
                ['Records skipped (empty data)', $skippedCount],
                ['Teachers not found in new DB', $notFoundCount],
                ['Individual record failures', $failedCount],
                ['Collaboration → DIU Researcher', $collaborationCounts['diu']],
                ['Collaboration → External', $collaborationCounts['external']],
                ['Collaboration → left empty (no authors)', $collaborationCounts['unknown']],
                ['created_by dropped (no such user)', $creatorsDropped],
            ]
        );

        return 0;
    }

    /**
     * Map strings to DB slugs
     */
    private function toSlug(string $val): string
    {
        $slug = str_replace(['/', ' '], ['-', '-'], strtolower(trim($val)));
        return preg_replace('/-+/', '-', $slug);
    }

    /**
     * Extract the first numeric sequence from a string and cast to float
     */
    private function extractFloat($val): ?float
    {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) return (float)$val;
        if (preg_match('/[0-9]+(?:\.[0-9]+)?/', $val, $matches)) {
            return (float)$matches[0];
        }
        return null;
    }

    /**
     * Extract the first numeric sequence from a string and cast to int
     */
    private function extractInt($val): ?int
    {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) return (int)$val;
        if (preg_match('/[0-9]+/', $val, $matches)) {
            return (int)$matches[0];
        }
        return null;
    }
}
