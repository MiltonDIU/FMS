<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportOldTeachersCommand extends Command
{
    protected $signature = 'export:old-teachers {--output=teachers_export.json} {--limit=1}';
    protected $description = 'Export teachers from old database to JSON format for import';

    public function handle()
    {
        $this->info('Starting export from old database...');

        try {
            // Fetch all teachers with their department/faculty mapping
            $teachers = DB::connection('old_db')
                ->table('teacher as t')
                ->leftJoin('dfd_add as dfd', 't.id', '=', 'dfd.teacher_id')
                ->leftJoin('department as dept', 'dfd.department_id', '=', 'dept.department_id')
                ->leftJoin('faculty as fac', 'dfd.faculty_id', '=', 'fac.faculty_id')
                ->leftJoin('designation as des', 'dfd.designation_id', '=', 'des.designation_id')
                ->select(
                    't.*',
                    'dfd.department_id',
                    'dfd.faculty_id',
                    'dfd.designation_id',
                    'dept.departmentname',
                    'fac.facultyname',
                    'des.designation'
                )
                ->where('t.status', 1)
                ->limit($this->option('limit'))
                ->get();

            $this->info("Found {$teachers->count()} teachers");

            $exportData = [];

            foreach ($teachers as $teacher) {
                $exportData[] = $this->transformTeacher($teacher);
            }

            // Save to JSON file
            $filename = $this->option('output');
            $path = storage_path('app/public/exports/' . $filename);
            
            // Ensure directory exists
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("Export completed successfully!");
            $this->info("File saved to: {$path}");
            $this->info("Total teachers exported: " . count($exportData));

        } catch (\Exception $e) {
            $this->error("Export failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function transformTeacher($teacher)
    {
        // Parse name into parts
        $nameParts = $this->parseName($teacher->name);

        return [
            'employee_id' => $teacher->employeeID,
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name' => $nameParts['last_name'],
            'designation_id' => $teacher->designation_id ?? null,
            'department_id' => $teacher->department_id ?? null,
            'webpage' => $teacher->webpage ?: null,
            'phone' => $teacher->phone ?: null,
            'personal_phone' => $teacher->cell ?: null,
            'email' => $teacher->email ?: null,
            'bio' => $this->cleanText($teacher->currentResearch),
            'research_interest' => $this->cleanText($teacher->teachingArea),
            'is_active' => $teacher->study_leave == 0 ? true : false,
            'is_public' => true,
            'user' => [
                'name' => $teacher->name,
                'email' => $this->getValidEmail($teacher->email, $teacher->employeeID),
            ],
            'educations' => [], // Removed for now
            'job_experiences' => $this->parseJobExperiences($teacher->previousEmployment),
            'awards' => $this->parseAwards($teacher->awardScholarship),
            'certifications' => [],
            'training_experiences' => $this->parseTrainings($teacher->trainingExperience),
            'skills' => [],
            'teaching_areas' => $this->parseTeachingAreas($teacher->teachingArea),
            'memberships' => $this->parseMemberships($teacher->membership),
            'social_links' => [],
        ];
    }

    private function parseName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        
        if (count($parts) == 1) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => ''];
        } elseif (count($parts) == 2) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => $parts[1]];
        } else {
            return [
                'first_name' => $parts[0],
                'middle_name' => implode(' ', array_slice($parts, 1, -1)),
                'last_name' => end($parts)
            ];
        }
    }

    private function cleanText($text)
    {
        if (empty($text)) return null;
        
        // Remove HTML tags
        $text = strip_tags($text);
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text) ?: null;
    }

    private function parseEducations($text)
    {
        if (empty($text)) return [];
        
        $educations = [];
        
        // Remove HTML tags but keep line breaks
        $text = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", $text);
        $text = strip_tags($text);
        
        // Clean up extra whitespace and decode HTML entities
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Split by common degree patterns
        $lines = preg_split('/\n+/', $text);
        
        // Common degree patterns
        $degreePatterns = [
            'Ph\.?D',
            'M\.?Phil',
            'M\.?Sc',
            'M\.?S',
            'MBA',
            'BBA',
            'B\.?Sc',
            'B\.?A',
            'Honors',
            'Diploma',
            'Masters?',
            'Bachelors?',
        ];
        
        $currentEducation = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if line starts with a degree
            $foundDegree = false;
            foreach ($degreePatterns as $pattern) {
                if (preg_match('/^(' . $pattern . ')/i', $line, $matches)) {
                    // Save previous education if exists
                    if ($currentEducation) {
                        $educations[] = $currentEducation;
                    }
                    
                    // Start new education
                    $currentEducation = [
                        'degree_type_id' => null,
                        'institution' => null,
                        'major' => null,
                        'passing_year' => null,
                        'result_type_id' => null,
                        'cgpa' => null,
                        'scale' => null,
                    ];
                    
                    // Extract degree name
                    $degree = $matches[1];
                    
                    // Try to extract major/concentration
                    if (preg_match('/(?:major in|Area of Concentration:?)\s*([^,\n]+)/i', $line, $majorMatch)) {
                        $currentEducation['major'] = trim(strip_tags($majorMatch[1]));
                    }
                    
                    // Try to extract institution
                    if (preg_match('/(?:from|Department of|Institute of|College of)\s+([^,\n\.]+(?:University|Institute|College)[^,\n\.]*)/i', $line, $instMatch)) {
                        $currentEducation['institution'] = trim($instMatch[1]);
                    }
                    
                    // Try to extract year
                    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $line, $yearMatch)) {
                        $currentEducation['passing_year'] = (int)$yearMatch[1];
                    }
                    
                    // Try to extract CGPA
                    if (preg_match('/CGPA[:\s]*([0-9.]+)/i', $line, $cgpaMatch)) {
                        $currentEducation['cgpa'] = (float)$cgpaMatch[1];
                        $currentEducation['result_type_id'] = 1; // Assuming 1 is CGPA
                        $currentEducation['scale'] = 4.0; // Default scale
                    }
                    
                    $foundDegree = true;
                    break;
                }
            }
            
            // If no degree found but we have current education, append to institution/major
            if (!$foundDegree && $currentEducation) {
                // Try to extract institution if not already set
                if (!$currentEducation['institution'] && preg_match('/(?:University|Institute|College)/i', $line)) {
                    $currentEducation['institution'] = trim($line);
                }
                
                // Try to extract year if not already set
                if (!$currentEducation['passing_year'] && preg_match('/\b(19\d{2}|20\d{2})\b/', $line, $yearMatch)) {
                    $currentEducation['passing_year'] = (int)$yearMatch[1];
                }
                
                // Try to extract CGPA if not already set
                if (!$currentEducation['cgpa'] && preg_match('/CGPA[:\s]*([0-9.]+)/i', $line, $cgpaMatch)) {
                    $currentEducation['cgpa'] = (float)$cgpaMatch[1];
                    $currentEducation['result_type_id'] = 1;
                    $currentEducation['scale'] = 4.0;
                }
            }
        }
        
        // Add last education
        if ($currentEducation) {
            $educations[] = $currentEducation;
        }
        
        // Filter out incomplete educations
        $educations = array_filter($educations, function($edu) {
            return !empty($edu['institution']) || !empty($edu['major']);
        });
        
        return array_values($educations);
    }

    private function parseJobExperiences($text)
    {
        if (empty($text)) return [];
        
        $experiences = [];
        
        // First, split by <p> tags and <li> tags
        $text = str_replace(['</p>', '</li>', '</li>'], "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        
        // Split by numbered list pattern (1., 2., 3., etc.) BEFORE splitting by newlines
        // This ensures each numbered item becomes a separate entry
        $items = preg_split('/(?=\d+\.\s)/', $text);
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item) || strlen($item) < 5) continue;
            
            // Remove leading number and dot (e.g., "1. ", "2. ")
            $item = preg_replace('/^\d+\.\s*/', '', $item);
            
            // Further split by newlines within this item (in case there are multiple lines)
            $lines = preg_split('/\n+/', $item);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strlen($line) < 5) continue;
                
                $experience = [
                    'position' => null,
                    'organization' => null,
                    'department' => null,
                    'location' => null,
                    'country_id' => 18, // Default Bangladesh
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'responsibilities' => null,
                ];
                
                // Try to extract position and organization
                // Pattern: "Position at Organization" or "Position, Organization" or "Position: Organization"
                if (preg_match('/^([^:,@]+)(?::|,|@|at)\s*(.+)$/i', $line, $matches)) {
                    $experience['position'] = trim($matches[1]);
                    $experience['organization'] = trim($matches[2]);
                } else {
                    // If no clear pattern, use the whole line as position
                    $experience['position'] = $line;
                }
                
                // Try to extract country name and lookup ID
                $countryNames = ['USA', 'United States', 'UK', 'United Kingdom', 'Canada', 'Australia', 'India', 'Pakistan', 'China', 'Japan', 'Germany', 'France', 'Saudi Arabia', 'UAE', 'Kuwait', 'Qatar'];
                foreach ($countryNames as $countryName) {
                    if (stripos($line, $countryName) !== false) {
                        // Try to find country ID from new database
                        $countryId = DB::table('countries')->where('name', 'like', '%' . $countryName . '%')->value('id');
                        if ($countryId) {
                            $experience['country_id'] = $countryId;
                        }
                        break;
                    }
                }
                
                // Try to extract years
                if (preg_match('/(19\d{2}|20\d{2})\s*[-–to]+\s*(19\d{2}|20\d{2}|present|current)/i', $line, $yearMatch)) {
                    $experience['start_date'] = $yearMatch[1] . '-01-01';
                    if (preg_match('/present|current/i', $yearMatch[2])) {
                        $experience['is_current'] = true;
                    } else {
                        $experience['end_date'] = $yearMatch[2] . '-12-31';
                    }
                }
                
                if (!empty($experience['position'])) {
                    $experiences[] = $experience;
                }
            }
        }
        
        return $experiences;
    }

    private function parseAwards($text)
    {
        if (empty($text)) return [];
        
        $awards = [];
        
        // Remove HTML tags but keep line breaks
        $text = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        
        // Split by numbered list pattern first (1., 2., 3., etc.)
        // Lookahead for number + dot + space
        $items = preg_split('/(?=\d+\.\s)/', $text);
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            
            // Remove leading number and dot if present
            $item = preg_replace('/^\d+\.\s*/', '', $item);
            
            // Further split by newlines, in case there are multiple lines per item or no numbers
            // Use preg_split with PREG_SPLIT_NO_EMPTY
            $lines = preg_split('/\n+/', $item, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strlen($line) < 5) continue;
                
                $award = [
                    'title' => $line,
                    'awarding_body' => null,
                    'year' => null,
                ];
                
                // Try to extract year (4-digit number)
                if (preg_match('/\b(19\d{2}|20\d{2})\b/', $line, $yearMatch)) {
                    $award['year'] = (int)$yearMatch[1];
                }
                
                // Try to extract awarding body using multiple patterns
                $patterns = [
                    // Pattern: "Award", Organization (quoted award followed by comma and organization)
                    '/^"[^"]+"\s*,\s*([^,\.]+?)(?:,|\.|$)/i',
                    // Pattern: "Organized by; Organization" (with semicolon)
                    '/(?:Organized by|Organised by);?\s+([^,\.]+?)(?:,|\.$|\.)/i',
                    // Pattern: "for Organization" (e.g., "Advisor for BTRI")
                    '/\bfor\s+(?:the\s+)?([^,\.]+?(?:Institute|University|Board|Ministry|Center|Centre|Foundation|Organization|Conference|Committee)[^,\.]*?)(?:,|\.|from|$)/i',
                    // Pattern: "Award by Organization"
                    '/\bby\s+(?:the\s+)?([^,\.]+?)(?:,|\.|for|dated|$)/i',
                    // Pattern: "Recognized by Organization"
                    '/(?:Recognized|Awarded|Appointed|Selected)\s+by\s+(?:the\s+)?([^,\.]+?)(?:,|\.|for|$)/i',
                    // Pattern: "at University/Institute" (e.g., "at Turkey to teach students of University")
                    '/(?:at|of)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\s+(?:University|Institute|College))/i',
                    // Pattern: "nominated by Organization"
                    '/(?:nominated by|appointed by)[;:\s]+([^,\.]+?)(?:,|\.|dated|$)/i',
                    // Pattern: "prepared by Organization"
                    '/(?:prepared by)[;:\s]+([^,\.]+?)(?:,|\.|nominated|$)/i',
                    // Pattern: "Award from Organization"
                    '/\bfrom\s+([^,\.]+?)(?:,|\.|in\s+\d{4}|$)/i',
                    // Pattern: "published by / issued by Organization"
                    '/(?:published by|issued by)[;:\s]+([^,\.]+?)(?:,|\.|and|$)/i',
                    // Pattern: Extract organization from parentheses with common acronyms
                    '/\(([A-Z]{2,})\)/i',
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $line, $bodyMatch)) {
                        $body = trim($bodyMatch[1]);
                        
                        // Clean up the extracted body
                        $body = preg_replace('/\s+and\s+(followed|students|till|associated).*$/i', '', $body);
                        $body = preg_replace('/\s+in\s+\d{4}.*$/i', '', $body);
                        $body = preg_replace('/\s*,\s*Srilanka.*$/i', '', $body); // Remove country suffix
                        
                        // Don't use it as awarding body if:
                        // - It contains date patterns (13 to 17 March, etc.)
                        // - It's too short (< 2 chars for acronyms, < 3 for full names)
                        // - It looks like a location only
                        if (preg_match('/\d{1,2}\s+(?:to|-)/', $body)) {
                            continue;
                        }
                        
                        if (strlen($body) < 2) {
                            continue;
                        }
                        
                        // Skip if it's common non-organization phrases
                        if (preg_match('/^(the|a|an|this|that|these|those|students|teach)\b/i', $body)) {
                            continue;
                        }
                        
                        // Skip pure location names unless they contain organization keywords
                        if (preg_match('/^(Bangladesh|Turkey|Malaysia|Vietnam|India|China|USA|UK)$/i', $body)) {
                            continue;
                        }
                        
                        $award['awarding_body'] = $body;
                        break;
                    }
                }
                
                // Special case: If award starts with organization name (e.g., "Russian Government Scholarship")
                // Extract first 2-3 words as potential awarding body
                if (!$award['awarding_body']) {
                    // Check for common organization keywords at the start
                    if (preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3})\s+(?:Scholarship|Award|Grant|Fellowship|Financial)/i', $line, $orgMatch)) {
                        $award['awarding_body'] = trim($orgMatch[1]);
                    }
                }
                
                // Special case: Extract "in the Xth International Conference..."
                if (!$award['awarding_body'] && preg_match('/in\s+the\s+(\d+(?:st|nd|rd|th)\s+International\s+Conference[^,\.]*)/i', $line, $confMatch)) {
                    $award['awarding_body'] = trim($confMatch[1]);
                }
                
                if (!empty($award['title'])) {
                    $awards[] = $award;
                }
            }
        }
        
        return $awards;
    }

    private function parseTrainings($text)
    {
        if (empty($text)) return [];
        
        $trainings = [];
        
        // Remove HTML tags but keep line breaks
        $text = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        
        $lines = preg_split('/\n+/', $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 5) continue;
            
            $training = [
                'title' => $line,
                'organization' => null,
                'category' => 'Training', // Default category
                'duration_days' => null,
                'completion_date' => null,
                'year' => null,
                'country_id' => 18, // Default Bangladesh
                'certificate_url' => null,
                'is_online' => false,
                'description' => null,
                'sort_order' => null,
            ];
            
            // Check for online keywords
            if (preg_match('/(online|virtual|webinar|remote)/i', $line)) {
                $training['is_online'] = true;
            }
            
            // Try to extract year
            if (preg_match('/\b(19\d{2}|20\d{2})\b/', $line, $yearMatch)) {
                $training['year'] = (int)$yearMatch[1];
            }
            
            // Try to extract exact completion date
            if (preg_match('/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+(?:19|20)\d{2})\b/i', $line, $dateMatch)) {
                try {
                    $training['completion_date'] = date('Y-m-d', strtotime($dateMatch[1]));
                } catch (\Exception $e) {
                    // Ignore date parsing error
                }
            }
            
            // Try to extract organization (text after "at" or "by")
            if (preg_match('/(?:at|by|from)\s+(.+?)(?:,|\.|$)/i', $line, $orgMatch)) {
                $org = trim($orgMatch[1]);
                // Clean up org
                $org = preg_replace('/\s+on\s+.*$/i', '', $org);
                // Use if reasonable length and not a date
                if (strlen($org) > 3 && !preg_match('/\d/', $org)) {
                    $training['organization'] = $org;
                }
            }
            
            // Try to extract country name and lookup ID
            $countryNames = ['USA', 'United States', 'UK', 'United Kingdom', 'Canada', 'Australia', 'India', 'Pakistan', 'China', 'Japan', 'Germany', 'France', 'Saudi Arabia', 'UAE', 'Kuwait', 'Qatar', 'Malaysia', 'Thailand', 'Singapore', 'Turkey', 'Russia', 'Vietnam'];
            foreach ($countryNames as $countryName) {
                if (stripos($line, $countryName) !== false) {
                    // Try to find country ID from new database
                    $countryId = DB::table('countries')->where('name', 'like', '%' . $countryName . '%')->value('id');
                    if ($countryId) {
                        $training['country_id'] = $countryId;
                    }
                    break;
                }
            }
            
            // Try to extract duration
            if (preg_match('/(\d+)\s*(?:days?|weeks?|months?)/i', $line, $durationMatch)) {
                $days = (int)$durationMatch[1];
                if (stripos($line, 'week') !== false) {
                    $days *= 7;
                } elseif (stripos($line, 'month') !== false) {
                    $days *= 30;
                }
                $training['duration_days'] = $days;
            }
            
            if (!empty($training['title'])) {
                $trainings[] = $training;
            }
        }
        
        return $trainings;
    }

    private function parseTeachingAreas($text)
    {
        if (empty($text)) return [];
        
        $areas = [];
        
        // First, split by <p> tags to handle multiple paragraphs
        $paragraphs = preg_split('/<\/p>|<p[^>]*>/i', $text);
        
        // Also split by <li> tags
        $items = [];
        foreach ($paragraphs as $para) {
            $liItems = preg_split('/<\/li>|<li[^>]*>/i', $para);
            $items = array_merge($items, $liItems);
        }
        
        // Now process each item
        foreach ($items as $item) {
            // Remove all remaining HTML tags
            $item = strip_tags($item);
            $item = html_entity_decode($item);
            $item = trim($item);
            
            if (empty($item) || strlen($item) < 3) continue;
            
            // Split by comma if multiple areas in one line
            $subItems = array_map('trim', explode(',', $item));
            
            foreach ($subItems as $subItem) {
                $subItem = trim($subItem);
                
                // Skip empty, very short items, or items that are just numbers/punctuation
                if (empty($subItem) || strlen($subItem) < 3 || preg_match('/^[\d\.\s]+$/', $subItem)) {
                    continue;
                }
                
                $areas[] = [
                    'area' => $subItem,
                    'description' => null,
                ];
            }
        }
        
        return $areas;
    }

    private function parseMemberships($text)
    {
        if (empty($text)) return [];
        
        $memberships = [];
        $lines = explode("\n", strip_tags($text));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $memberships[] = [
                'membership_organization_id' => null,
                'membership_type_id' => null,
                'membership_id' => $line,
                'status' => 'Active',
            ];
        }
        
        return $memberships;
    }

    private function getValidEmail($email, $employeeId)
    {
        // Clean and validate email
        $email = trim($email);
        
        // Check if email is valid
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        // Generate email from employee ID
        $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $employeeId);
        return strtolower($cleanId) . '@diu.edu.bd';
    }
}
