<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\Author;

class ConvertPublicationCsvToJsonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:convert-pipeline {--limit= : Limit the number of publications to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process All_Publications.csv through a 3-step pipeline to output mapped JSON files';

    /**
     * Variable to control whether incentives should be auto-distributed.
     * Set to true to calculate shares, or false to leave all at 0.
     */
    private bool $autoCalculateIncentives = false;

    /**
     * Variable to control whether AI should be used for name matching fallback.
     * Set to true to call Gemini for low-confidence matches, or false to skip.
     */
    private bool $useAiForNameMatching = false;

    /**
     * Header mapping from CSV to JSON snake_case fields
     */
    private const HEADER_MAP = [
        'Title' => 'title',
        'First Author' => 'first_author',
        'Co-Authors' => 'co_authors',
        'Department' => 'department_short',
        'Email' => 'email',
        'Publication Year' => 'publication_year',
        'Abstract' => 'abstract',
        'Award Money' => 'award_money_csv',
        'CiteScore' => 'citescore',
        'Conference / Journal Link' => 'journal_link',
        'Conference / Journal Name' => 'journal_name',
        'Corresponding Author' => 'corresponding_author',
        'Created by' => 'created_by',
        'Created on' => 'created_on',
        'Display Name' => 'display_name',
        'External ID' => 'external_id',
        'Faculty' => 'faculty_short',
        'Funding' => 'funding',
        'H-Index' => 'h_index',
        'ID' => 'id',
        'Impact and Practical Application' => 'impact_and_practical_application',
        'Impact Factor' => 'impact_factor',
        'Indexed' => 'indexed',
        'Keywords' => 'keywords',
        'Last Modified on' => 'last_modified_on',
        'Last Updated by' => 'last_updated_by',
        'Last Updated on' => 'last_updated_on',
        'No of Research Published Paper' => 'no_of_research_published_paper',
        'Phone' => 'phone',
        'Publication Date' => 'publication_date',
        'Q-Index' => 'q_index',
        'Remarks' => 'remarks',
        'Research Area' => 'research_area',
        'Researcher Name with Designation' => 'researcher_name_with_designation',
        'Student Involvement' => 'student_involvement'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = storage_path('app/public/document/All_Publications.csv');

        // Fallback for documnt typo
        if (!file_exists($csvPath)) {
            $csvPath = storage_path('app/public/documnt/All_Publications.csv');
        }

        if (!file_exists($csvPath)) {
            $this->error("Error: All_Publications.csv not found at {$csvPath}");
            return 1;
        }

        $baseDir = dirname($csvPath);
        $firstStepPath = $baseDir . '/first_step.json';
        $secondStepPath = $baseDir . '/second_step.json';
        $thirdStepPath = $baseDir . '/third_step.json';

        $limit = $this->option('limit');
        $limit = ($limit !== null && is_numeric($limit)) ? (int)$limit : null;

        // --- STEP 1: CSV to JSON Conversion ---
        $this->info("Step 1: Converting CSV to first_step.json with HTML stripping and incentive distribution...");
        $pubs = $this->step1ConvertCsv($csvPath, $limit);
        File::put($firstStepPath, json_encode($pubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Step 1 complete! Saved " . count($pubs) . " publications to first_step.json");

        // --- STEP 2: Faculty & Department ID Mapping ---
        $this->info("Step 2: Mapping faculties and departments to second_step.json...");
        $mappedPubs = $this->step2MapFacultiesAndDepartments($firstStepPath);
        File::put($secondStepPath, json_encode($mappedPubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Step 2 complete! Saved second_step.json");

        // --- STEP 3: Fuzzy Teacher Name Matching ---
        $this->info("Step 3: Matching teachers and mapping teacher_ids to third_step.json...");
        $finalPubs = $this->step3MatchTeachers($secondStepPath);
        File::put($thirdStepPath, json_encode($finalPubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Step 3 complete! Saved third_step.json");

        // --- STEP 4: Matching Old Site Publications ---
        /*
        $this->info("Step 4: Matching with Old Site publications from teachers_publications_export.json...");
        $oldExportPath = $baseDir . '/teachers_publications_export.json';
        if (!file_exists($oldExportPath)) {
            $this->error("Old site publications file not found at {$oldExportPath}!");
            return 1;
        }
        $this->step4MatchOldSite($thirdStepPath, $oldExportPath, $baseDir);
        */

        $this->info("\nAll steps completed successfully!");
        return 0;
    }

    /**
     * Step 1: Read CSV, clean text fields, group co-authors, and calculate incentives
     */
    private function step1ConvertCsv(string $csvPath, ?int $limit = null): array
    {
        $pubs = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            $headers = fgetcsv($handle);

            // Clean headers (remove BOM or weird spacing)
            foreach ($headers as &$header) {
                $header = trim($header, "\xEF\xBB\xBF ");
            }

            $currentPub = null;
            $count = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($headers)) {
                    continue;
                }

                $data = array_combine($headers, $row);
                $title = trim($data['Title'] ?? '');

                if ($title !== '') {
                    // Save previous publication if exists
                    if ($currentPub !== null) {
                        $pubs[] = $this->finalizePublicationAuthorsAndIncentives($currentPub);
                        $count++;
                        if ($limit !== null && $count >= $limit) {
                            $currentPub = null;
                            break;
                        }
                    }

                    // Start new publication and map to snake_case keys
                    $currentPub = [];
                    foreach ($data as $key => $val) {
                        $mappedKey = self::HEADER_MAP[$key] ?? strtolower(str_replace(' ', '_', $key));
                        $shouldStripHtml = in_array($key, ['Abstract', 'Title', 'Conference / Journal Name', 'Remarks'], true);
                        $currentPub[$mappedKey] = $this->cleanField($val, $shouldStripHtml);
                    }

                    $coAuthor = trim($data['Co-Authors'] ?? '');
                    $currentPub['co_authors'] = ($coAuthor !== '') ? [$this->cleanField($coAuthor, true)] : [];
                } else {
                    // Continuation row: append Co-Author if any
                    if ($currentPub !== null) {
                        $coAuthor = trim($data['Co-Authors'] ?? '');
                        if ($coAuthor !== '') {
                            $cleanedCoAuthor = $this->cleanField($coAuthor, true);
                            if (!in_array($cleanedCoAuthor, $currentPub['co_authors'], true)) {
                                $currentPub['co_authors'][] = $cleanedCoAuthor;
                            }
                        }
                    }
                }
            }

            // Save the last publication
            if ($currentPub !== null) {
                $pubs[] = $this->finalizePublicationAuthorsAndIncentives($currentPub);
            }

            fclose($handle);
        }

        return $pubs;
    }

    /**
     * Check if author name is valid (skip '= and empty strings)
     */
    private function isValidAuthorName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || $name === "'=") {
            return false;
        }
        // Remove non-alphabetic characters to check if it has letters
        $onlyLetters = preg_replace('/[^a-zA-Z]/', '', $name);
        if (strlen($onlyLetters) < 2) {
            return false;
        }
        return true;
    }

    /**
     * Group authors into custom structure and calculate incentive amounts
     */
    private function finalizePublicationAuthorsAndIncentives(array $pub): array
    {
        $authors = [];
        $sortOrder = 1;

        // 1. First Author
        if (!empty($pub['first_author']) && $this->isValidAuthorName($pub['first_author'])) {
            $authors[] = [
                'name' => $pub['first_author'],
                'author_role' => 'first',
                'incentive_amount' => 0.0,
                'sort_order' => $sortOrder++
            ];
        }

        // 2. Corresponding Author
        if (!empty($pub['corresponding_author']) && $this->cleanName($pub['corresponding_author']) !== $this->cleanName($pub['first_author'] ?? '')) {
            if ($this->isValidAuthorName($pub['corresponding_author'])) {
                $authors[] = [
                    'name' => $pub['corresponding_author'],
                    'author_role' => 'corresponding',
                    'incentive_amount' => 0.0,
                    'sort_order' => $sortOrder++
                ];
            }
        }

        // 3. Co-Authors
        if (isset($pub['co_authors']) && is_array($pub['co_authors'])) {
            foreach ($pub['co_authors'] as $co) {
                if ($this->cleanName($co) === $this->cleanName($pub['first_author'] ?? '') ||
                    $this->cleanName($co) === $this->cleanName($pub['corresponding_author'] ?? '')) {
                    continue;
                }
                if ($this->isValidAuthorName($co)) {
                    $authors[] = [
                        'name' => $co,
                        'author_role' => 'co_author',
                        'incentive_amount' => 0.0,
                        'sort_order' => $sortOrder++
                    ];
                }
            }
        }

        // Calculate incentives if eligible
        $awardText = trim($pub['award_money_csv'] ?? '');
        $cleanAwardVal = preg_replace('/[^0-9]/', '', $awardText);

        $isNumeric = is_numeric($cleanAwardVal) && (int)$cleanAwardVal > 0;
        $isAlreadyPaid = str_contains(strtolower($awardText), 'paid');

        $shouldCalculate = $isNumeric || $isAlreadyPaid || $this->autoCalculateIncentives;

        $poolAmount = 0;
        if ($shouldCalculate) {
            if ($isNumeric) {
                $poolAmount = (int)$cleanAwardVal;
            } else {
                // Fetch default slab
                $qIndex = $pub['q_index'] ?? '';
                $citeScore = $pub['citescore'] ?? '';
                $slab = $this->getAwardSlab($qIndex, $citeScore);
                $poolAmount = $slab['1st'];
            }

            $numAuthors = count($authors);
            if ($numAuthors > 0) {
                if ($numAuthors === 1) {
                    $authors[0]['incentive_amount'] = (float)$poolAmount;
                } else {
                    // Check if there is a First Author
                    $firstIdx = null;
                    $corrIdx = null;
                    $coIndices = [];

                    foreach ($authors as $idx => $auth) {
                        if ($auth['author_role'] === 'first') {
                            $firstIdx = $idx;
                        } elseif ($auth['author_role'] === 'corresponding') {
                            $corrIdx = $idx;
                        } else {
                            $coIndices[] = $idx;
                        }
                    }

                    if ($firstIdx !== null) {
                        $firstShare = $poolAmount * 0.5;
                        $authors[$firstIdx]['incentive_amount'] = (float)$firstShare;

                        $others = [];
                        if ($corrIdx !== null) $others[] = $corrIdx;
                        foreach ($coIndices as $co_i) $others[] = $co_i;

                        if (count($others) > 0) {
                            $otherShare = ($poolAmount * 0.5) / count($others);
                            foreach ($others as $oth_i) {
                                $authors[$oth_i]['incentive_amount'] = (float)round($otherShare, 2);
                            }
                        }
                    } elseif ($corrIdx !== null) {
                        $corrShare = $poolAmount * 0.5;
                        $authors[$corrIdx]['incentive_amount'] = (float)$corrShare;

                        if (count($coIndices) > 0) {
                            $coShare = ($poolAmount * 0.5) / count($coIndices);
                            foreach ($coIndices as $co_i) {
                                $authors[$co_i]['incentive_amount'] = (float)round($coShare, 2);
                            }
                        }
                    } else {
                        // Split pool equally among co-authors
                        $share = $poolAmount / $numAuthors;
                        foreach ($authors as &$auth) {
                            $auth['incentive_amount'] = (float)round($share, 2);
                        }
                    }
                }
            }
        }

        $pub['calculated_total_incentive'] = (float)$poolAmount;
        $pub['authors'] = $authors;
        return $pub;
    }

    /**
     * Helper to retrieve award slabs based on Q-Index or CiteScore
     */
    private function getAwardSlab($qIndex, $citeScore): array
    {
        $qIndex = strtoupper(trim($qIndex));
        $citeScore = (float)$citeScore;

        if ($qIndex === 'Q1') {
            return ['1st' => 20000, 'corr' => 15000, 'co' => 10000];
        } elseif ($qIndex === 'Q2') {
            return ['1st' => 15000, 'corr' => 12000, 'co' => 8000];
        } elseif ($qIndex === 'Q3') {
            return ['1st' => 10000, 'corr' => 8000, 'co' => 6000];
        } elseif ($qIndex === 'Q4') {
            return ['1st' => 8000, 'corr' => 6000, 'co' => 4000];
        }

        if ($citeScore >= 5.0) {
            return ['1st' => 20000, 'corr' => 15000, 'co' => 10000];
        } elseif ($citeScore >= 3.0) {
            return ['1st' => 15000, 'corr' => 12000, 'co' => 8000];
        } elseif ($citeScore >= 1.0) {
            return ['1st' => 10000, 'corr' => 8000, 'co' => 6000];
        }

        return ['1st' => 5000, 'corr' => 4000, 'co' => 3000];
    }

    /**
     * Step 2: Map Faculty and Department Shortnames (codes) to IDs
     */
    private function step2MapFacultiesAndDepartments(string $firstStepPath): array
    {
        $pubs = json_decode(File::get($firstStepPath), true);

        $facultiesCache = Faculty::all()->keyBy(fn($f) => strtoupper(trim($f->code)));
        $departmentsCache = Department::all()->keyBy(fn($d) => strtoupper(trim($d->code)));

        foreach ($pubs as &$pub) {
            $facultyCode = strtoupper(trim($pub['faculty_short'] ?? ''));
            $deptCode = strtoupper(trim($pub['department_short'] ?? ''));

            if ($facultyCode === 'FAS') {
                $facultyCode = 'FHLS';
                $pub['faculty_short'] = 'FHLS';
            }

            if ($deptCode === 'BA') {
                $deptCode = 'BBA';
                $pub['department_short'] = 'BBA';
            }

            $pub['faculty_id'] = isset($facultiesCache[$facultyCode]) ? $facultiesCache[$facultyCode]->id : null;
            $pub['department_id'] = isset($departmentsCache[$deptCode]) ? $departmentsCache[$deptCode]->id : null;

            // Resolve historical time from CSV fields
            $pubTime = !empty($pub['last_modified_on']) ? $pub['last_modified_on'] :
                       (!empty($pub['last_updated_on']) ? $pub['last_updated_on'] :
                       (!empty($pub['created_on']) ? $pub['created_on'] : now()->format('Y-m-d H:i:s')));

            // Resolve dynamic user ID from Created by column
            $creatorName = $pub['created_by'] ?? '';
            $creatorUserId = $this->resolveOrCreateUser($creatorName);
            $pub['created_by_id'] = $creatorUserId;

            // Determine if the incentive object should exist
            $awardText = trim($pub['award_money_csv'] ?? '');
            $cleanAwardVal = preg_replace('/[^0-9]/', '', $awardText);

            $isNumeric = is_numeric($cleanAwardVal) && (int)$cleanAwardVal > 0;
            $isAlreadyPaid = str_contains(strtolower($awardText), 'paid');

            $shouldHaveIncentive = $isNumeric || $isAlreadyPaid || $this->autoCalculateIncentives;

            if ($shouldHaveIncentive) {
                // Determine status
                $status = 'pending';
                if ($isAlreadyPaid) {
                    $status = 'paid';
                } elseif ($isNumeric) {
                    $status = 'approved';
                }

                // Calculate total amount from authors
                $totalAmount = 0.0;
                if (isset($pub['authors']) && is_array($pub['authors'])) {
                    $totalAmount = array_sum(array_column($pub['authors'], 'incentive_amount'));
                }

                $pub['incentive'] = [
                    'total_amount' => (float)$totalAmount,
                    'status' => $status,
                    'created_by' => $creatorUserId,
                    'created_at' => $pubTime,
                    'updated_at' => $pubTime
                ];
            } else {
                $pub['incentive'] = null;
            }
        }

        return $pubs;
    }

    /**
     * Resolve or dynamically create user from raw Created by name
     */
    private function resolveOrCreateUser(string $rawName): int
    {
        $cleanedName = $this->cleanNamePrefixes($rawName);
        if (empty($cleanedName)) {
            return 5; // Default fallback Admin ID
        }

        static $userCache = [];
        if (isset($userCache[$cleanedName])) {
            return $userCache[$cleanedName];
        }

        $user = \App\Models\User::where('name', 'like', $cleanedName)->first();
        if ($user) {
            $userCache[$cleanedName] = $user->id;
            return $user->id;
        }

        // Generate clean email
        $emailPart = strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $cleanedName));
        $email = $emailPart . '@fms.com';

