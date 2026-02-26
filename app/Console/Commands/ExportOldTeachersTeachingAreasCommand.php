<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportOldTeachersTeachingAreasCommand extends Command
{
    protected $signature = 'export:old-teachers-teaching-areas
                            {--limit=0 : Limit the number of records}
                            {--output=teachers_teaching_areas_export.json : Output filename in storage/app/public/exports/}';

    protected $description = 'Export teacher teaching areas from the old database';

    public function handle(): int
    {
        $this->info('Fetching old teacher teaching areas...');
        $limit = (int) $this->option('limit');

        // Explicitly ignore problematic teacher IDs requested by the user
        $ignoredIds = [18, 285, 40, 373, 163, 515, 556, 4];

        $query = DB::connection('old_db')
            ->table('teacher as t')
            ->join('dfd_add as dfd', 'dfd.teacher_id', '=', 't.id')
            ->select('t.id as old_teacher_id', 't.employeeID', 't.teachingArea')
            ->whereNotNull('t.teachingArea')
            ->where('t.teachingArea', '!=', '')
            ->whereNotIn('t.id', $ignoredIds)
            ->groupBy('t.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get();
        $this->info("Found {$records->count()} teachers with teaching areas.");

        $exportData = [];
        $reviewData = [];
        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            $result = $this->parseTeachingAreas($record->teachingArea);
            
            if (!empty($result['clean'])) {
                $exportData[] = [
                    'old_teacher_id' => $record->old_teacher_id,
                    'employee_id'    => $record->employeeID,
                    'teaching_areas' => $result['clean'],
                ];
            }

            if (!empty($result['review'])) {
                $reviewData[] = [
                    'old_teacher_id' => $record->old_teacher_id,
                    'employee_id'    => $record->employeeID,
                    'raw_content'    => $record->teachingArea,
                    'flagged_items'  => $result['review'],
                ];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Prepared " . count($exportData) . " records for export.");
        $this->warn("Identified " . count($reviewData) . " records needing manual review.");
        
        // Export Clean Data
        $this->saveJson($this->option('output'), $exportData);
        
        // Export Review Data
        $reviewFilename = str_replace('.json', '_review.json', $this->option('output'));
        $this->saveJson($reviewFilename, $reviewData);

        return 0;
    }

    private function saveJson(string $filename, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error("JSON encoding failed for {$filename}: " . json_last_error_msg());
            return;
        }

        $path = storage_path('app/public/exports/' . $filename);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $bytes = file_put_contents($path, $json);
        if ($bytes === false) {
            $this->error("Failed to write to file: {$path}");
            return;
        }

        $this->info("✅ Export complete ({$bytes} bytes) → {$path}");
    }

    private function parseTeachingAreas(string $raw): array
    {
        $clean = [];
        $review = [];

        if (empty(trim($raw))) {
            return ['clean' => $clean, 'review' => $review];
        }

        // Ensure raw string is UTF-8
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

        // Logic 1: Detect tags content
        $tagsToEvaluate = ['p', 'li', 'div'];
        foreach ($tagsToEvaluate as $tag) {
            $pattern = "/<{$tag}[^>]*>(.*?)<\/{$tag}>/is";
            if (preg_match_all($pattern, $raw, $matches)) {
                foreach ($matches[1] as $content) {
                    // Normalize content: replace <br> with skip/space to prevent word merging
                    $normalizedContent = str_ireplace(['<br>', '<br/>', '<br />'], ' | ', $content);
                    $item = strip_tags($normalizedContent);
                    $item = $this->cleanSubjectText($item);

                    if (empty($item)) continue;

                    // Strong Junk (Has Year/Date)
                    if ($this->hasYearOrDate($item)) {
                        $review[] = [
                            'type' => 'junk_date_found',
                            'item' => $item,
                        ];
                        continue;
                    }

                    // Keyword Check (Role/University) but ONLY if not a list
                    // If it has pipe separators (from <br>) or many commas, it's likely a list
                    $isList = strpos($item, '|') !== false || substr_count($item, ',') + substr_count($item, ';') > 1;
                    
                    if (!$isList && $this->isProfileRoleOrInstitution($item)) {
                        $review[] = [
                            'type' => 'junk_profile_info',
                            'item' => $item,
                        ];
                        continue;
                    }

                    // If it has line breaks (<br>) but is very long without any recognized list markers or delimiters
                    if (stripos($content, '<br') !== false) {
                        $delims = substr_count($item, '|') + substr_count($item, ',') + substr_count($item, ';');
                        if ($delims < 2 && strlen($item) > 150) {
                            $review[] = [
                                'type' => 'p_tag_with_br_long_text',
                                'item' => $item,
                            ];
                        }
                    }
                }
            }
        }

        // Final Extraction logic for "Clean" Data
        $tempCleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>', '</ul>', '</ol>'], "\n", $raw);
        $tempCleaned = strip_tags($tempCleaned);
        $tempCleaned = html_entity_decode($tempCleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $lines = explode("\n", $tempCleaned);
        foreach ($lines as $line) {
            $line = $this->cleanSubjectText($line);
            if (empty($line) || strlen($line) < 3) continue;

            if ($this->hasYearOrDate($line)) continue;

            if (strlen($line) > 250 && strpos($line, ',') === false) continue;
            
            if (preg_match_all('/[,;]/', $line) > 1 && str_word_count($line) < 50) {
                $subParts = preg_split('/[,;]/', $line);
                foreach ($subParts as $part) {
                    $part = $this->cleanSubjectText($part);
                    
                    if (strlen($part) > 2 && strlen($part) < 250 && !$this->hasYearOrDate($part)) {
                        $clean[] = [
                            'area'        => mb_convert_encoding($part, 'UTF-8', 'UTF-8'),
                            'description' => null,
                        ];
                    }
                }
            } else {
                // Check again for role/inst if it's a short line without commas
                if (substr_count($line, ',') < 1 && $this->isProfileRoleOrInstitution($line)) {
                    continue;
                }

                if (strlen($line) > 2 && strlen($line) < 250) {
                    $clean[] = [
                        'area'        => mb_convert_encoding($line, 'UTF-8', 'UTF-8'),
                        'description' => null,
                    ];
                }
            }
        }

        return [
            'clean'  => $clean,
            'review' => $review,
        ];
    }

    private function cleanSubjectText(string $text): string
    {
        // 1. Decode entities and normalize whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        
        // 2. Remove leading bullets/numbers: "1. ", "1) ", "1 ", "o ", "- ", "* ", "(a) ", etc.
        // This strips: 
        // - (a) or a)
        // - 1. or 1)
        // - 1 (followed by space)
        // - Bullets: o, -, •, *, etc.
        $text = preg_replace('/^(\(?[0-9a-z][\.)]|\(?[0-9]+\)|[0-9]+\.|\b[0-9]+\b|o|[-•*·?#])\s*/i', '', trim($text));
        
        // 3. Normalize internal whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    private function hasYearOrDate(string $text): bool
    {
        // Detect Years (e.g. 1999, 2024, '07, '99)
        if (preg_match('/\b(19|20)\d{2}\b/', $text)) return true; 
        if (preg_match('/\b\d{2}[-–]\d{2}\b/', $text)) return true;
        if (preg_match('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* [0-9]{2,4}\b/i', $text)) return true;
        if (preg_match('/\b[0-9]{2,4} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\b/i', $text)) return true;
        
        return false;
    }

    private function isProfileRoleOrInstitution(string $text): bool
    {
        $junkKeywords = [
            'Professor', 'Assistant Professor', 'Associate Professor', 'Dean', 'Director', 'Head', 
            'Coordinator', 'Lecturer', 'Registrar',
            'University', 'Green University', 'CUET', 'BIT Rajshahi', 'Syndicate', 'Expert Member'
        ];

        foreach ($junkKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
