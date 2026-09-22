<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\Teacher;
use App\Support\GrantTypeRule;
use App\Support\PublicationQuartileRule;
use App\Support\ResearchCollaborationRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOldTeachersPublicationsCommand extends Command
{
    protected $signature = 'import:old-teachers-publications
                            {--file=teachers_publications_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0                               : Limit the number of teachers to process}
                            {--dry-run                               : Preview without writing to DB}
                            {--skip-existing                         : Skip already existing database entries}';

    protected $description = 'Import teacher publications from exported JSON, creating shared publication records and mapping polymorphic author roles';

    public function handle(): int
    {
        $file   = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

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
         *
         * It used to be keyed by lowercased name with a hard-coded 'n/q'
         * fallback, which stopped working the moment that row was renamed to
         * N/A: 13,179 of the 13,846 publications in this export carry the text
         * "N/Q", and every one of them would have been written as a null.
         * PublicationQuartileRule reads the text and knows both spellings of
         * the row, so neither the export's wording nor the row's name can
         * break it again.
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

        $pubCreated     = 0;
        $pubShared      = 0;
        $authorsLinked  = 0;
        $skipped        = 0;
        $teacherFailed  = 0;
        $recordFailed   = 0;
        $count          = 0;
        $grantUpgrades  = 0;

        // Grant types by id too, for reading back what a shared publication
        // already holds before deciding whether to touch it.
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

        foreach ($data as $record) {
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

            // Resolve faculty and department for the teacher
            $deptId    = $teacher->department_id;
            $facultyId = $deptToFaculty[$deptId] ?? null;

            foreach ($publications as $pub) {
                $title = trim($pub['title'] ?? '');
                if ($title === '') {
                    $skipped++;
                    continue;
                }

                // Resolve type, linkage, quartile IDs
                $typeId     = $pubTypeMap[mb_strtolower($pub['publication_type'] ?? '')] ?? $pubTypeMap['journal article'] ?? null;
                $linkageId  = $linkageMap[mb_strtolower($pub['linkage'] ?? '')] ?? $linkageMap['non-indexed'] ?? null;
                $authorRole = $pub['author_role'] ?? 'co_author';

                $quartileSlug = PublicationQuartileRule::slugFor($pub['quartile'] ?? null);
                $quartileId = $quartileSlug === PublicationQuartileRule::NOT_QUARTILED
                    ? $notQuartiledId
                    : ($quartilesBySlug[$quartileSlug]->id ?? $notQuartiledId);

                /*
                 * The grant type this teacher's own dates imply for the paper.
                 * Applied below rather than here, because whether it is allowed
                 * to overwrite what is already on the record depends on which
                 * answer it is.
                 */
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
                     // Check if publication already exists by Title (avoid duplicates)
                     $existingPubs = Publication::where('title', $title)->get();
                     $existingPub = null;

                     $pubYear = $pub['publication_year'] ?? null;
                     $jName = trim($pub['journal_name'] ?? '');

                     foreach ($existingPubs as $ep) {
                         // 1. Same year?
                         if ($pubYear && $ep->publication_year == $pubYear) {
                             $existingPub = $ep;
                             break;
                         }
                         // 2. Same journal name?
                         if ($jName !== '' && $ep->journal_name && stripos($ep->journal_name, $jName) !== false) {
                             $existingPub = $ep;
                             break;
                         }
                         // 3. Same department or faculty?
                         if ($ep->department_id == $deptId || $ep->faculty_id == $facultyId) {
                             $existingPub = $ep;
                             break;
                         }
                     }

                     if ($existingPub) {
                         $pubId = $existingPub->id;
                         if ($this->option('skip-existing')) {
                             $linkExists = DB::table('publication_authors')
                                 ->where([
                                     'publication_id'  => $pubId,
                                     'authorable_type' => 'App\Models\Teacher',
                                     'authorable_id'   => $teacher->id,
                                 ])->exists();
                             if ($linkExists) {
                                 DB::rollBack();
                                 continue;
                             }
                         }
                         $pubShared++;
                     } else {
                         $newPub = Publication::create([
                             'publication_type_id'     => $typeId,
                             'publication_linkage_id'  => $linkageId,
                             'publication_quartile_id' => $quartileId,
                             'grant_type_id'           => $grantsBySlug[$grantSlug]->id,
                             'title'                   => $title,
                             'journal_name'            => $pub['journal_name'] ?? null,
                             'journal_link'            => $pub['journal_link'] ?? null,
                             'publication_year'        => $pubYear,
                             'status'                  => 'approved', // Default approved for old DB publications
                             'faculty_id'              => $facultyId,
                             'department_id'           => $deptId,
                             'h_index'                 => $pub['h_index'] ?? null,
                             'citescore'               => $pub['citescore'] ?? null,
                             'impact_factor'           => $pub['impact_factor'] ?? null,
                             'keywords'                => $pub['keywords'] ?? null,
                             'abstract'                => $pub['abstract'] ?? null,
                             'come_from_pd'            => 0,
                             'come_from_old_site'      => 1,
                         ]);
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

                    /*
                     * The collaboration, recomputed from everyone now on the
                     * paper rather than from the teacher just linked.
                     *
                     * It has to be done after the link and from the pivot,
                     * because this import shares publications: a paper already
                     * imported from PD may carry authors from the authors table
                     * — visiting faculty, outside researchers — and attaching
                     * one of our teachers to it turns it from an external
                     * collaboration into a joint one. Reading only the teacher
                     * in hand would call every paper DIU Researcher.
                     *
                     * Same rule as everywhere else; ResearchCollaborationRule
                     * is the only place it is written down.
                     */
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

                    /*
                     * Grant type on a publication that already existed.
                     *
                     * A paper is DIU-funded work if ANY of its authors was here
                     * when it came out, so a qualifying teacher upgrades it —
                     * but only from Not Assigned or Self Funded, the two values
                     * that mean "nothing better is known". A DIU Project,
                     * External Project or Govt. Funded already on the record was
                     * read off the award or the funding column in the PD export,
                     * which is somebody's written record of who paid; that beats
                     * an inference drawn from a joining date, so it stands.
                     */
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
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed',                         $count],
                ['Publications → NEW (created)',               $pubCreated],
                ['Publications → SHARED (existing linked)',     $pubShared],
                ['Authorships Linked (Pivot records)',          $authorsLinked],
                ['Records skipped (empty data)',               $skipped],
                ['Teachers not found in new DB',               $teacherFailed],
                ['Individual record failures',                 $recordFailed],
                ['Grant → DIU Project (published after joining)',  $grantCounts[GrantTypeRule::DIU]],
                ['Grant → Self Funded (published before joining)', $grantCounts[GrantTypeRule::SELF]],
                ['Grant → Not Assigned (a date was missing)',      $grantCounts[GrantTypeRule::NOT_ASSIGNED]],
                ['Grant upgraded to DIU Project on a shared paper', $grantUpgrades],
                ['Collaboration → DIU Researcher',                 $collabCounts[ResearchCollaborationRule::DIU]],
                ['Collaboration → Visiting Faculty + DIU',         $collabCounts[ResearchCollaborationRule::VISITING_AND_DIU]],
                ['Collaboration → Collaboration (External)',       $collabCounts[ResearchCollaborationRule::EXTERNAL]],
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Import complete.");
        }

        return Command::SUCCESS;
    }
}
