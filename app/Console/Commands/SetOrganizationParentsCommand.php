<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetOrganizationParentsCommand extends Command
{
    protected $signature = 'organizations:set-parents
                            {--dry-run : Preview without writing to DB}';

    protected $description = 'Automatically set parent_id for known sub-organizations based on name patterns.';

    /**
     * Parent-child name maps.
     * Key = exact name of parent organization
     * Value = array of LIKE patterns that match sub-orgs belonging to this parent
     */
    const PARENT_MAP = [
        'Daffodil International University' => [
            'Daffodil International University',
            'Daffodil International Professional Training Institute',
            'Daffodil Institute of Information Technology',
            'HRDI, Daffodil',
            'Human Resources Development Institute, Daffodil',
            'Career Development Center (CDC), Daffodil',
            'Faculty of Business and Economics, Daffodil',
            'ETE department of Daffodil',
            'Daffodil International University Alumni Association',
            'Daffodil International University Business and Education Club',
            'Journal of Business and Economics, Daffodil',
            'Cisco Local Academy (Daffodil',
            'Research Division, DIU',
            'Division of Research, DIU',
            'IQAC, DIU',
            'HRDI, DIU',
            'HRDI of DIU',
            'DIU press',
            'division of research of DIU',
            'Dept. of THM, DIU',
            'Daffodil International University & Daffodil Education Network',
            'Department of Business Administration, Faculty of Business and Entrepreneurship, Daffodil',
        ],
        'University of Dhaka' => [
            'Department of Statistics, University of Dhaka',
            'Bureau of Business Research, University of Dhaka',
            'Bureau of Business Research Faculty of Business Studies, University of Dhaka',
            'Department of English, University of Dhaka',
            'The Bureau of Business Research, University of Dhaka',
            'Faculty of Business Studies in University of Dhaka',
            'Institute of Statistical Research and Training (ISRT), University of Dhaka',
            'Dhaka University Bureau of Business Research',
            'Dhaka University Alumnai Association',
            'Dhaka University Alumni Association',
            'IBA Alumni Association, University of Dhaka',
            'Dhaka University Club',
            'Accounting Alumni Association of University of Dhaka',
            'Dhaka University Marketing Alumni Association',
            'Dhaka University Bureau of Business Research',
        ],
        'Jahangirnagar University' => [
            'Statistical Alumni Association, Jahangirnagar University',
            'Department of Statistics Jahangirnagar University',
        ],
        'Chittagong University' => [
            'Chittagong University Club, Chittagong University',
            'Rajshahi University Alumni Association',
        ],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->info('🔍 DRY RUN — no changes will be written to DB');
        }

        foreach (self::PARENT_MAP as $parentName => $patterns) {
            $parent = Organization::where('name', $parentName)->first();
            if (!$parent) {
                $this->warn("  ⚠ Parent not found: {$parentName}");
                continue;
            }

            $this->line("\n<fg=cyan>Parent:</> {$parentName} (ID: {$parent->id})");

            foreach ($patterns as $pattern) {
                $matches = Organization::where('name', 'like', "%{$pattern}%")
                    ->where('id', '!=', $parent->id)
                    ->whereNull('parent_id')
                    ->get();

                foreach ($matches as $org) {
                    $this->line("  → [{$org->id}] {$org->name}");

                    if (!$dryRun) {
                        $org->update(['parent_id' => $parent->id]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN complete. Would update {$skipped} organizations.");
        } else {
            $this->info("✅ Done! Updated {$updated} organizations with parent_id.");
        }

        return Command::SUCCESS;
    }
}