        $existingByEmail = \App\Models\User::where('email', $email)->first();
        if ($existingByEmail) {
            $userCache[$cleanedName] = $existingByEmail->id;
            return $existingByEmail->id;
        }

        $newUser = \App\Models\User::create([
            'name' => $cleanedName,
            'email' => $email,
            'password' => bcrypt('password123'),
            'is_active' => 1
        ]);

        $userCache[$cleanedName] = $newUser->id;
        return $newUser->id;
    }

    /**
     * Step 3: Match Authors to Teachers using Department/Faculty and Fuzzy names
     */
    private function step3MatchTeachers(string $secondStepPath): array
    {
        $pubs = json_decode(File::get($secondStepPath), true);

        // Load and group teachers by department_id
        $teachersDb = Teacher::with('user')->get();

        // Cache teacher name variations
        foreach ($teachersDb as $t) {
            $names = [];
            if (!empty($t->full_name)) {
                $names[] = $this->cleanName($t->full_name);
            }
            $full_db_name = trim($t->first_name . ' ' . $t->middle_name . ' ' . $t->last_name);
            $names[] = $this->cleanName($full_db_name);
            if ($t->user && !empty($t->user->name)) {
                $names[] = $this->cleanName($t->user->name);
            }
            $t->clean_names = array_unique(array_filter($names));
        }

        // Cache teachers by user email
        $teachersByEmail = [];
        foreach ($teachersDb as $t) {
            if ($t->user && !empty($t->user->email)) {
                $teachersByEmail[strtolower(trim($t->user->email))] = $t;
            }
        }

        $teachersByDept = $teachersDb->groupBy('department_id');

        // Preload and cache existing database authors
        $existingAuthors = Author::all();
        $authorCache = [];
        foreach ($existingAuthors as $author) {
            $authorCache[$this->cleanName($author->name)] = $author->id;
        }

        // Cache author types
        $authorTypesCache = \Illuminate\Support\Facades\DB::table('author_types')->get()->keyBy('name');

        foreach ($pubs as &$pub) {
            $pub['come_from_pd'] = 1;
            $deptId = $pub['department_id'];
            $candidates = isset($teachersByDept[$deptId]) ? $teachersByDept[$deptId] : $teachersDb;

            $pubEmail = strtolower(trim($pub['email'] ?? ''));
            $emailTeacher = ($pubEmail !== '' && isset($teachersByEmail[$pubEmail])) ? $teachersByEmail[$pubEmail] : null;

            if (isset($pub['authors']) && is_array($pub['authors'])) {
                // First priority: map by publication email to the closest matching author
                $emailMappedAuthorIdx = null;
                if ($emailTeacher) {
                    $bestIdx = null;
                    $maxSim = -1.0;
                    foreach ($pub['authors'] as $idx => $author) {
                        $cleanAuthor = $this->cleanName($author['name']);
                        foreach ($emailTeacher->clean_names as $tName) {
                            if ($tName !== '') {
                                similar_text($tName, $cleanAuthor, $sim);
                                if ($sim > $maxSim) {
                                    $maxSim = $sim;
                                    $bestIdx = $idx;
                                }
                            }
                        }
                    }
                    // If similarity is at least 30%, map directly
                    if ($bestIdx !== null && $maxSim >= 30.0) {
                        $emailMappedAuthorIdx = $bestIdx;
                    }
                }

                foreach ($pub['authors'] as $idx => &$author) {
                    // If already mapped by email
                    if ($emailMappedAuthorIdx !== null && $idx === $emailMappedAuthorIdx) {
                        $author['authorable_type'] = 'App\\Models\\Teacher';
                        $author['authorable_id'] = $emailTeacher->id;
                        continue;
                    }

                    $authorName = trim($author['name']);
                    $cleanedAuthorName = $this->cleanNamePrefixes($authorName);
                    $author['name'] = $cleanedAuthorName;

                    $matchedTeacher = $this->findTeacherMatch($cleanedAuthorName, $candidates);

                    if (!$matchedTeacher && $candidates !== $teachersDb) {
                        $matchedTeacher = $this->findTeacherMatch($cleanedAuthorName, $teachersDb);
                    }

                    if ($matchedTeacher) {
                        $author['authorable_type'] = 'App\\Models\\Teacher';
                        $author['authorable_id'] = $matchedTeacher->id;
                    } else {
                        // Unmatched - look up or create in authors table
                        $cleanAuthorName = $this->cleanName($authorName);

                        // Check cache first
                        if (isset($authorCache[$cleanAuthorName])) {
                            $authorId = $authorCache[$cleanAuthorName];
                        } else {
                            // Resolve email
                            $cleanEmailPart = str_replace(' ', '.', $cleanAuthorName);
                            $email = $cleanEmailPart . '@fms.com';

                            // Check DB by email to prevent duplicate entry
                            $existingByEmail = Author::where('email', $email)->first();
                            if ($existingByEmail) {
                                $authorId = $existingByEmail->id;
                            } else {
                                // Resolve author type from researcher_name_with_designation
                                $designation = strtoupper(trim($pub['researcher_name_with_designation'] ?? ''));

                                if ($designation === '') {
                                    $typeName = 'GA';
                                } elseif (str_contains($designation, 'GA')) {
                                    $typeName = 'GA';
                                } elseif (str_contains($designation, 'SA')) {
                                    $typeName = 'SA';
                                } else {
                                    // VF, VF+Faculty, VF+Anything, or any other value
                                    $typeName = 'VF';
                                }

                                $authorTypeId = $authorTypesCache[$typeName]->id ?? 2;

                                $newAuthor = Author::create([
                                    'name' => $authorName,
                                    'email' => $email,
                                    'author_type_id' => $authorTypeId,
                                    'is_active' => 1,
                                ]);

                                $authorId = $newAuthor->id;
                            }

                            // Cache it
                            $authorCache[$cleanAuthorName] = $authorId;
                        }

                        $author['authorable_type'] = 'App\\Models\\Author';
                        $author['authorable_id'] = $authorId;
                    }
                }
            }
        }

        return $pubs;
    }

    /**
     * Step 4: Match PD publications with Old Site publications and split outcomes
     */
    private function step4MatchOldSite(string $thirdStepPath, string $oldExportPath, string $outputDir): void
    {
        $pdPubs = json_decode(File::get($thirdStepPath), true);
        $oldExport = json_decode(File::get($oldExportPath), true);

        // Load all teachers with department and faculty to resolve metadata for old site publications
        $teachersDb = Teacher::with('department.faculty')->get();
        $teachersMap = $teachersDb->keyBy('employee_id');

        // Flat index old site publications
        $oldPubsIndexed = [];
        foreach ($oldExport as $teacherObj) {
            $empId = $teacherObj['_employee_id'] ?? '';
            $teacher = $teachersMap[$empId] ?? null;

            $tPubs = $teacherObj['publications'] ?? [];
            foreach ($tPubs as $op) {
                $title = trim($op['title'] ?? '');
                if ($title === '') continue;

                $oldPubsIndexed[] = [
                    'title' => $title,
                    'clean_title' => $this->cleanName($title),
                    'journal_link' => $this->normalizeUrl($op['journal_link'] ?? ''),
                    'raw_pub' => $op,
                    'teacher' => $teacher,
                    'employee_id' => $empId,
                    'matched' => false
                ];
            }
        }

        $matchingPubs = [];
        $noMatchingPubs = [];

        foreach ($pdPubs as $pd) {
            $cleanPdTitle = $this->cleanName($pd['title'] ?? '');
            $cleanPdLink = $this->normalizeUrl($pd['journal_link'] ?? '');

            $isMatchFound = false;

            foreach ($oldPubsIndexed as $idx => &$oldPub) {
                // Check A: Exact cleaned title match
                // Check B: Exact non-empty normalized URL match
                // Check C: similarity >= 85%
                $titleSimilarity = 0.0;
                if ($cleanPdTitle !== '' && $oldPub['clean_title'] !== '') {
                    similar_text($cleanPdTitle, $oldPub['clean_title'], $titleSimilarity);
                }

                $linkMatch = ($cleanPdLink !== '' && $oldPub['journal_link'] !== '' && $cleanPdLink === $oldPub['journal_link']);

                if ($cleanPdTitle === $oldPub['clean_title'] || $linkMatch || $titleSimilarity >= 85.0) {
                    $isMatchFound = true;
                    $oldPub['matched'] = true; // Mark as matched to exclude from after_fourth_step
                    break;
                }
            }

            if ($isMatchFound) {
                $pd['come_from_old_site'] = 1;
                $matchingPubs[] = $pd;
            } else {
                $pd['come_from_old_site'] = 0;
                $noMatchingPubs[] = $pd;
            }
        }

        // Collect unmatched Old Site publications for after_fourth_step.json
        $unmatchedOldPubs = [];
        foreach ($oldPubsIndexed as $oldPub) {
            if (!$oldPub['matched']) {
                $op = $oldPub['raw_pub'];
                $teacher = $oldPub['teacher'];

                // Map teacher metadata
                $facultyId = null;
                $deptId = null;
                $facShort = null;
                $deptShort = null;

                if ($teacher && $teacher->department) {
                    $deptId = $teacher->department_id;
                    $deptShort = $teacher->department->code;
                    if ($teacher->department->faculty) {
                        $facultyId = $teacher->department->faculty->id;
                        $facShort = $teacher->department->faculty->code;
                    }
                }

                // Construct standardized author block
                $authors = [];
                if ($teacher) {
                    $full_name = trim($teacher->first_name . ' ' . $teacher->middle_name . ' ' . $teacher->last_name);
                    $authors[] = [
                        'name' => $full_name,
                        'author_role' => $op['author_role'] ?? 'co_author',
                        'incentive_amount' => 0.0,
                        'authorable_type' => 'App\\Models\\Teacher',
                        'authorable_id' => $teacher->id,
                        'sort_order' => 1
                    ];
                } else {
                    $authors[] = [
                        'name' => 'Unknown Author',
                        'author_role' => $op['author_role'] ?? 'co_author',
                        'incentive_amount' => 0.0,
                        'authorable_type' => 'App\\Models\\Author',
                        'authorable_id' => 1,
                        'sort_order' => 1
                    ];
                }

                $unmatchedOldPubs[] = [
                    'title' => $this->cleanField($op['title'] ?? '', true),
                    'journal_name' => $this->cleanField($op['journal_name'] ?? '', true),
                    'journal_link' => $op['journal_link'] ?? null,
                    'q_index' => $op['quartile'] ?? null,
                    'citescore' => $op['citescore'] ?? null,
                    'publication_year' => $op['publication_year'] ?? null,
                    'faculty_id' => $facultyId,
                    'department_id' => $deptId,
                    'faculty_short' => $facShort,
                    'department_short' => $deptShort,
                    'abstract' => $this->cleanField($op['abstract'] ?? '', true),
                    'keywords' => $this->cleanField($op['keywords'] ?? '', true),
                    'h_index' => $op['h_index'] ?? null,
                    'impact_factor' => $op['impact_factor'] ?? null,
                    'remarks' => $op['publication_type'] ?? null,
                    'indexed' => $op['linkage'] ?? null,
                    'come_from_pd' => 0,
                    'come_from_old_site' => 1,
                    'award_money_csv' => null,
                    'calculated_total_incentive' => 0.0,
                    'authors' => $authors,
                    'incentive' => null
                ];
            }
        }

        // Save outcomes
        File::put($outputDir . '/fourth_step_matching.json', json_encode($matchingPubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($outputDir . '/fourth_step_no_matching.json', json_encode($noMatchingPubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($outputDir . '/after_fourth_step.json', json_encode($unmatchedOldPubs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Step 4 complete! Created:");
        $this->info("   1. fourth_step_matching.json (" . count($matchingPubs) . " matched publications)");
        $this->info("   2. fourth_step_no_matching.json (" . count($noMatchingPubs) . " unmatched PD publications)");
        $this->info("   3. after_fourth_step.json (" . count($unmatchedOldPubs) . " unmatched Old Site publications)");
    }

    /**
     * Clean and normalize URLs for exact matching
     */
    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = str_replace(['https://', 'http://', 'www.'], '', $url);
        return rtrim($url, '/');
    }

    /**
     * Helper to clean HTML tags and strip special characters (# and @)
     */
    private function cleanField(?string $text, bool $stripHtml = true): string
    {
        if ($text === null) return '';
        if ($stripHtml) {
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        // Remove special characters # and @
        $text = str_replace(['#', '@'], '', $text);
        return trim($text);
    }

    /**
     * Clean author name for match comparison
     */
    private function cleanName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z]/', '', $name);
        return trim($name);
    }

    /**
     * Find best matching teacher from candidates list
     */
    private function findTeacherMatch(string $authorName, $candidates): ?Teacher
    {
        $cleanAuthor = $this->cleanName($authorName);
        if ($cleanAuthor === '') return null;

        // 1. Exact match of cleaned names
        foreach ($candidates as $t) {
            foreach ($t->clean_names as $tName) {
                if ($tName === $cleanAuthor) {
                    return $t;
                }
            }
        }

        // 2. Substring match
        foreach ($candidates as $t) {
            foreach ($t->clean_names as $tName) {
                if ($tName !== '' && (str_contains($tName, $cleanAuthor) || str_contains($cleanAuthor, $tName))) {
                    return $t;
                }
            }
        }

        // 3. Fuzzy match via similar_text (high confidence threshold >= 75%)
        foreach ($candidates as $t) {
            foreach ($t->clean_names as $tName) {
                if ($tName !== '') {
                    similar_text($tName, $cleanAuthor, $sim);
                    if ($sim >= 75) {
                        return $t;
                    }
                }
            }
        }

        // 4. AI-assisted matching fallback (if enabled)
        if ($this->useAiForNameMatching) {
            // Find potential candidates who have at least some overlap (similarity >= 40%)
            $potentialCandidates = [];
            foreach ($candidates as $t) {
                $maxSim = 0.0;
                foreach ($t->clean_names as $tName) {
                    if ($tName !== '') {
                        similar_text($tName, $cleanAuthor, $sim);
                        if ($sim > $maxSim) {
                            $maxSim = $sim;
                        }
                    }
                }
                if ($maxSim >= 40) {
                    $potentialCandidates[] = $t;
                }
            }

            if (count($potentialCandidates) > 0) {
                try {
                    $aiService = resolve(\App\Services\VertexAIService::class);

                    $prompt = "You are an extremely strict academic name-matching expert.\n";
                    $prompt .= "Determine if the target author name: \"{$authorName}\" refers to the exact same physical person as any of the following candidate teachers.\n\n";
                    $prompt .= "CRITICAL MATCHING GUIDELINES:\n";
                    $prompt .= "1. Only return a match if you are 98% confident it is the same person (e.g. initials like 'M. A. Rahman' matching 'Md. Ashiqur Rahman').\n";
                    $prompt .= "2. DO NOT match names that belong to different people but sound similar (e.g. 'Abu Kausar' and 'Abu Kaisar Mohammad Masum' are DIFFERENT people; 'Abu Kausar' lacks the significant parts 'Mohammad Masum').\n";
                    $prompt .= "3. If one name has major name parts (like middle or last names) that are completely absent in the other, they are likely distinct individuals. Do not match them.\n";
                    $prompt .= "4. If you have any doubt, return {\"matched_id\": null}.\n\n";
                    $prompt .= "Candidates list:\n";
                    foreach ($potentialCandidates as $pc) {
                        $full_name = trim($pc->first_name . ' ' . $pc->middle_name . ' ' . $pc->last_name);
                        $prompt .= "- ID: {$pc->id}, Name: \"{$full_name}\"\n";
                    }
                    $prompt .= "\nIf one of the candidates matches, return its ID in JSON format: {\"matched_id\": ID}. If none of the candidates match, return {\"matched_id\": null}.\n";
                    $prompt .= "Return ONLY the JSON object. Do not include markdown code block syntax.";

                    $res = $aiService->generateContent('gemini-2.5-flash', $prompt, 0.0, 'application/json');
                    $content = $res['content'] ?? '';

                    // Clean markdown code blocks if present
                    $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
                    $content = preg_replace('/\s*```$/m', '', $content);

                    $dec = json_decode(trim($content), true);
                    $matchedId = $dec['matched_id'] ?? null;

                    if ($matchedId !== null) {
                        foreach ($potentialCandidates as $pc) {
                            if ((int)$pc->id === (int)$matchedId) {
                                return $pc;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Fail silently and fallback to standard creation
                }
            }
        }

        return null;
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
