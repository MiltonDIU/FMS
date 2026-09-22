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
use App\Models\GrantType;
use App\Support\GrantTypeRule;
use App\Support\PublicationQuartileRule;
use App\Support\ResearchCollaborationRule;

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
            $jsonPath = storage_path('app/public/document/third_step.json');
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
        $collaborationsById = ResearchCollaboration::all()->keyBy('id');
        $grantTypesById = GrantType::all()->keyBy('id');
        $grantTypesCache = GrantType::all()->keyBy('slug');

        /*
         * Not Assigned has to exist before a run can file anything under it.
         * Its migration adds it, but a database that has not been migrated
         * would otherwise silently write nulls again — the exact state this
         * whole category was added to get rid of.
         */
        if (! isset($grantTypesCache[GrantTypeRule::NOT_ASSIGNED])) {
            $this->error('The "Not Assigned" grant type is missing. Run php artisan migrate first.');

            return 1;
        }

        // Same reasoning for the quartile every unranked publication falls to.
        // Named N/A now, N/Q before it was renamed; either will do.
        $notQuartiledId = PublicationQuartileRule::notQuartiledId($quartilesCache);

        if ($notQuartiledId === null) {
            $this->error(
                'No "not ranked" publication quartile found — expected one of: '
                . implode(', ', PublicationQuartileRule::NOT_QUARTILED_SLUGS)
                . '. Run php artisan db:seed --class=PublicationLookupSeeder first.'
            );

            return 1;
        }

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

        // Counted so the run says how the collaboration and the funding came
        // out, rather than leaving both to be discovered in the table
        // afterwards. Keyed by slug, with 'unknown' for the rows that could not
        // be placed.
        $collaborationCounts = [
            ResearchCollaborationRule::DIU => 0,
            ResearchCollaborationRule::VISITING_AND_DIU => 0,
            ResearchCollaborationRule::EXTERNAL => 0,
            'unknown' => 0,
        ];

        $grantCounts = [
            GrantTypeRule::DIU => 0,
            GrantTypeRule::EXTERNAL => 0,
            GrantTypeRule::SELF => 0,
            GrantTypeRule::NOT_ASSIGNED => 0,
            'unknown' => 0,
        ];

        // grant_type_id comes out of the file as an id, resolved against the
        // grant_types table on the day the pipeline ran. The same thing that
        // happened to created_by after a migrate:fresh can happen here, so an
        // id nothing matches becomes null instead of failing the row.
        $grantIdsDropped = 0;
        $collaborationIdsDropped = 0;

        $quartileCounts = [
            PublicationQuartileRule::Q1 => 0,
            PublicationQuartileRule::Q2 => 0,
            PublicationQuartileRule::Q3 => 0,
            PublicationQuartileRule::Q4 => 0,
            PublicationQuartileRule::NOT_QUARTILED => 0,
        ];

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

            /*
             * Resolve Quartile ID.
             *
             * Never null: a journal that is not ranked in a quartile is N/Q,
             * which is a row in the table and not an absence. The old lookup
             * left a null whenever the text missed, and "N/A" missed — it slugs
             * to "n-a", which is nothing.
             */
            $quartileSlug = PublicationQuartileRule::slugFor($pub['q_index'] ?? null);

            $quartile_id = $quartileSlug === PublicationQuartileRule::NOT_QUARTILED
                ? $notQuartiledId
                : ($quartilesCache[$quartileSlug]->id ?? null);

            $quartileCounts[$quartileSlug] = ($quartileCounts[$quartileSlug] ?? 0) + 1;

            /*
             * The collaboration, as publications:convert-pipeline worked it out
             * from the resolved author list.
             *
             * It is recomputed here when the file does not carry it, so a
             * third_step.json written before the pipeline started emitting the
             * field still imports with a collaboration rather than a null. Same
             * rule either way — ResearchCollaborationRule is the only place it
             * is written down.
             */
            $authors = is_array($pub['authors'] ?? null) ? $pub['authors'] : [];

            /*
             * An id in the file that the table no longer has is treated as if
             * the file had said nothing, and the rule answers instead. This is
             * the created_by problem again: the ids were resolved on the day
             * the pipeline ran, and a migrate:fresh since then renumbers the
             * lookup tables underneath them.
             */
            $collaboration_id = $pub['research_collaboration_id'] ?? null;

            if ($collaboration_id !== null && ! isset($collaborationsById[$collaboration_id])) {
                $collaboration_id = null;
                $collaborationIdsDropped++;
            }

            $collaborationSlug = $collaboration_id !== null
                ? ($collaborationsById[$collaboration_id]->slug ?? null)
                : ResearchCollaborationRule::slugFor($authors);

            if ($collaboration_id === null && $collaborationSlug !== null) {
                $collaboration_id = $collaborationsCache[$collaborationSlug]->id ?? null;
            }

            $collaborationCounts[$collaborationSlug ?? 'unknown'] =
                ($collaborationCounts[$collaborationSlug ?? 'unknown'] ?? 0) + 1;

            /*
             * The grant type, the same way: what the pipeline decided, or the
             * rule again when the file is older than the field. The CSV's
             * Award Money and Funding cells are both carried through into
             * third_step.json, so this command can work it out from scratch
             * exactly as the pipeline did.
             */
            $grant_type_id = $pub['grant_type_id'] ?? null;

            if ($grant_type_id !== null && ! isset($grantTypesById[$grant_type_id])) {
                $grant_type_id = null;
                $grantIdsDropped++;
            }

            $grantSlug = $grant_type_id !== null
                ? ($grantTypesById[$grant_type_id]->slug ?? null)
                : GrantTypeRule::slugFor(
                    $pub['award_money_csv'] ?? null,
                    $pub['funding'] ?? null,
                    $authors,
                );

            if ($grant_type_id === null && $grantSlug !== null) {
                $grant_type_id = $grantTypesCache[$grantSlug]->id ?? null;
            }

            $grantCounts[$grantSlug ?? 'unknown'] = ($grantCounts[$grantSlug ?? 'unknown'] ?? 0) + 1;

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
                    'grant_type_id' => $grant_type_id,
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
                ['Collaboration → DIU Researcher', $collaborationCounts[ResearchCollaborationRule::DIU]],
                ['Collaboration → Visiting Faculty + DIU Researcher', $collaborationCounts[ResearchCollaborationRule::VISITING_AND_DIU]],
                ['Collaboration → Collaboration (External)', $collaborationCounts[ResearchCollaborationRule::EXTERNAL]],
                ['Collaboration → left empty (no resolved authors)', $collaborationCounts['unknown']],
                ['Grant type → DIU Project', $grantCounts[GrantTypeRule::DIU]],
                ['Grant type → External Project', $grantCounts[GrantTypeRule::EXTERNAL]],
                ['Grant type → Self Funded', $grantCounts[GrantTypeRule::SELF]],
                ['Grant type → Not Assigned', $grantCounts[GrantTypeRule::NOT_ASSIGNED]],
                ['Grant type → left empty (not in the file)', $grantCounts['unknown']],
                ['Quartile → Q1 / Q2 / Q3 / Q4', $quartileCounts[PublicationQuartileRule::Q1]
                    . ' / ' . $quartileCounts[PublicationQuartileRule::Q2]
                    . ' / ' . $quartileCounts[PublicationQuartileRule::Q3]
                    . ' / ' . $quartileCounts[PublicationQuartileRule::Q4]],
                ['Quartile → N/Q (not ranked)', $quartileCounts[PublicationQuartileRule::NOT_QUARTILED]],
                ['grant_type_id dropped (no such grant type)', $grantIdsDropped],
                ['research_collaboration_id dropped (recomputed)', $collaborationIdsDropped],
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
