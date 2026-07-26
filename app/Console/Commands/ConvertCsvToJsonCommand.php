<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConvertCsvToJsonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:csv-to-json 
                            {--limit= : Limit the number of publications to convert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert publications CSV in storage/app/public/document to JSON with grouping and ditto resolution.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = storage_path('app/public/document/All_Publications (25072026).csv');
        $outputPath = storage_path('app/public/document/publications_all.json');

        if (!file_exists($csvPath)) {
            $this->error("Error: CSV file not found at: $csvPath");
            return 1;
        }

        $this->info("Reading and parsing CSV file...");
        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file);

        $col_map = [];
        foreach ($headers as $idx => $header) {
            $col_map[$header] = $idx;
        }

        $grouped_rows = [];
        $last_primary_idx = -1;
        $last_first_author = '';

        while (($row = fgetcsv($file)) !== false) {
            $id_val = trim($row[$col_map['ID']] ?? '');
            
            if ($id_val !== '') {
                $last_primary_idx = count($grouped_rows);
                
                $first_author = trim($row[$col_map['First Author']] ?? '');
                if ($first_author === "'=" || $first_author === "0" || $first_author === "=") {
                    $first_author = $last_first_author;
                } else {
                    if ($first_author !== '') {
                        $last_first_author = $first_author;
                    }
                }
                
                $co_authors_list = [];
                $co_author_init = trim($row[$col_map['Co-Authors']] ?? '');
                if ($co_author_init !== '') {
                    $co_authors_list[] = $co_author_init;
                }

                $grouped_rows[] = [
                    'id' => (int) $id_val,
                    'title' => $this->cleanHtml($row[$col_map['Title']] ?? '', false),
                    'display_name' => $this->cleanHtml($row[$col_map['Display Name']] ?? '', false),
                    'first_author' => $first_author,
                    'corresponding_author' => trim($row[$col_map['Corresponding Author']] ?? ''),
                    'co_authors' => $co_authors_list,
                    'department' => trim($row[$col_map['Department']] ?? ''),
                    'faculty' => trim($row[$col_map['Faculty']] ?? ''),
                    'email' => trim($row[$col_map['Email']] ?? ''),
                    'phone' => trim($row[$col_map['Phone']] ?? ''),
                    'publication_year' => trim($row[$col_map['Publication Year']] ?? ''),
                    'publication_date' => trim($row[$col_map['Publication Date']] ?? ''),
                    'abstract' => $this->cleanHtml($row[$col_map['Abstract']] ?? '', true),
                    'award_money' => trim($row[$col_map['Award Money']] ?? ''),
                    'citescore' => trim($row[$col_map['CiteScore']] ?? ''),
                    'journal_link' => trim($row[$col_map['Conference / Journal Link']] ?? ''),
                    'journal_name' => trim($row[$col_map['Conference / Journal Name']] ?? ''),
                    'q_index' => trim($row[$col_map['Q-Index']] ?? ''),
                    'researcher_designation' => trim($row[$col_map['Researcher Name with Designation']] ?? ''),
                    'created_by' => trim($row[$col_map['Created by']] ?? ''),
                    'created_on' => trim($row[$col_map['Created on']] ?? ''),
                    'external_id' => trim($row[$col_map['External ID']] ?? ''),
                    'funding' => trim($row[$col_map['Funding']] ?? ''),
                    'h_index' => trim($row[$col_map['H-Index']] ?? ''),
                    'impact_and_practical_application' => $this->cleanHtml($row[$col_map['Impact and Practical Application']] ?? '', true),
                    'impact_factor' => trim($row[$col_map['Impact Factor']] ?? ''),
                    'indexed' => trim($row[$col_map['Indexed']] ?? ''),
                    'keywords' => trim($row[$col_map['Keywords']] ?? ''),
                    'last_modified_on' => trim($row[$col_map['Last Modified on']] ?? ''),
                    'last_updated_by' => trim($row[$col_map['Last Updated by']] ?? ''),
                    'last_updated_on' => trim($row[$col_map['Last Updated on']] ?? ''),
                    'no_of_research_published_paper' => trim($row[$col_map['No of Research Published Paper']] ?? ''),
                    'remarks' => $this->cleanHtml($row[$col_map['Remarks']] ?? '', true),
                    'research_area' => $this->cleanHtml($row[$col_map['Research Area']] ?? '', true),
                    'student_involvement' => trim($row[$col_map['Student Involvement']] ?? '')
                ];
            } else {
                $co_author_sub = trim($row[$col_map['Co-Authors']] ?? '');
                if ($co_author_sub !== '' && $last_primary_idx !== -1) {
                    $grouped_rows[$last_primary_idx]['co_authors'][] = $co_author_sub;
                }
            }
        }
        fclose($file);

        $limit = $this->option('limit');
        $totalToProcess = count($grouped_rows);
        if ($limit !== null && is_numeric($limit)) {
            $totalToProcess = min((int) $limit, count($grouped_rows));
            $this->info("Execution limit set: converting max $totalToProcess publications.");
        }

        $output_pubs = [];
        for ($i = 0; $i < $totalToProcess; $i++) {
            $output_pubs[] = $grouped_rows[$i];
        }

        $this->info("Writing JSON output to: $outputPath");
        File::ensureDirectoryExists(dirname($outputPath));
        file_put_contents($outputPath, json_encode($output_pubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("\n--- Conversion Completed Successfully ---");
        $this->line("Total Grouped Publications: " . count($grouped_rows));
        $this->line("Converted (Limit applied): $totalToProcess");
        $this->line("Output JSON: $outputPath");

        return 0;
    }

    /**
     * Helper to clean HTML tags from text fields
     */
    private function cleanHtml($text, $allowBr = true)
    {
        if (empty($text)) return '';
        $allowed = $allowBr ? '<br>' : '';
        $cleaned = strip_tags($text, $allowed);
        if ($allowBr) {
            $cleaned = preg_replace('/<br\s*\/?>/i', '<br>', $cleaned);
        }
        return trim($cleaned);
    }
}
