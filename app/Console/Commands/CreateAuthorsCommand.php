<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\Author;
use App\Models\Teacher;
use App\Models\Faculty;
use App\Models\Department;

class CreateAuthorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:create-authors
                            {--limit= : Limit the number of publications to process (useful for testing)}
                            {--csv-json=public/documents/old publication/publications_all.json : Path to the source JSON file relative to project root}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register unmatched authors in the database and populate morph link fields (authorable_id, authorable_type) in a final export JSON.';

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
     * Execute the console command.
     */
    public function handle()
    {
        $jsonRelativePath = $this->option('csv-json');
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

        $this->info("Preloading database caches for teacher matching...");
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

        $this->info("Preloading and caching existing database authors...");
        $existingAuthors = Author::all();
        $authorCache = [];
        foreach ($existingAuthors as $author) {
            $authorCache[$this->cleanName($author->name)] = $author->id;
        }

        $limit = $this->option('limit');
        $totalToProcess = count($publications);
        if ($limit && is_numeric($limit)) {
            $totalToProcess = min((int)$limit, count($publications));
            $this->info("Limiting execution to first $totalToProcess publications.");
        }

        $newAuthorsCount = 0;
        $linkedTeachersCount = 0;
        $linkedAuthorsCount = 0;

        $this->info("Processing publications and mapping authors...");

        for ($i = 0; $i < $totalToProcess; $i++) {
            $pub = $publications[$i];

            // Resolve department_id for teacher matching
            $fac_short_lower = strtolower($pub['faculty_short'] ?? '');
            $dept_short_lower = strtolower($pub['department_short'] ?? '');
            $department_id = $dept_map[$fac_short_lower][$dept_short_lower] ?? ($dept_map['direct'][$dept_short_lower] ?? null);
            if (!empty($pub['department_id'])) {
                $department_id = $pub['department_id'];
            }

            if (isset($pub['authors']) && is_array($pub['authors'])) {
                $newAuthorsArray = [];

                foreach ($pub['authors'] as $idx => $author) {
                    $authorName = trim($author['name']);

                    // 1st: Check teacher matching by name and department/faculty
                    $matched_t = $this->findTeacherMatch($authorName, $teachers_db, $department_id);

                    if ($matched_t) {
                        // Matched to a teacher
                        $authorable_type = 'App\\Models\\Teacher';
                        $authorable_id = $matched_t->id;
                        $email = $matched_t->user ? $matched_t->user->email : $matched_t->secondary_email;
                        $employee_id = $matched_t->employee_id;
                        $teacher_id = $matched_t->id;
                        $is_diu_faculty = true;
                        $linkedTeachersCount++;
                    } else {
                        // Unmatched - must check/create record in authors table
                        $cleanAuthorName = $this->cleanName($authorName);
                        $email = trim($author['email'] ?? '');

                        if (empty($email)) {
                            $cleanEmailPart = str_replace(' ', '.', $cleanAuthorName);
                            $email = $cleanEmailPart . '@fms.com';
                        }

                        if (isset($authorCache[$cleanAuthorName])) {
                            // Author already exists in database cache
                            $authorId = $authorCache[$cleanAuthorName];
                            $linkedAuthorsCount++;
                        } else {
                            // Double-check database by email to prevent duplicate constraints
                            $existingByEmail = Author::where('email', $email)->first();
                            if ($existingByEmail) {
                                $authorId = $existingByEmail->id;
                            } else {
                                // Create new author in database
                                $newAuthor = Author::create([
                                    'name' => $authorName,
                                    'email' => $email,
                                    'author_type_id' => ($author['is_visiting_faculty'] ?? false) ? 1 : 2, // 1 = VF, 2 = Guest Author
                                    'is_active' => 1
                                ]);
                                $authorId = $newAuthor->id;
                                $newAuthorsCount++;
                            }

                            // Add to cache
                            $authorCache[$cleanAuthorName] = $authorId;
                            $linkedAuthorsCount++;
                        }

                        $authorable_type = 'App\\Models\\Author';
                        $authorable_id = $authorId;
                        $employee_id = null;
                        $teacher_id = null;
                        $is_diu_faculty = false;
                    }

                    // Format as requested: author_role, incentive_amount, authorable_type, authorable_id, sort_order
                    $newAuthorsArray[] = [
                        'author_role' => $author['role'] ?? $author['author_role'] ?? 'Co-author',
                        'incentive_amount' => (float)($author['incentive_amount'] ?? 0.00),
                        'authorable_type' => $authorable_type,
                        'authorable_id' => $authorable_id,
                        'sort_order' => $idx + 1,
                    ];
                }

                $pub['authors'] = $newAuthorsArray;
            }

            // Save the updated publication back to the array
            $publications[$i] = $pub;
        }

        // Setup output directory and file path
        $exportDirectory = storage_path('app/public/export');
        $exportJsonPath = $exportDirectory . '/publications_all.json';

        $this->info("Ensuring export directory exists at: $exportDirectory");
        File::ensureDirectoryExists($exportDirectory);

        $this->info("Writing complete mapped publications JSON to: $exportJsonPath");
        file_put_contents($exportJsonPath, json_encode($publications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("\n--- Authors Mapping Completed Successfully ---");
        $this->line("Publications Processed: $totalToProcess / " . count($publications));
        $this->line("New Authors Created in Database: $newAuthorsCount");
        $this->line("Authors Linked to Teachers Table: $linkedTeachersCount");
        $this->line("Authors Linked to Authors Table: $linkedAuthorsCount");
        $this->line("Output File: $exportJsonPath");

        return 0;
    }
}
