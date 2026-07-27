<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Teacher;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationIncentive;

class MapPublicationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:map 
                            {--limit= : Limit the number of new publications to process (useful for testing)}
                            {--csv=public/documents/old publication/All_Publications (25072026).csv : Path to the source CSV file relative to project root} 
                            {--output=public/documents/old publication/publications_all.json : Path to the output JSON file relative to project root}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Map old publication CSV data to FMS database and generate a clean JSON export with HTML stripped (except <br>).';

    /**
     * Helper to clean HTML tags except <br> and decode entities
     */
    private function cleanHtml($str)
    {
        if (empty($str)) return '';
        // Strip tags allowing <br>
        $cleaned = strip_tags($str, '<br>');
        // Decode HTML entities
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($cleaned);
    }

    /**
     * Helper to clean and normalize names for robust matching
     */
    private function cleanName($name)
    {
        if (empty($name)) return '';
        $name = str_replace(['.', ',', '-'], ' ', $name);
        $name = strtolower($name);
        $titles = ['dr', 'prof', 'mr', 'mrs', 'ms', 'md', 'shaikh', 'sheikh', 'engr', 'assoc', 'assistant', 'professor', 'lecturer', 'mohammad', 'muhammad', 'mst', 'most'];
        $parts = array_filter(explode(' ', $name), function($part) {
            return trim($part) !== '';
        });
        $filtered = array_filter($parts, function($part) use ($titles) {
            return !in_array(strtolower(trim($part)), $titles);
        });
        return implode(' ', $filtered);
    }

    /**
     * Helper to lookup teacher by name, resolving duplicates via department_id
     */
    private function findTeacherMatch($author_name, $teachers, $pub_dept_id)
    {
        $clean_author = $this->cleanName($author_name);
        if (empty($clean_author)) return null;

        $matches = [];
        foreach ($teachers as $t) {
            if ($t->clean_db_name === $clean_author || $t->clean_user_name === $clean_author) {
                $matches[] = $t;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            foreach ($matches as $t) {
                if ($t->department_id == $pub_dept_id) {
                    return $t;
                }
            }
            return $matches[0];
        }

        // Soft/Partial matching fallback
        foreach ($teachers as $t) {
            if (!empty($t->clean_db_name)) {
                $author_parts = explode(' ', $clean_author);
                $t_parts = explode(' ', $t->clean_db_name);
                $intersect = array_intersect($author_parts, $t_parts);
                if (count($intersect) >= 2) {
                    $matches[] = $t;
                }
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            foreach ($matches as $t) {
                if ($t->department_id == $pub_dept_id) {
                    return $t;
                }
            }
            return $matches[0];
        }

        return null;
    }

    /**
     * Get award slab amounts based on Q-Index and CiteScore
     */
    private function getAwardSlab($q_index, $citescore)
    {
        $q = strtoupper(trim($q_index));
        $cs = (float) $citescore;

        if ($q === 'Q1' && $cs > 10) {
            return ['1st' => 50000, 'corr' => 35000, 'co' => 25000];
        } elseif ($q === 'Q1') {
            return ['1st' => 30000, 'corr' => 25000, 'co' => 20000];
        } elseif ($q === 'Q2') {
            return ['1st' => 25000, 'corr' => 20000, 'co' => 15000];
        } elseif ($q === 'Q3') {
            return ['1st' => 20000, 'corr' => 15000, 'co' => 10000];
        } else { // Q4 and others
            return ['1st' => 15000, 'corr' => 10000, 'co' => 10000];
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = base_path($this->option('csv'));
        $outputPath = base_path($this->option('output'));

        if (!file_exists($csvPath)) {
            $this->error("Error: CSV file not found at: $csvPath");
            return 1;
        }

        $this->info("Preloading database caches...");
        // Cache faculties and departments
        $faculties_cache = Faculty::all()->keyBy('short_name');
        $departments_cache = Department::all();

        $faculty_map = [];
        foreach ($faculties_cache as $short => $f) {
            $faculty_map[strtolower($short)] = $f->id;
        }

        $dept_map = [];
        foreach ($departments_cache as $d) {
            $fac_short = strtolower($d->faculty->short_name ?? '');
            $d_short = strtolower($d->short_name);
            $dept_map[$fac_short][$d_short] = $d->id;
            $dept_map['direct'][$d_short] = $d->id;
        }

        // Cache and clean teacher names
        $teachers_db = Teacher::with('user', 'department')->get();
        foreach ($teachers_db as $t) {
            $t->clean_db_name = $this->cleanName(trim($t->first_name . ' ' . $t->middle_name . ' ' . $t->last_name));
            $t->clean_user_name = $t->user ? $this->cleanName($t->user->name) : '';
        }

        // Cache all publications in database to avoid individual queries
        $this->info("Preloading database publications...");
        $publications_db = Publication::select('id', 'title', 'faculty_id', 'department_id')->get();
        $pub_title_map = [];
        foreach ($publications_db as $p) {
            $pub_title_map[strtolower(trim($p->title))] = $p;
        }

        // Cache all incentives
        $this->info("Preloading database incentives...");
        $incentives_db = PublicationIncentive::select('publication_id', 'total_amount', 'status')->get()->keyBy('publication_id');

        // Open CSV file
        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file);

        // Find header indices
        $col_map = [];
        foreach ($headers as $idx => $header) {
            $col_map[$header] = $idx;
        }

        $this->info("Grouping publication CSV rows...");
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
                    'title' => trim($row[$col_map['Title']] ?? ''),
                    'first_author' => $first_author,
                    'corresponding_author' => trim($row[$col_map['Corresponding Author']] ?? ''),
                    'co_authors' => $co_authors_list,
                    'department_short' => trim($row[$col_map['Department']] ?? ''),
                    'faculty_short' => trim($row[$col_map['Faculty']] ?? ''),
                    'email' => trim($row[$col_map['Email']] ?? ''),
                    'publication_year' => trim($row[$col_map['Publication Year']] ?? ''),
                    'abstract' => trim($row[$col_map['Abstract']] ?? ''),
                    'award_money_csv' => trim($row[$col_map['Award Money']] ?? ''),
                    'citescore' => trim($row[$col_map['CiteScore']] ?? ''),
                    'link' => trim($row[$col_map['Conference / Journal Link']] ?? ''),
                    'journal_name' => trim($row[$col_map['Conference / Journal Name']] ?? ''),
                    'q_index' => trim($row[$col_map['Q-Index']] ?? ''),
                    'researcher_designation' => trim($row[$col_map['Researcher Name with Designation']] ?? ''),
                    'raw_row' => $row
                ];
            } else {
                $co_author_sub = trim($row[$col_map['Co-Authors']] ?? '');
                if ($co_author_sub !== '' && $last_primary_idx !== -1) {
                    $grouped_rows[$last_primary_idx]['co_authors'][] = $co_author_sub;
                }
            }
        }
        fclose($file);

        $this->info("Checking for existing output JSON file...");
        $existingPubs = [];
        if (file_exists($outputPath)) {
            $existingContent = file_get_contents($outputPath);
            $decoded = json_decode($existingContent, true);
            if (is_array($decoded)) {
                foreach ($decoded as $pub) {
                    if (isset($pub['csv_id'])) {
                        $existingPubs[$pub['csv_id']] = $pub;
                    }
                }
            }
        }

        $this->info("Processing grouped publications...");
        $all_publications = [];
        $matched_titles_count = 0;
        $matched_teachers_count = 0;
        $skipped_count = 0;
        $new_processed_count = 0;

        $limit = $this->option('limit');
        if ($limit !== null && is_numeric($limit)) {
            $limit = (int) $limit;
            $this->info("Execution limit set: processing max $limit new publications.");
        }

        foreach ($grouped_rows as $pub) {
            $csv_id = $pub['id'];
            
            // If already processed in a previous execution, preserve as-is and skip reprocessing
            if (isset($existingPubs[$csv_id])) {
                $all_publications[] = $existingPubs[$csv_id];
                $skipped_count++;
                
                // Re-tally teacher matches for log output accuracy
                if (isset($existingPubs[$csv_id]['authors'])) {
                    foreach ($existingPubs[$csv_id]['authors'] as $auth) {
                        if (!empty($auth['teacher_id'])) {
                            $matched_teachers_count++;
                        }
                    }
                }
                if (!empty($existingPubs[$csv_id]['db_publication_id'])) {
                    $matched_titles_count++;
                }
                continue;
            }

            // Check if we hit the new process limit
            if ($limit !== null && $new_processed_count >= $limit) {
                continue;
            }

            $fac_short_lower = strtolower($pub['faculty_short']);
            $dept_short_lower = strtolower($pub['department_short']);
            
            if ($dept_short_lower === 'ba') {
                $dept_short_lower = 'bba';
            }
            
            $faculty_id = $faculty_map[$fac_short_lower] ?? null;
            $department_id = $dept_map[$fac_short_lower][$dept_short_lower] ?? ($dept_map['direct'][$dept_short_lower] ?? null);

            $title_lower = strtolower(trim($pub['title']));
            $db_pub = $pub_title_map[$title_lower] ?? null;
            $db_pub_id = $db_pub ? $db_pub->id : null;
            if ($db_pub) {
                $matched_titles_count++;
                if ($db_pub->faculty_id) $faculty_id = $db_pub->faculty_id;
                if ($db_pub->department_id) $department_id = $db_pub->department_id;
            }

            $authors = [];
            if (!empty($pub['first_author'])) {
                $authors[] = [
                    'name' => $pub['first_author'],
                    'role' => 'First Author'
                ];
            }
            if (!empty($pub['corresponding_author']) && $this->cleanName($pub['corresponding_author']) !== $this->cleanName($pub['first_author'])) {
                $authors[] = [
                    'name' => $pub['corresponding_author'],
                    'role' => 'Corresponding Author'
                ];
            }
            foreach ($pub['co_authors'] as $co_author) {
                $clean_co = $this->cleanName($co_author);
                if ($clean_co !== $this->cleanName($pub['first_author']) && $clean_co !== $this->cleanName($pub['corresponding_author'])) {
                    $authors[] = [
                        'name' => $co_author,
                        'role' => 'Co-author'
                    ];
                }
            }

            $mapped_authors = [];
            $has_vf = str_contains(strtolower($pub['researcher_designation']), 'vf');
            
            foreach ($authors as $auth) {
                $matched_t = $this->findTeacherMatch($auth['name'], $teachers_db, $department_id);
                
                $is_vf = false;
                $employee_id = null;
                $email = null;
                $teacher_id = null;

                if ($matched_t) {
                    $matched_teachers_count++;
                    $teacher_id = $matched_t->id;
                    $employee_id = $matched_t->employee_id;
                    $email = $matched_t->user ? $matched_t->user->email : $matched_t->secondary_email;
                    
                    if ($matched_t->job_type_id == 5) {
                        $is_vf = true;
                    }
                }
                
                if ($has_vf && ($auth['role'] === 'First Author' || count($authors) === 1)) {
                    $is_vf = true;
                }

                $mapped_authors[] = [
                    'name' => $auth['name'],
                    'role' => $auth['role'],
                    'teacher_id' => $teacher_id,
                    'employee_id' => $employee_id,
                    'email' => $email,
                    'is_diu_faculty' => !empty($employee_id),
                    'is_visiting_faculty' => $is_vf,
                    'incentive_amount' => 0.00
                ];
            }

            $slab = $this->getAwardSlab($pub['q_index'], $pub['citescore']);
            $csv_award_val = preg_replace('/[^0-9]/', '', $pub['award_money_csv']);
            $pool_amount = (is_numeric($csv_award_val) && (int)$csv_award_val > 0) ? (int)$csv_award_val : $slab['1st'];

            $num_eligible = count($mapped_authors);
            $vf_indices = [];
            foreach ($mapped_authors as $idx => $ma) {
                if ($ma['is_visiting_faculty']) {
                    $vf_indices[] = $idx;
                }
            }

            if ($num_eligible > 0) {
                if (count($vf_indices) > 0) {
                    if ($num_eligible === 1) {
                        $vf_idx = $vf_indices[0];
                        $mapped_authors[$vf_idx]['incentive_amount'] = (float) $pool_amount;
                    } else {
                        $vf_idx = $vf_indices[0];
                        $vf_role = $mapped_authors[$vf_idx]['role'];
                        
                        $vf_slab_amt = $slab['co'];
                        if ($vf_role === 'First Author') {
                            $vf_slab_amt = $slab['1st'];
                        } elseif ($vf_role === 'Corresponding Author') {
                            $vf_slab_amt = $slab['corr'];
                        }

                        $vf_award = max($vf_slab_amt, $pool_amount * 0.5);
                        if ($vf_award > $pool_amount) {
                            $vf_award = $pool_amount;
                        }
                        
                        $mapped_authors[$vf_idx]['incentive_amount'] = (float) $vf_award;

                        $remaining = $pool_amount - $vf_award;
                        $other_indices = array_diff(array_keys($mapped_authors), $vf_indices);
                        if (count($other_indices) > 0 && $remaining > 0) {
                            $share = $remaining / count($other_indices);
                            foreach ($other_indices as $o_idx) {
                                $mapped_authors[$o_idx]['incentive_amount'] = (float) round($share, 2);
                            }
                        }
                    }
                } else {
                    $has_1st = false;
                    $first_idx = null;
                    $has_corr = false;
                    $corr_idx = null;
                    $co_indices = [];

                    foreach ($mapped_authors as $idx => $ma) {
                        $role = $ma['role'];
                        if ($role === 'First Author') {
                            $has_1st = true;
                            $first_idx = $idx;
                        } elseif ($role === 'Corresponding Author') {
                            $has_corr = true;
                            $corr_idx = $idx;
                        } else {
                            $co_indices[] = $idx;
                        }
                    }

                    if ($has_1st) {
                        $pool = $slab['1st'];
                        $first_share = $pool * 0.5;
                        $mapped_authors[$first_idx]['incentive_amount'] = (float) $first_share;

                        $others = [];
                        if ($has_corr) $others[] = $corr_idx;
                        foreach ($co_indices as $co_i) $others[] = $co_i;

                        if (count($others) > 0) {
                            $other_share = ($pool * 0.5) / count($others);
                            foreach ($others as $oth_i) {
                                $mapped_authors[$oth_i]['incentive_amount'] = (float) round($other_share, 2);
                            }
                        }
                    } elseif ($has_corr) {
                        $pool = $slab['corr'];
                        $corr_share = $pool * 0.5;
                        $mapped_authors[$corr_idx]['incentive_amount'] = (float) $corr_share;

                        if (count($co_indices) > 0) {
                            $co_share = ($pool * 0.5) / count($co_indices);
                            foreach ($co_indices as $co_i) {
                                $mapped_authors[$co_i]['incentive_amount'] = (float) round($co_share, 2);
                            }
                        }
                    } else {
                        $pool = $slab['co'];
                        if (count($co_indices) > 0) {
                            $co_share = $pool / count($co_indices);
                            foreach ($co_indices as $co_i) {
                                $mapped_authors[$co_i]['incentive_amount'] = (float) round($co_share, 2);
                            }
                        }
                    }
                }
            }

            $db_incentive_amount = 0.00;
            $db_incentive_status = 'pending';
            $db_created_by = 5;
            $db_created_at = now()->toDateTimeString();
            $db_updated_at = now()->toDateTimeString();

            if ($db_pub_id && isset($incentives_db[$db_pub_id])) {
                $db_incentive = $incentives_db[$db_pub_id];
                $db_incentive_amount = (float) $db_incentive->total_amount;
                $db_incentive_status = $db_incentive->status;
                if (!empty($db_incentive->created_by)) {
                    $db_created_by = (int) $db_incentive->created_by;
                }
                if (!empty($db_incentive->created_at)) {
                    $db_created_at = is_string($db_incentive->created_at) ? $db_incentive->created_at : $db_incentive->created_at->toDateTimeString();
                }
                if (!empty($db_incentive->updated_at)) {
                    $db_updated_at = is_string($db_incentive->updated_at) ? $db_incentive->updated_at : $db_incentive->updated_at->toDateTimeString();
                }
            }

            $cleaned_abstract = $this->cleanHtml($pub['abstract']);
            $cleaned_title = $this->cleanHtml($pub['title']);

            $all_publications[] = [
                'csv_id' => $pub['id'],
                'db_publication_id' => $db_pub_id,
                'title' => $cleaned_title,
                'journal_name' => $this->cleanHtml($pub['journal_name']),
                'journal_link' => $pub['link'],
                'q_index' => $pub['q_index'],
                'citescore' => $pub['citescore'],
                'publication_year' => $pub['publication_year'],
                'faculty_id' => $faculty_id,
                'department_id' => $department_id,
                'faculty_short' => $pub['faculty_short'],
                'department_short' => $pub['department_short'],
                'abstract' => $cleaned_abstract,
                'award_money_csv' => $pub['award_money_csv'],
                'calculated_total_incentive' => (float) $pool_amount,
                'db_incentive' => [
                    'total_amount' => $db_incentive_amount,
                    'status' => $db_incentive_status,
                    'created_by' => $db_created_by,
                    'created_at' => $db_created_at,
                    'updated_at' => $db_updated_at
                ],
                'authors' => $mapped_authors
            ];

            $new_processed_count++;
        }

        $this->info("Writing JSON output to: $outputPath");
        
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, json_encode($all_publications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $this->info("\n--- Mapping Completed Successfully ---");
        $this->line("Total Grouped Rows in CSV: " . count($grouped_rows));
        $this->line("Skipped (Already Processed): $skipped_count");
        $this->line("Newly Processed: $new_processed_count");
        $this->line("Title Matches in Database: $matched_titles_count");
        $this->line("Total Authors Matched to Teachers: $matched_teachers_count");
        $this->line("Output JSON: $outputPath");

        return 0;
    }
}
