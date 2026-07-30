<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\Publication;
use App\Models\PublicationType;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;

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
                    'research_collaboration_id' => $pub['research_collaboration_id'] ?? null,
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
                    'created_by' => $pub['created_by_id'] ?? $pub['created_by'] ?? null,
                ];

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
                        'created_by' => $inc['created_by'] ?? $pub['created_by_id'] ?? 5,
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
