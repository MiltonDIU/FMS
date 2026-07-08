<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\Teacher;
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

        // 3. Load Publication Quartiles lookup
        $quartileMap = [];
        $dbQuartiles = DB::table('publication_quartiles')->get();
        foreach ($dbQuartiles as $pq) {
            $quartileMap[mb_strtolower($pq->name)] = $pq->id;
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
                $quartileId = $quartileMap[mb_strtolower($pub['quartile'] ?? '')] ?? $quartileMap['n/q'] ?? null;
                $authorRole = $pub['author_role'] ?? 'co_author';

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY RUN] %-15s → Title: %-40s | Type: %-15s | Linkage: %-10s | Role: %s",
                        $employeeId,
                        mb_substr($title, 0, 40),
                        $pub['publication_type'] ?? 'Journal Article',
                        $pub['linkage'] ?? 'Non-Indexed',
                        $authorRole
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
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Import complete.");
        }

        return Command::SUCCESS;
    }
}
