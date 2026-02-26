<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class ExportOldTeachersAwardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:old-teachers-awards {--output=teachers_awards_export.json} {--limit=0}';
    protected $description = 'Export teacher awards/scholarships from old database (only for teachers in dfd_add)';

    public function handle(): int
    {
        $this->info('Fetching old teacher awards...');
        $limit = (int) $this->option('limit');

        $query = DB::connection('old_db')
            ->table('teacher as t')
            ->join('dfd_add as dfd', 'dfd.teacher_id', '=', 't.id')
            ->select('t.id as old_teacher_id', 't.employeeID', 't.awardScholarship')
            ->whereNotNull('t.awardScholarship')
            ->where('t.awardScholarship', '!=', '')
            ->groupBy('t.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get();
        $this->info("Found {$records->count()} teachers with awards.");

        $exportData = [];
        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            $awards = $this->parseAwards($record->awardScholarship);
            if (!empty($awards)) {
                $exportData[] = [
                    'old_teacher_id' => $record->old_teacher_id,
                    'employee_id'    => $record->employeeID,
                    'awards'         => $awards,
                ];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Prepared " . count($exportData) . " records for export.");
        
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error("JSON encoding failed: " . json_last_error_msg());
            return 1;
        }

        $filename = $this->option('output');
        $path = storage_path('app/public/exports/' . $filename);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $bytes = file_put_contents($path, $json);
        if ($bytes === false) {
            $this->error("Failed to write to file: {$path}");
            return 1;
        }

        $this->info("✅ Export complete ({$bytes} bytes) → {$path}");
        return 0;
    }

    private function parseAwards(string $raw): array
    {
        if (empty(trim($raw))) return [];

        // Ensure raw string is UTF-8
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

        // Clean HTML and convert list items/breaks to newlines
        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>'], "\n", $raw);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Split by newline and filter empty ones
        $lines = explode("\n", $cleaned);
        $awards = [];

        foreach ($lines as $line) {
            // Clean excessive whitespace and non-breaking spaces
            $line = preg_replace('/\s+/', ' ', $line);
            $line = str_replace("\xc2\xa0", ' ', $line);
            $line = trim($line, " \t\n\r\0\x0B-•*"); // Also trim common list bullet points
            if (empty($line) || strlen($line) < 3) continue;

            // Final safety check for UTF-8
            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');

            // 1. Extract Year (usually 4 digits starting with 19 or 20)
            $year = null;
            if (preg_match('/\b(19|20)\d{2}\b/', $line, $matches)) {
                $year = $matches[0];
            }

            // 2. Extract Awarding Body
            $awardingBody = null;
            
            // Patterns to look for in order of priority
            $patterns = [
                // Pattern: "Award Title (for/to [Organization])"
                '/\((?:for|to|at|by|from)\s+((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)\)/',
                
                // Pattern: "by/from/at/to [Organization]"
                '/(?:by|from|at|to)\s+((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)/',
                
                // Pattern: ", [Organization]" or "- [Organization]" at the end (after year or title)
                '/[,:-]\s*([A-Z][A-Z\s0-9]+)[\.\s]*$/', // All caps like "DIU."
                '/[,:-]\s*((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)[\.\s]*$/', // Mixed case
            ];

            $instKeywords = ['University', 'College', 'School', 'Ministry', 'Division', 'Board', 'Institute', 'Department', 'Committee', 'Council', 'Center', 'Academy', 'Organization', 'Agency', 'Association', 'Foundation', 'Society', 'DIU', 'Cisco', 'UGC', 'ICT', 'Govt'];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $candidate = trim($matches[1]);
                    
                    // Cleanup candidate from common trailing words or punctuation
                    $candidate = preg_replace('/[,;\.\(\)\s]+$/', '', $candidate);
                    $candidate = trim($candidate);

                    if (empty($candidate) || strlen($candidate) < 2) continue;
                    if (is_numeric($candidate)) continue; // Don't capture just a year

                    // Check if it contains institution keywords or is all caps (like DIU)
                    $isInstitution = preg_match('/^[A-Z]{2,}$/', $candidate); // All caps like DIU, UGC
                    if (!$isInstitution) {
                        foreach ($instKeywords as $kw) {
                            if (stripos($candidate, $kw) !== false) {
                                $isInstitution = true;
                                break;
                            }
                        }
                    }
                    
                    if ($isInstitution) {
                        // Avoid capturing the year as part of the awarding body if it leaked in
                        $candidate = preg_replace('/\b(19|20)\d{2}\b/', '', $candidate);
                        $awardingBody = trim($candidate, " \t\n\r\0\x0B,-.");
                        if (!empty($awardingBody)) break;
                    }
                }
            }

            // Fallback: if no awarding body found but "title" contains institution, try a broader search
            if (!$awardingBody) {
                $instPattern = '/(?:University|College|Ministry|Division|Board|Institute|Academy|Society|Center)\s+(?:of|at|in)?\s*[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*/i';
                if (preg_match($instPattern, $line, $matches)) {
                    $candidate = trim($matches[0]);
                    // Don't just match "University" or "Scholarship"
                    if (strlen($candidate) > 10) {
                        $awardingBody = $candidate;
                    }
                }
            }

            $awards[] = [
                'title'         => $line,
                'awarding_body' => $awardingBody,
                'type'          => $this->guessType($line),
                'year'          => $year,
                'remarks'       => null,
            ];
        }

        return $awards;
    }

    private function guessType(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'scholarship')) return 'scholarship';
        if (str_contains($text, 'recognition')) return 'recognition';
        if (str_contains($text, 'appreciation')) return 'appreciation';
        return 'award';
    }
}
