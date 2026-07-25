<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Publication;
use App\Models\PublicationIncentive;

class ImportPublicationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:pd_publication_data 
                            {--limit= : Limit the number of publications to import} 
                            {--json=storage/app/public/export/publications_all.json : Path to the source JSON file relative to project root}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import publications along with their authors (morph mappings) and incentives from the generated JSON export.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonRelativePath = $this->option('json');
        $jsonPath = base_path($jsonRelativePath);

        if (!file_exists($jsonPath)) {
            $this->error("Error: JSON file not found at: $jsonPath");
            return 1;
        }

        $this->info("Reading publications JSON file...");
        $jsonContent = file_get_contents($jsonPath);
        $publications = json_decode($jsonContent, true);

        if (!is_array($publications)) {
            $this->error("Error: Failed to parse JSON file or invalid array format.");
            return 1;
        }

        $limit = $this->option('limit');
        $totalToProcess = count($publications);
        if ($limit && is_numeric($limit)) {
            $totalToProcess = min((int)$limit, count($publications));
            $this->info("Limiting execution to import the first $totalToProcess publications.");
        }

        $importedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $this->info("Importing publications and mapping relation data...");

        for ($i = 0; $i < $totalToProcess; $i++) {
            $pub = $publications[$i];
            $title = trim($pub['title']);

            // Check if publication already exists by title
            $existing = Publication::whereRaw('LOWER(title) = ?', [strtolower($title)])->first();
            if ($existing) {
                $this->line("[$i] Skipped (Already exists): $title");
                $skippedCount++;
                continue;
            }

            // Determine publication type
            $type_id = 1; // Journal Article
            $journal_name_lower = strtolower($pub['journal_name'] ?? '');
            if (
                str_contains($journal_name_lower, 'conference') ||
                str_contains($journal_name_lower, 'proceeding') ||
                str_contains($journal_name_lower, 'workshop') ||
                str_contains($journal_name_lower, 'symposium') ||
                str_contains($journal_name_lower, 'congress')
            ) {
                $type_id = 2; // Conference Proceeding
            }

            // Determine linkage (Scopus = 1, Non-Indexed = 5)
            $linkage_id = 5;
            if (!empty($pub['q_index']) || !empty($pub['citescore'])) {
                $linkage_id = 1; // Scopus
            }

            // Determine quartile (Q1=1, Q2=2, Q3=3, Q4=4, N/Q=5)
            $quartile_id = 5;
            $q_index = strtolower(trim($pub['q_index'] ?? ''));
            if ($q_index === 'q1') {
                $quartile_id = 1;
            } elseif ($q_index === 'q2') {
                $quartile_id = 2;
            } elseif ($q_index === 'q3') {
                $quartile_id = 3;
            } elseif ($q_index === 'q4') {
                $quartile_id = 4;
            }

            DB::beginTransaction();
            try {
                // 1. Create Publication
                $newPub = Publication::create([
                    'title' => $title,
                    'abstract' => $pub['abstract'] ?? null,
                    'journal_name' => $pub['journal_name'] ?? null,
                    'journal_link' => $pub['journal_link'] ?? null,
                    'publication_year' => $pub['publication_year'] ?? null,
                    'citescore' => !empty($pub['citescore']) ? (float)$pub['citescore'] : null,
                    'faculty_id' => $pub['faculty_id'] ?? null,
                    'department_id' => $pub['department_id'] ?? null,
                    'publication_type_id' => $type_id,
                    'publication_linkage_id' => $linkage_id,
                    'publication_quartile_id' => $quartile_id,
                    'status' => 'approved',
                    'student_involvement' => false,
                    'is_featured' => false
                ]);

                // 2. Insert Authors
                if (isset($pub['authors']) && is_array($pub['authors'])) {
                    foreach ($pub['authors'] as $author) {
                        $role_mapped = 'co_author';
                        $role_lower = strtolower(trim($author['author_role']));
                        if (str_contains($role_lower, 'first')) {
                            $role_mapped = 'first';
                        } elseif (str_contains($role_lower, 'corresponding')) {
                            $role_mapped = 'corresponding';
                        }

                        DB::table('publication_authors')->insert([
                            'publication_id' => $newPub->id,
                            'authorable_type' => $author['authorable_type'],
                            'authorable_id' => $author['authorable_id'],
                            'author_role' => $role_mapped,
                            'incentive_amount' => (float)$author['incentive_amount'],
                            'sort_order' => (int)$author['sort_order'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }

                // 3. Insert Publication Incentive
                if (isset($pub['db_incentive']) && is_array($pub['db_incentive'])) {
                    $db_inc = $pub['db_incentive'];
                    
                    // Sum incentive amounts from authors as total incentive amount
                    $sum_incentive = 0.00;
                    if (isset($pub['authors']) && is_array($pub['authors'])) {
                        $sum_incentive = array_sum(array_column($pub['authors'], 'incentive_amount'));
                    }

                    $total_amount = $sum_incentive > 0 ? $sum_incentive : (float)($db_inc['total_amount'] ?? 0.00);

                    // Note: We insert directly to bypass model booted hook logging overhead during command execution,
                    // preserving the exact timestamps and created_by user ID from the JSON file
                    DB::table('publication_incentives')->insert([
                        'publication_id' => $newPub->id,
                        'total_amount' => $total_amount,
                        'status' => $db_inc['status'] ?? 'pending',
                        'created_by' => $db_inc['created_by'] ?? 5,
                        'created_at' => $db_inc['created_at'] ?? now(),
                        'updated_at' => $db_inc['updated_at'] ?? now()
                    ]);
                }

                DB::commit();
                $this->info("[$i] Imported: $title");
                $importedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("[$i] Failed to import: $title. Error: " . $e->getMessage());
                $failedCount++;
            }
        }

        $this->info("\n--- Import Process Completed ---");
        $this->line("Publications Processed: $totalToProcess");
        $this->line("Successfully Imported: $importedCount");
        $this->line("Skipped (Already Existed): $skippedCount");
        $this->line("Failed: $failedCount");

        return 0;
    }
}
