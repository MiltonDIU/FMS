<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Teacher;

class FillTeacherFullNameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teachers:fill-fullname {--limit= : Limit the number of teachers to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean academic titles and fill the full_name column in teachers table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $query = Teacher::with('user');
        if ($limit !== null && is_numeric($limit)) {
            $query->take((int)$limit);
        }
        $teachers = $query->get();
        $total = count($teachers);
        $this->info("Processing {$total} teachers...");

        $count = 0;
        foreach ($teachers as $t) {
            $rawName = '';
            if ($t->user && !empty($t->user->name)) {
                $rawName = $t->user->name;
            } else {
                $rawName = trim($t->first_name . ' ' . $t->middle_name . ' ' . $t->last_name);
            }

            if (empty($rawName)) {
                continue;
            }

            $cleanedName = $this->cleanNamePrefixes($rawName);
            $t->full_name = $cleanedName;
            $t->save();
            $count++;
        }

        $this->info("Successfully populated {$count}/{$total} teachers' full names.");
        return 0;
    }

    /**
     * Clean prefix titles from name case-insensitively
     */
    private function cleanNamePrefixes(string $name): string
    {
        $prefixes = [
            'professors\s+dr',
            'professor',
            'prof',
            'engr',
            'mst',
            'mr',
            'ms',
            'dr'
        ];

        $cleaned = $name;
        $matched = true;

        while ($matched) {
            $matched = false;
            foreach ($prefixes as $p) {
                // Match prefix word boundary, optional dot, followed by space
                $pattern = '/^' . $p . '\b\.?\s+/i';
                if (preg_match($pattern, $cleaned)) {
                    $cleaned = preg_replace($pattern, '', $cleaned);
                    $matched = true;
                    break;
                }
            }
        }

        // Normalize spaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return trim($cleaned);
    }
}
