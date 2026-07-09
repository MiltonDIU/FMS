<?php

namespace App\Services;

use App\Models\Major;
use App\Models\EducationalInstitution;
use Illuminate\Support\Facades\Log;
use Exception;

class DuplicateFinderService
{
    protected VertexAIService $ai;

    public function __construct(VertexAIService $ai)
    {
        $this->ai = $ai;
    }

    public function getSuggestionsWithCache(string $type, bool $forceRefresh = false): array
    {
        $cachePath = storage_path("app/ai-cache/{$type}_duplicates.json");
        $currentCount = $type === 'major' 
            ? Major::where('is_active', true)->count() 
            : EducationalInstitution::where('is_active', true)->count();

        if (!$forceRefresh && file_exists($cachePath)) {
            $data = json_decode(file_get_contents($cachePath), true);
            if (is_array($data)) {
                $data['current_records'] = $currentCount;
                $data['is_cached'] = true;
                return $data;
            }
        }

        // Run fresh duplicate scan
        $suggestions = $this->findDuplicates($type);

        $cacheData = [
            'last_checked_at' => now()->format('Y-m-d H:i:s'),
            'total_records' => $currentCount,
            'suggestions' => $suggestions,
        ];

        // Ensure directory exists
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cachePath, json_encode($cacheData, JSON_PRETTY_PRINT));

        $cacheData['current_records'] = $currentCount;
        $cacheData['is_cached'] = false;

        return $cacheData;
    }

    public function removeGroupFromCache(string $type, int $targetId, array $sourceIds): void
    {
        $cachePath = storage_path("app/ai-cache/{$type}_duplicates.json");
        if (!file_exists($cachePath)) {
            return;
        }

        $data = json_decode(file_get_contents($cachePath), true);
        if (!is_array($data) || !isset($data['suggestions'])) {
            return;
        }

        $allMergedIds = array_merge([$targetId], $sourceIds);
        $newSuggestions = [];

        foreach ($data['suggestions'] as $group) {
            $primaryId = (int)$group['primary']['id'];
            $dupIds = array_column($group['duplicates'], 'id');
            $allGroupIds = array_merge([$primaryId], $dupIds);

            if (empty(array_intersect($allGroupIds, $allMergedIds))) {
                $newSuggestions[] = $group;
            }
        }

        $data['suggestions'] = $newSuggestions;
        $data['total_records'] = max(0, $data['total_records'] - count($sourceIds));

        file_put_contents($cachePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function findDuplicates(string $type): array
    {
        $records = [];
        if ($type === 'major') {
            $records = Major::where('is_active', true)->get(['id', 'name']);
        } else {
            $records = EducationalInstitution::where('is_active', true)->get(['id', 'name']);
        }

        if ($records->count() < 2) {
            return [];
        }

        try {
            return $this->findDuplicatesWithAI($records, $type);
        } catch (Exception $e) {
            Log::warning("AI Duplicate Finder failed: " . $e->getMessage() . ". Falling back to local heuristic.");
            return $this->findDuplicatesWithHeuristic($records);
        }
    }

    protected function findDuplicatesWithAI($records, string $type): array
    {
        $recordsString = "";
        foreach ($records as $record) {
            $recordsString .= "{$record->id} - {$record->name}\n";
        }

        $typeName = $type === 'major' ? 'majors or academic disciplines' : 'educational institutions or universities';

        $prompt = "You are a data cleansing expert. Your task is to identify duplicate records from a list of {$typeName}.
Duplicates represent the same entity, but have different spellings, typos, abbreviations, acronyms, or word orders.
Do not group distinct fields or entities (for example, 'Civil Engineering' is not 'Mechanical Engineering', 'Dhaka College' is not 'Dhaka University', etc.).

Here is the list of records:
{$recordsString}

Output a JSON object with a single key \"groups\". Under \"groups\", output an array of objects. Each object represents a cluster of duplicates and must have:
- \"primary_id\": The ID of the most standard, formal, and complete name from the cluster.
- \"duplicate_ids\": An array of IDs of the other records in the cluster that should be merged into the primary one.

Only output valid JSON. Do not include any explanations, markdown blocks, or extra characters.";

        $response = $this->ai->generateContent('gemini-2.5-flash', $prompt, 0.0, 'application/json');
        
        $content = trim($response['content'] ?? '');
        
        // Strip markdown code blocks if the model outputs them anyway
        if (strpos($content, '```') === 0) {
            $content = preg_replace('/^```(?:json)?\n?|```$/m', '', $content);
            $content = trim($content);
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['groups'])) {
            throw new Exception("Invalid JSON response from AI.");
        }

        $formatted = [];
        $recordMap = $records->keyBy('id');

        foreach ($data['groups'] as $group) {
            $primaryId = $group['primary_id'] ?? null;
            $duplicateIds = $group['duplicate_ids'] ?? [];

            if ($primaryId && !empty($duplicateIds)) {
                $primaryRecord = $recordMap->get($primaryId);
                if (!$primaryRecord) continue;

                $duplicates = [];
                foreach ($duplicateIds as $dupId) {
                    $dupRec = $recordMap->get($dupId);
                    if ($dupRec) {
                        $duplicates[] = [
                            'id' => $dupRec->id,
                            'name' => $dupRec->name,
                        ];
                    }
                }

                if (!empty($duplicates)) {
                    $formatted[] = [
                        'primary' => [
                            'id' => $primaryRecord->id,
                            'name' => $primaryRecord->name,
                        ],
                        'duplicates' => $duplicates,
                    ];
                }
            }
        }

        return $formatted;
    }

    protected function findDuplicatesWithHeuristic($records): array
    {
        $formatted = [];
        $visited = [];

        // Simple word sorting and similarity check
        $processed = [];
        foreach ($records as $record) {
            $name = strtolower(trim($record->name));
            // remove common suffixes/prefixes to focus on root names
            $normalized = preg_replace('/\b(university of|of technology|science and technology|college|institute of)\b/i', '', $name);
            $words = preg_split('/\s+/', trim($normalized));
            sort($words);
            $processed[$record->id] = [
                'record' => $record,
                'words' => implode(' ', $words),
                'name' => $name,
            ];
        }

        foreach ($processed as $id1 => $item1) {
            if (isset($visited[$id1])) continue;

            $duplicates = [];
            foreach ($processed as $id2 => $item2) {
                if ($id1 === $id2 || isset($visited[$id2])) continue;

                // 1. Check exact word-sorted match
                $match = false;
                if ($item1['words'] === $item2['words']) {
                    $match = true;
                } else {
                    // 2. Check similar text
                    similar_text($item1['name'], $item2['name'], $percent);
                    if ($percent > 88) {
                        $match = true;
                    } else {
                        // 3. Levenshtein for typos
                        $lev = levenshtein($item1['name'], $item2['name']);
                        if ($lev > 0 && $lev <= 2) {
                            $match = true;
                        }
                    }
                }

                if ($match) {
                    $duplicates[] = [
                        'id' => $item2['record']->id,
                        'name' => $item2['record']->name,
                    ];
                    $visited[$id2] = true;
                }
            }

            if (!empty($duplicates)) {
                $visited[$id1] = true;
                $formatted[] = [
                    'primary' => [
                        'id' => $item1['record']->id,
                        'name' => $item1['record']->name,
                    ],
                    'duplicates' => $duplicates,
                ];
            }
        }

        return $formatted;
    }
}
