<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\Teacher;
use App\Support\GrantTypeRule;
use App\Support\PublicationQuartileRule;
use App\Support\PublicationTypeRule;
use App\Support\ResearchCollaborationRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportOldTeachersPublicationsCommand extends Command
{
    protected $signature = 'import:old-teachers-publications
                            {--file=teachers_publications_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0                               : Limit the number of teachers to process}
                            {--dry-run                               : Preview without writing to DB}
                            {--skip-existing                         : Skip already existing database entries (enabled by default)}
                            {--force                                 : Force re-importing all teachers and publications even if already entered}';

    protected $description = 'Import teacher publications from exported JSON, creating shared publication records and mapping polymorphic author roles';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        DB::disableQueryLog();
        gc_enable();

        $file   = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers-publications first.");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Invalid JSON in {$file}");
            return Command::FAILURE;
        }

        // Trim all string values recursively
        array_walk_recursive($data, function (&$val) {
            if (is_string($val)) {
                $val = trim($val);
            }
        });

        $limit        = (int) $this->option('limit');
        $processCount = ($limit > 0 && $limit < count($data)) ? $limit : count($data);

        $this->info($dryRun
            ? "🔍 DRY RUN — no changes will be written to DB"
            : "🚀 Importing publications and linking authors..."
        );
        $this->info("Total records to process: {$processCount}");
        $this->newLine();

        // 1. Load Publication Types lookup
        $pubTypeMap = [];
        $dbPubTypes = DB::table('publication_types')->get();
        foreach ($dbPubTypes as $pt) {
            $pubTypeMap[mb_strtolower($pt->name)] = $pt->id;
        }

        // 2. Load Publication Linkages lookup
        $linkageMap = [];
        $dbLinkages = DB::table('publication_linkages')->get();
        foreach ($dbLinkages as $pl) {
            $linkageMap[mb_strtolower($pl->name)] = $pl->id;
        }

        /*
         * 3. Load Publication Quartiles lookup, by slug.
         */
        $quartilesBySlug = DB::table('publication_quartiles')->get()->keyBy('slug');
        $notQuartiledId = PublicationQuartileRule::notQuartiledId($quartilesBySlug);

        if ($notQuartiledId === null) {
            $this->error(
                'No "not ranked" publication quartile found — expected one of: '
                . implode(', ', PublicationQuartileRule::NOT_QUARTILED_SLUGS) . '.'
            );

            return Command::FAILURE;
        }

        // Publication types by slug
        $typesBySlug = DB::table('publication_types')->get()->keyBy('slug');
        $notAssignedTypeId = $typesBySlug[PublicationTypeRule::NOT_ASSIGNED]->id ?? null;

        if ($notAssignedTypeId === null) {
            $this->error('The "Not Assigned" publication type is missing. Run php artisan migrate first.');

            return Command::FAILURE;
        }

        // Grant types and research collaborations, both by slug.
        $grantsBySlug = DB::table('grant_types')->get()->keyBy('slug');
        $collabsBySlug = DB::table('research_collaborations')->get()->keyBy('slug');

        foreach ([GrantTypeRule::DIU, GrantTypeRule::SELF, GrantTypeRule::NOT_ASSIGNED] as $needed) {
            if (! isset($grantsBySlug[$needed])) {
                $this->error("The \"{$needed}\" grant type is missing. Run php artisan migrate first.");

                return Command::FAILURE;
            }
        }

        // 4. Load Departments to Faculty mapping
        $deptToFaculty = [];
        $dbDepts = DB::table('departments')->select('id', 'faculty_id')->get();
        foreach ($dbDepts as $d) {
            $deptToFaculty[$d->id] = $d->faculty_id;
        }

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $pubCreated               = 0;
        $pubShared                = 0;
        $authorsLinked            = 0;
        $pubSkippedExisting       = 0;
        $teachersAlreadyImported  = 0;
        $skipped                  = 0;
        $teacherFailed            = 0;
        $recordFailed             = 0;
        $count                    = 0;
        $grantUpgrades            = 0;

        // Grant types by id
        $grantsById = DB::table('grant_types')->get()->keyBy('id');

        $grantCounts = [
            GrantTypeRule::DIU => 0,
            GrantTypeRule::SELF => 0,
            GrantTypeRule::NOT_ASSIGNED => 0,
        ];

        $collabCounts = [
            ResearchCollaborationRule::DIU => 0,
            ResearchCollaborationRule::VISITING_AND_DIU => 0,
            ResearchCollaborationRule::EXTERNAL => 0,
        ];

        foreach ($data as $index => $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId   = $record['_employee_id'] ?? $record['employee_id'] ?? null;
            $publications = $record['publications'] ?? [];

            if (!$employeeId) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $teacher = Teacher::where('employee_id', $employeeId)->first();
            if (!$teacher) {
                $teacherFailed++;
                $bar->advance();
                continue;
            }

            if (empty($publications)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $bar->setMessage("Processing: {$employeeId}");

            // Load all existing publications already linked to this teacher
            $existingTeacherPubs = DB::table('publication_authors')
                ->join('publications', 'publications.id', '=', 'publication_authors.publication_id')
                ->where('publication_authors.authorable_type', 'App\Models\Teacher')
                ->where('publication_authors.authorable_id', $teacher->id)
                ->select('publications.id', 'publications.slug', 'publications.title')
                ->get();

            $linkedSlugs = $existingTeacherPubs->pluck('slug')->filter()->flip()->all();
            $linkedTitles = $existingTeacherPubs->pluck('title')->filter()->map(fn ($t) => mb_strtolower(trim($t)))->flip()->all();

            // Check if all publications for this teacher are already linked
            if (!$force && $existingTeacherPubs->isNotEmpty()) {
                $allAlreadyLinked = true;
                foreach ($publications as $pubCheck) {
                    $tCheck = trim($pubCheck['title'] ?? '');
                    if ($tCheck === '') continue;
                    $sCheck = Str::slug($tCheck) ?: 'publication';
                    $tCheckLower = mb_strtolower($tCheck);

                    if (!isset($linkedSlugs[$sCheck]) && !isset($linkedTitles[$tCheckLower])) {
                        $allAlreadyLinked = false;
                        break;
                    }
                }

                if ($allAlreadyLinked) {
                    $teachersAlreadyImported++;
                    $pubSkippedExisting += count($publications);
                    $bar->advance();
                    unset($data[$index], $existingTeacherPubs, $linkedSlugs, $linkedTitles);
                    if ($count % 50 === 0) {
                        gc_collect_cycles();
                    }
                    continue;
                }
            }

            // Resolve faculty and department for the teacher
            $deptId    = $teacher->department_id;
            $facultyId = $deptToFaculty[$deptId] ?? null;

            foreach ($publications as $pub) {
                $title = trim($pub['title'] ?? '');
                if ($title === '') {
                    $skipped++;
                    continue;
                }

                $titleSlug  = Str::slug($title) ?: 'publication';
                $titleLower = mb_strtolower($title);

                // Skip if this specific publication is already linked to this teacher
                if (!$force && (isset($linkedSlugs[$titleSlug]) || isset($linkedTitles[$titleLower]))) {
                    $pubSkippedExisting++;
                    continue;
                }

                $typeSlug   = PublicationTypeRule::slugFor($pub['publication_type'] ?? null);
                $typeId     = $typesBySlug[$typeSlug]->id ?? $notAssignedTypeId;
                $linkageId  = $linkageMap[mb_strtolower($pub['linkage'] ?? '')] ?? $linkageMap['non-indexed'] ?? null;
                $authorRole = $pub['author_role'] ?? 'co_author';

                $quartileSlug = PublicationQuartileRule::slugFor($pub['quartile'] ?? null);
                $quartileId = $quartileSlug === PublicationQuartileRule::NOT_QUARTILED
                    ? $notQuartiledId
                    : ($quartilesBySlug[$quartileSlug]->id ?? $notQuartiledId);

                $grantSlug = GrantTypeRule::slugForOldSitePublication(
                    (int) ($pub['publication_year'] ?? 0) ?: null,
                    $teacher->joining_date,
                );

                $grantCounts[$grantSlug] = ($grantCounts[$grantSlug] ?? 0) + 1;

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY RUN] %-15s → Title: %-40s | Type: %-15s | Linkage: %-10s | Role: %-13s | Pub %s vs joined %s → %s",
                        $employeeId,
                        mb_substr($title, 0, 40),
                        $pub['publication_type'] ?? 'Journal Article',
                        $pub['linkage'] ?? 'Non-Indexed',
                        $authorRole,
                        $pub['publication_year'] ?? '—',
                        filled($teacher->joining_date) ? $teacher->joining_date->format('Y') : '—',
                        $grantSlug
                    ));
                    $pubCreated++;
                    $authorsLinked++;
                    continue;
                }

                DB::beginTransaction();
                try {
                    $pubYear = !empty($pub['publication_year']) ? (int) $pub['publication_year'] : null;

                    // 1. Check if publication already exists by Slug (indexed) or exact Title
                    $existingPub = Publication::where('slug', $titleSlug)->first();
                    if (!$existingPub && $title !== '') {
                        $existingPub = Publication::where('title', $title)->first();
                    }

                    if ($existingPub) {
                        $pubId = $existingPub->id;

                        // 2. Check if this author is already linked
                        $authorLinkExists = DB::table('publication_authors')
                            ->where([
                                'publication_id'  => $pubId,
                                'authorable_type' => 'App\Models\Teacher',
                                'authorable_id'   => $teacher->id,
                            ])->exists();

                        if ($authorLinkExists && !$force) {
                            DB::rollBack();
                            $pubSkippedExisting++;
                            continue;
                        }

                        // 3. Update department & missing fields if empty, and mark come_from_old_site
                        $updateData = ['come_from_old_site' => 1];
                        if (empty($existingPub->department_id) && $deptId) {
                            $updateData['department_id'] = $deptId;
                        }
                        if (empty($existingPub->faculty_id) && $facultyId) {
                            $updateData['faculty_id'] = $facultyId;
                        }
                        if (empty($existingPub->publication_year) && $pubYear) {
                            $updateData['publication_year'] = $pubYear;
                        }
                        if (empty($existingPub->journal_name) && !empty($pub['journal_name'])) {
                            $updateData['journal_name'] = $pub['journal_name'];
                        }
                        if (empty($existingPub->journal_link) && !empty($pub['journal_link'])) {
                            $updateData['journal_link'] = $pub['journal_link'];
                        }

                        $existingPub->update($updateData);
                        $pubShared++;
                    } else {
                        // 4. Create publication
                        $newPub = Publication::updateOrCreate(
                            [
                                'slug' => $titleSlug,
                            ],
                            [
                                'publication_type_id'     => $typeId,
                                'publication_linkage_id'  => $linkageId,
                                'publication_quartile_id' => $quartileId,
                                'grant_type_id'           => $grantsBySlug[$grantSlug]->id,
                                'title'                   => $title,
                                'journal_name'            => $pub['journal_name'] ?? null,
                                'journal_link'            => $pub['journal_link'] ?? null,
                                'publication_year'        => $pubYear,
                                'status'                  => 'approved',
                                'faculty_id'              => $facultyId,
                                'department_id'           => $deptId,
                                'h_index'                 => $pub['h_index'] ?? null,
                                'citescore'               => $pub['citescore'] ?? null,
                                'impact_factor'           => $pub['impact_factor'] ?? null,
                                'keywords'                => $pub['keywords'] ?? null,
                                'abstract'                => $pub['abstract'] ?? null,
                                'come_from_pd'            => 0,
                                'come_from_old_site'      => 1,
                            ]
                        );
                        $pubId = $newPub->id;
                        $pubCreated++;
                    }

                    // Link the teacher as an author
                    DB::table('publication_authors')->updateOrInsert(
                        [
                            'publication_id'  => $pubId,
                            'authorable_type' => 'App\Models\Teacher',
                            'authorable_id'   => $teacher->id,
                        ],
                        [
                            'author_role'     => $authorRole,
                            'sort_order'      => 0,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]
                    );

                    // Add to local cache so subsequent publications in the same file know this author is linked
                    $linkedSlugs[$titleSlug] = true;
                    $linkedTitles[$titleLower] = true;

                    // Recompute research collaboration
                    $pubAuthors = DB::table('publication_authors')
                        ->where('publication_id', $pubId)
                        ->pluck('authorable_type')
                        ->map(fn ($type): array => ['authorable_type' => $type])
                        ->all();

                    $collabSlug = ResearchCollaborationRule::slugFor($pubAuthors);
                    $collabId = $collabSlug !== null ? ($collabsBySlug[$collabSlug]->id ?? null) : null;

                    if ($collabId !== null) {
                        DB::table('publications')->where('id', $pubId)
                            ->update(['research_collaboration_id' => $collabId, 'updated_at' => now()]);

                        $collabCounts[$collabSlug] = ($collabCounts[$collabSlug] ?? 0) + 1;
                    }

                    // Grant type on shared publication
                    if ($existingPub) {
                        $current = DB::table('publications')->where('id', $pubId)->value('grant_type_id');
                        $currentSlug = $grantsById[$current]->slug ?? null;

                        $weak = [null, GrantTypeRule::NOT_ASSIGNED, GrantTypeRule::SELF];

                        if ($grantSlug === GrantTypeRule::DIU && in_array($currentSlug, $weak, true)) {
                            DB::table('publications')->where('id', $pubId)
                                ->update(['grant_type_id' => $grantsBySlug[GrantTypeRule::DIU]->id, 'updated_at' => now()]);
                            $grantUpgrades++;
                        } elseif ($currentSlug === null) {
                            DB::table('publications')->where('id', $pubId)
                                ->update(['grant_type_id' => $grantsBySlug[$grantSlug]->id, 'updated_at' => now()]);
                        }
                    }

                    $authorsLinked++;
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->newLine();
                    $this->error("Failed to import publication for {$employeeId} title '{$title}': " . $e->getMessage());
                    $recordFailed++;
                }

                unset($existingPub, $newPub, $updateData, $pubAuthors);
            }

            unset($data[$index], $existingTeacherPubs, $linkedSlugs, $linkedTitles);
            if ($count % 50 === 0) {
                gc_collect_cycles();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed',                              $count],
                ['Teachers skipped (already fully imported)',       $teachersAlreadyImported],
                ['Publications → NEW (created)',                    $pubCreated],
                ['Publications → SHARED (existing linked)',          $pubShared],
                ['Publications → SKIPPED (already entered)',         $pubSkippedExisting],
                ['Authorships Linked (Pivot records)',               $authorsLinked],
                ['Records skipped (empty data)',                    $skipped],
                ['Teachers not found in new DB',                    $teacherFailed],
                ['Individual record failures',                      $recordFailed],
                ['Grant → DIU Project (published after joining)',   $grantCounts[GrantTypeRule::DIU]],
                ['Grant → Self Funded (published before joining)',  $grantCounts[GrantTypeRule::SELF]],
                ['Grant → Not Assigned (a date was missing)',       $grantCounts[GrantTypeRule::NOT_ASSIGNED]],
                ['Grant upgraded to DIU Project on a shared paper', $grantUpgrades],
                ['Collaboration → DIU Researcher',                  $collabCounts[ResearchCollaborationRule::DIU]],
                ['Collaboration → Visiting Faculty + DIU',          $collabCounts[ResearchCollaborationRule::VISITING_AND_DIU]],
                ['Collaboration → Collaboration (External)',        $collabCounts[ResearchCollaborationRule::EXTERNAL]],
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Import complete.");
        }

        return Command::SUCCESS;
    }
}
