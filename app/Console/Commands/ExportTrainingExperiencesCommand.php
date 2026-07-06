<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Phase 2 — Training Experiences Export (AI-powered HTML parser)
 *
 * Source data shape (old DB / JSON dump):
 *   employeeID           string
 *   trainingExperience   HTML string — unstructured <ul><li> list
 *
 * Each teacher's HTML is sent to Claude in batches. Claude extracts
 * structured fields matching the TrainingExperience model and returns JSON.
 *
 * Usage:
 *   php artisan export:training-experiences
 *   php artisan export:training-experiences --source=db
 *   php artisan export:training-experiences --source=json --json-file=teacher_2_.json
 *   php artisan export:training-experiences --limit=20
 *   php artisan export:training-experiences --batch-size=5
 *   php artisan export:training-experiences --dry-run
 *   php artisan export:training-experiences --resume
 */
class ExportTrainingExperiencesCommand extends Command
{
    protected $signature = 'export:training-experiences
                            {--source=db                               : Data source: "db" (old_db connection) or "json"}
                            {--json-file=old_teacher.json              : Source JSON filename (inside storage/app/public/)}
                            {--output=training_experiences_export.json : Output filename (inside storage/app/public/exports/)}
                            {--limit=0                                  : Limit number of teachers processed (0 = all)}
                            {--batch-size=10                            : Teachers per AI API call}
                            {--provider=auto                            : AI provider: auto|openrouter|gemini|groq|anthropic|deepseek|internal|heuristic}
                            {--dry-run                                  : Parse but do not write output file}
                            {--overwrite                                : Overwrite the output file and re-process all records}';

    protected $description = 'Export & AI-parse training experiences from old DB/JSON — Phase 2 (supports OpenRouter/Gemini/Groq/Anthropic/DeepSeek)';

    const DEFAULT_COUNTRY_ID = 18; // Bangladesh

    // AI provider model names
    const MODELS = [
        'anthropic'  => 'claude-sonnet-4-20250514',
        'groq'       => 'llama-3.3-70b-versatile',
        'gemini'     => 'gemini-2.5-flash',
        'openrouter' => 'google/gemini-3.5-flash',
        'deepseek'   => 'deepseek-v4-flash',
    ];

    protected array $countryMap      = [];
    protected array $employeeToOldId = [];
    protected string $aiProvider     = 'openrouter';  // resolved at runtime

    public function handle(): int
    {
        $this->info('Building lookup tables...');
        $this->buildCountryMap();
        $this->buildEmployeeIdMap();
        $this->resolveAiProvider();

        $rawRecords = $this->loadSourceRecords();
        if (empty($rawRecords)) {
            $this->error('No source records found.');
            return 1;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $rawRecords = array_slice($rawRecords, 0, $limit);
        }

        $this->info('Total teachers to process: ' . count($rawRecords));

        // Auto-resume check: skip already done employeeIDs unless --overwrite is set
        $existingData = [];
        if (!$this->option('overwrite')) {
            $existingData = $this->loadExistingOutput();
            if (!empty($existingData)) {
                $doneEmployeeIds = array_column($existingData, '_employee_id');
                $before = count($rawRecords);
                $rawRecords = array_values(array_filter(
                    $rawRecords,
                    fn($r) => !in_array((string)$r['employeeID'], array_map('strval', $doneEmployeeIds))
                ));
                $skippedCount = $before - count($rawRecords);
                if ($skippedCount > 0) {
                    $this->info("🔄 Found existing progress — automatically skipped {$skippedCount} already processed teachers.");
                    $this->info("💡 Use --overwrite if you want to re-process all from scratch.");
                }
            }
        }

        // ── Batch process ──
        $batchSize   = max(1, (int) $this->option('batch-size'));
        $batches     = array_chunk($rawRecords, $batchSize);
        $exportData  = $existingData;
        $totalParsed = 0;
        $totalFailed = 0;
        $failLog     = [];

        $bar = $this->output->createProgressBar(count($rawRecords));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');

        foreach ($batches as $batchIndex => $batch) {
            $bar->setMessage('Batch ' . ($batchIndex + 1) . '/' . count($batches) . ' — calling Claude...');

            if ($this->aiProvider === 'internal') {
                $this->handleInternalBatch($batch, $exportData);
                return 0;
            }

            try {
                $parsed = $this->parseWithClaude($batch);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Claude API error on batch {$batchIndex}: " . $e->getMessage());
                foreach ($batch as $record) {
                    $failLog[] = [
                        'employeeID' => $record['employeeID'],
                        'reason'     => 'claude_api_error',
                        'error'      => $e->getMessage(),
                    ];
                    $totalFailed++;
                }
                $bar->advance(count($batch));
                continue;
            }

            foreach ($parsed as $employeeId => $trainings) {
                $oldTeacherId = $this->employeeToOldId[(string)$employeeId] ?? null;
                $exportData[] = [
                    '_employee_id'         => (string) $employeeId,
                    '_old_teacher_id'      => $oldTeacherId,
                    'training_experiences' => $trainings,
                ];
                $totalParsed += count($trainings);
            }

            $bar->advance(count($batch));

            if ($batchIndex < count($batches) - 1) {
                usleep(300_000);
            }
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine();

        // ── Write output ──
        $exportDir = storage_path('app/public/exports/');
        if (!is_dir($exportDir)) mkdir($exportDir, 0755, true);

        if (!$this->option('dry-run')) {
            $path = $exportDir . $this->option('output');
            file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✅ Export complete → {$path}");

            if (!empty($failLog)) {
                $failPath = $exportDir . 'training_export_errors.json';
                file_put_contents($failPath, json_encode($failLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->warn("⚠️  Error log → {$failPath}");
            }
        } else {
            $this->warn('DRY RUN — output not written.');
        }

        $processed = count($rawRecords) - $totalFailed;
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed',         count($rawRecords)],
                ['Training records extracted', $totalParsed],
                ['Failed batches (teachers)',  $totalFailed],
                ['Avg trainings / teacher',    $processed > 0 ? round($totalParsed / $processed, 1) : 0],
            ]
        );

        return $totalFailed > 0 ? 1 : 0;
    }

    // ────────────────────────────────────────────────
    // Source Loaders
    // ────────────────────────────────────────────────

    private function loadSourceRecords(): array
    {
        return $this->option('source') === 'db'
            ? $this->loadFromDb()
            : $this->loadFromJson();
    }

    private function loadFromJson(): array
    {
        $filename = $this->option('json-file');
        // Support both storage/app/public/old_teacher.json and exports/ subfolder
        $path = file_exists(storage_path('app/public/' . $filename))
            ? storage_path('app/public/' . $filename)
            : storage_path('app/public/exports/' . $filename);

        if (!file_exists($path)) {
            $this->error("JSON file not found: {$path}");
            return [];
        }

        $raw = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return [];
        }

        $data = [];

        // Support multiple formats:
        //   A) PHPMyAdmin dump: [{type:header}, {type:database}, {type:table, data:[...]}]
        //   B) Plain array of objects with employeeID
        //   C) Wrapped object: {"data": [...]}
        if (isset($raw['data']) && is_array($raw['data'])) {
            $data = $raw['data'];
        } else {
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                if (isset($item['type']) && $item['type'] === 'table') {
                    $data = $item['data'] ?? [];
                    break;
                }
                if (isset($item['employeeID'])) {
                    $data[] = $item;
                }
            }
        }

        // Filter out blank trainingExperience
        $data = array_values(array_filter(
            $data,
            fn($r) => !empty(trim(strip_tags($r['trainingExperience'] ?? '')))
        ));

        $this->info("Loaded " . count($data) . " records from JSON ({$path}).");
        return $data;
    }

    private function loadFromDb(): array
    {
        // Adjust table/column to match your old DB schema
        $rows = DB::connection('old_db')
            ->table('teacher')
            ->whereNotNull('trainingExperience')
            ->where('trainingExperience', '!=', '')
            ->select('employeeID', 'trainingExperience')
            ->get();

        $this->info("Loaded " . $rows->count() . " records from old DB.");
        return $rows->map(fn($r) => (array) $r)->toArray();
    }

    private function loadExistingOutput(): array
    {
        $path = storage_path('app/public/exports/' . $this->option('output'));
        if (!file_exists($path)) return [];
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    // ────────────────────────────────────────────────
    // AI Provider Resolution
    // ────────────────────────────────────────────────

    private function resolveAiProvider(): void
    {
        $option = strtolower(trim($this->option('provider')));

        if ($option !== 'auto') {
            if ($option === 'heuristic') {
                $this->aiProvider = 'heuristic';
                $this->info('AI provider: Heuristic (Regex-based)');
                return;
            }
            if ($option === 'internal') {
                $this->aiProvider = 'internal';
                $this->info('AI provider: Internal (Antigravity Agent)');
                return;
            }

            // Explicit provider requested — validate key exists
            $keyMap = [
                'anthropic'  => env('ANTHROPIC_API_KEY'),
                'groq'       => env('GROQ_API_KEY'),
                'gemini'     => env('GEMINI_API_KEY'),
                'openrouter' => env('OPENROUTER_API_KEY'),
                'deepseek'   => env('DEEPSEEK_API_KEY'),
            ];
            if (empty($keyMap[$option] ?? '')) {
                $this->warn("⚠️  --provider={$option} set but key not found in .env — trying auto-detect.");
            } else {
                $this->aiProvider = $option;
                $this->info("AI provider: {$option} (explicit)");
                return;
            }
        }

        // Auto-detect: prefer DeepSeek/OpenRouter first if key exists, then others
        $priority = ['deepseek', 'openrouter', 'gemini', 'groq', 'anthropic'];
        foreach ($priority as $provider) {
            $key = match($provider) {
                'deepseek'  => env('DEEPSEEK_API_KEY'),
                'openrouter'=> env('OPENROUTER_API_KEY'),
                'gemini'    => env('GEMINI_API_KEY'),
                'groq'      => env('GROQ_API_KEY'),
                'anthropic' => env('ANTHROPIC_API_KEY'),
            };
            if (!empty($key)) {
                $this->aiProvider = $provider;
                $this->info("AI provider: {$provider} (auto-detected) | model: " . self::MODELS[$provider]);
                return;
            }
        }

        throw new \RuntimeException(
            'No AI API key found. Set at least one of: DEEPSEEK_API_KEY, OPENROUTER_API_KEY, GEMINI_API_KEY, GROQ_API_KEY, ANTHROPIC_API_KEY in .env'
        );
    }

    // ────────────────────────────────────────────────
    // AI Parser — unified entry point
    // ────────────────────────────────────────────────

    private function parseWithClaude(array $batch): array
    {
        return match($this->aiProvider) {
            'heuristic'  => $this->parseWithHeuristics($batch),
            'deepseek'   => $this->callDeepSeek($batch),
            'openrouter' => $this->callOpenRouter($batch),
            'groq'       => $this->callGroq($batch),
            'gemini'     => $this->callGemini($batch),
            default      => $this->callAnthropic($batch),
        };
    }

    private function callDeepSeek(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization'     => 'Bearer ' . env('DEEPSEEK_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->post('https://api.openmodel.ai/v1/messages', [
                'model'      => self::MODELS['deepseek'],
                'max_tokens' => 4096,
                'thinking'   => ['type' => 'disabled'],
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenModel API {$response->status()}: " . $response->body());
        }

        $content = '';
        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'] ?? '';
            }
        }
        return $this->parseClaudeResponse($content, $batch);
    }

    private function callOpenRouter(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);
        $model  = self::MODELS['openrouter'];

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => env('APP_URL', 'http://localhost:8000'),
                'X-Title'       => env('APP_NAME', 'Faculty | Daffodil International University'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'       => $model,
                'temperature' => 0,
                'max_tokens'  => 1500,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenRouter API {$response->status()}: " . $response->body());
        }

        $content = $response->json('choices.0.message.content', '');
        return $this->parseClaudeResponse($content, $batch);
    }

    // ── Anthropic ──

    private function callAnthropic(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);

        $response = Http::timeout(90)
            ->withHeaders([
                'x-api-key'         => env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => self::MODELS['anthropic'],
                'max_tokens' => 4096,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Anthropic API {$response->status()}: " . $response->body());
        }

        return $this->parseClaudeResponse(
            $response->json('content.0.text', ''),
            $batch
        );
    }

    // ── Groq ──

    private function callGroq(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => self::MODELS['groq'],
                'temperature' => 0,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a structured data extraction assistant. Always respond with valid JSON only — no explanation, no markdown.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                // Ask Groq for JSON mode
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Groq API {$response->status()}: " . $response->body());
        }

        $content = $response->json('choices.0.message.content', '');
        return $this->parseClaudeResponse($content, $batch);
    }

    // ── Gemini ──

    private function callGemini(array $batch): array
    {
        $prompt   = $this->buildPrompt($batch);
        $model    = self::MODELS['gemini'];
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent"
            . '?key=' . env('GEMINI_API_KEY');

        $response = Http::timeout(90)
            ->post($endpoint, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0,
                    'responseMimeType' => 'application/json',  // force JSON output
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Gemini API {$response->status()}: " . $response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text', '');
        return $this->parseClaudeResponse($content, $batch);
    }

    private function buildPrompt(array $batch): string
    {
        $teacherBlocks = '';
        foreach ($batch as $record) {
            $empId        = htmlspecialchars((string)$record['employeeID'], ENT_XML1);
            $htmlRaw      = $record['trainingExperience'] ?? '';
            $cleanedText  = $this->cleanHtmlForPrompt($htmlRaw);
            $teacherBlocks .= "\n<teacher employeeID=\"{$empId}\">\n{$cleanedText}\n</teacher>\n";
        }

        return <<<PROMPT
You are a structured data extraction assistant. Parse each teacher's training/workshop/seminar/course list from the HTML below.

Return ONLY a valid JSON object — no explanation, no markdown fences.

## Output format:
{
  "EMPLOYEE_ID": [
    {
      "title": "...",
      "organization": "...",
      "category": "...",
      "duration_days": null,
      "completion_date": "YYYY-MM-DD or null",
      "year": 2024,
      "country": "Bangladesh",
      "is_online": false,
      "description": null
    }
  ]
}

## Field rules:
- **title** (required): name of the training/workshop/seminar — never null
- **organization**: institute or organizer (e.g. "DIU", "HRDI", "ICT Division")
- **category**: exactly one of: Workshop | Seminar | Training | Webinar | Course | Conference | Internship | Other
  - Workshop → "workshop" in text
  - Seminar → "seminar" in text
  - Webinar → "webinar" / "online session" in text
  - Conference → "conference" / "congress" / "symposium" in text
  - Internship → "intern" / "industrial training"
  - Course → formal enrollment-based course
  - Training → default for "training program", "orientation", "faculty development" etc.
  - Other → anything else
- **duration_days**: integer (convert: "2 weeks"→14, "3 months"→90, "1 year"→365); null if unknown
- **completion_date**: full date as YYYY-MM-DD only if day+month+year all known; else null
- **year**: 4-digit integer if any year mentioned; else null
- **country**: country name string if mentioned; default "Bangladesh" for DIU/local events; null if truly unknown
- **is_online**: true only if explicitly "webinar", "online", "virtual"
- **description**: any meaningful extra detail not captured above; null if nothing extra

## Important:
- Each <li> = one separate record (do NOT merge)
- If a <li> has two events, split into two records
- Ignore <p>&nbsp;</p> and empty items
- Strip all HTML tags from values
- Include ALL employeeIDs in output even if empty array

{$teacherBlocks}
PROMPT;
    }

    private function parseClaudeResponse(string $content, array $batch): array
    {
        // Strip markdown fences if present
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        // Fallback: try to extract JSON object from within larger text
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Could not parse Claude response as JSON: ' . substr($content, 0, 400)
            );
        }

        $result = [];

        foreach ($decoded as $employeeId => $rawTrainings) {
            if (!is_array($rawTrainings)) continue;

            $result[(string)$employeeId] = [];
            $sortOrder = 1;

            foreach ($rawTrainings as $tr) {
                $title = trim($tr['title'] ?? '');
                if ($title === '') continue;

                $result[(string)$employeeId][] = [
                    'title'           => $title,
                    'organization'    => $this->cleanText($tr['organization'] ?? null),
                    'category'        => $this->validateCategory($tr['category'] ?? null),
                    'duration_days'   => $this->parseDurationDays($tr['duration_days'] ?? null),
                    'completion_date' => $this->validateDate($tr['completion_date'] ?? null),
                    'year'            => $this->validateYear($tr['year'] ?? null),
                    'country_id'      => $this->resolveCountry($tr['country'] ?? null),
                    'is_online'       => (bool) ($tr['is_online'] ?? false),
                    'description'     => $this->cleanText($tr['description'] ?? null),
                    'sort_order'      => $sortOrder++,
                    '_raw_country'    => $tr['country'] ?? null, // stripped at import
                ];
            }
        }

        // Ensure every employee in the batch appears in the result
        foreach ($batch as $record) {
            $empId = (string)$record['employeeID'];
            if (!isset($result[$empId])) {
                $result[$empId] = [];
            }
        }

        return $result;
    }

    // ────────────────────────────────────────────────
    // Lookup Tables
    // ────────────────────────────────────────────────

    private function buildCountryMap(): void
    {
        $countries = DB::connection('mysql')
            ->table('countries')
            ->get(['id', 'name', 'code', 'slug']);

        foreach ($countries as $c) {
            $this->countryMap[strtolower(trim($c->name))] = (int) $c->id;
            if (!empty($c->code)) $this->countryMap[strtolower(trim($c->code))] = (int) $c->id;
            if (!empty($c->slug)) $this->countryMap[strtolower(trim($c->slug))] = (int) $c->id;
        }

        $this->info('Country map: ' . count($this->countryMap) . ' entries');
    }

    private function buildEmployeeIdMap(): void
    {
        $path = storage_path('app/public/exports/teachers_export.json');
        if (!file_exists($path)) {
            $this->warn('teachers_export.json not found — _old_teacher_id will be null.');
            return;
        }

        $teachers = json_decode(file_get_contents($path), true);
        foreach ($teachers as $t) {
            $empId = $t['teacher_profile']['employee_id'] ?? null;
            $oldId = $t['teacher_profile']['_old_teacher_id'] ?? null;
            if ($empId && $oldId) {
                $this->employeeToOldId[(string)$empId] = (int)$oldId;
            }
        }

        $this->info('Employee→OldID map: ' . count($this->employeeToOldId) . ' entries');
    }

    // ────────────────────────────────────────────────
    // Value Validators / Cleaners
    // ────────────────────────────────────────────────

    private function resolveCountry(?string $raw): int
    {
        if (empty($raw)) return self::DEFAULT_COUNTRY_ID;

        $key = strtolower(trim($raw));
        if (isset($this->countryMap[$key])) return $this->countryMap[$key];

        $aliases = [
            'bangladesh'  => 18,  'bd'           => 18,
            'usa'         => 233, 'united states' => 233, 'america' => 233,
            'uk'          => 232, 'united kingdom' => 232,
            'india'       => 101,
            'malaysia'    => 133,
            'singapore'   => 197,
            'australia'   => 13,
            'canada'      => 38,
            'germany'     => 82,
            'japan'       => 109,
            'china'       => 44,
            'south korea' => 116, 'korea' => 116,
            'thailand'    => 219,
            'nepal'       => 150,
            'sri lanka'   => 209, 'srilanka' => 209,
        ];
        if (isset($aliases[$key])) return $aliases[$key];

        foreach ($this->countryMap as $mapKey => $id) {
            if (str_contains($mapKey, $key) || str_contains($key, $mapKey)) return $id;
        }

        return self::DEFAULT_COUNTRY_ID;
    }

    private function validateCategory(?string $cat): ?string
    {
        $valid = ['Workshop', 'Seminar', 'Training', 'Webinar', 'Course', 'Conference', 'Internship', 'Other'];
        return in_array($cat, $valid, true) ? $cat : null;
    }

    private function validateDate(?string $date): ?string
    {
        if (empty($date)) return null;
        try {
            $dt   = new \DateTime($date);
            $year = (int) $dt->format('Y');
            if ($year < 1970 || $year > (int) date('Y') + 1) return null;
            return $dt->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function validateYear($year): ?int
    {
        $y = (int) $year;
        return ($y >= 1970 && $y <= (int) date('Y') + 1) ? $y : null;
    }

    private function parseDurationDays($raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        // Claude may return int or numeric string
        if (is_numeric($raw)) {
            $n = (int) $raw;
            return $n > 0 ? $n : null;
        }
        $n = (int) preg_replace('/[^0-9]/', '', (string) $raw);
        return $n > 0 ? $n : null;
    }

    private function handleInternalBatch(array $batch, array $exportData): void
    {
        $queuePath   = storage_path('app/public/internal_agent_queue.json');
        $resultsPath = storage_path('app/public/internal_agent_results.json');
        $outputPath  = storage_path('app/public/exports/' . $this->option('output'));

        // 1. Check if results exist from previous turn
        if (file_exists($resultsPath)) {
            $results = json_decode(file_get_contents($resultsPath), true);
            if (is_array($results)) {
                $this->info('Merging results from internal_agent_results.json...');
                
                $mergedCount = 0;
                foreach ($results as $item) {
                    $exportData[] = $item;
                    $mergedCount++;
                }

                file_put_contents($outputPath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("✅ Merged {$mergedCount} records into {$outputPath}");
                
                // Cleanup
                @unlink($resultsPath);
                @unlink($queuePath);

                $this->info('Run the command again to process the next batch.');
                return;
            }
        }

        // 2. If results don't exist, check if queue exists
        if (file_exists($queuePath)) {
            $this->warn('Queue file already exists. Please ask the Internal Agent to process "internal_agent_queue.json" and save to "internal_agent_results.json".');
            return;
        }

        // 3. Stage the current batch
        $this->info('Staging batch for Internal Agent...');
        file_put_contents($queuePath, json_encode($batch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->warn("⚠️  Batch of " . count($batch) . " teachers staged in: {$queuePath}");
        $this->info('Please ask the Internal Agent (me) to process this file.');
    }

    private function parseWithHeuristics(array $batch): array
    {
        $result = [];
        foreach ($batch as $record) {
            $empId = (string) $record['employeeID'];
            $html  = $record['trainingExperience'] ?? '';
            $result[$empId] = [];

            // 1. Split by common delimiters (li, p, br, or double newline)
            $parts = preg_split('/<(?:li|p|br\s*\/?)>|\r\n\r\n|\n\n/i', $html, -1, PREG_SPLIT_NO_EMPTY);
            
            $sortOrder = 1;
            foreach ($parts as $part) {
                $text = strip_tags($part);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, "UTF-8");
                $text = trim(preg_replace('/\s+/', ' ', $text));
                $text = trim($text, " \t\n\r\0\x0B\xc2\xa0"); // also trim non-breaking spaces

                if (empty($text) || strlen($text) < 5) continue;

                // Simple skip for headers like "Training Experience:"
                if (preg_match('/^(training|workshop|seminar|experience|industrial)\s+(experience|list|training):?$/i', $text)) continue;

                $data = [
                    'title'           => $text, // default
                    'organization'    => null,
                    'category'        => 'Training', // default
                    'duration_days'   => null,
                    'completion_date' => null,
                    'year'            => null,
                    'country_id'      => self::DEFAULT_COUNTRY_ID,
                    'is_online'       => false,
                    'description'     => null,
                    'sort_order'      => $sortOrder++,
                ];

                // 2. Extract Year
                if (preg_match('/\b(19|20)\d{2}\b/', $text, $m)) {
                    $data['year'] = (int) $m[0];
                }

                // 3. Extract Date (YYYY-MM-DD or DD Month YYYY)
                if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
                    $data['completion_date'] = $m[1];
                } elseif (preg_match('/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*,?\s*\d{4})\b/i', $text, $m)) {
                    $data['completion_date'] = $this->validateDate($m[1]);
                }

                // 4. Extract Duration
                if (preg_match('/(\d+)\s*(day|week|month|year)s?/i', $text, $m)) {
                    $val = (int) $m[1];
                    $unit = strtolower($m[2]);
                    $data['duration_days'] = match($unit) {
                        'day'   => $val,
                        'week'  => $val * 7,
                        'month' => $val * 30,
                        'year'  => $val * 365,
                    };
                }

                // 5. Category Mapping
                if (preg_match('/workshop/i',   $text)) $data['category'] = 'Workshop';
                elseif (preg_match('/seminar/i',  $text)) $data['category'] = 'Seminar';
                elseif (preg_match('/webinar|online session/i', $text)) $data['category'] = 'Webinar';
                elseif (preg_match('/conference|congress|symposium/i', $text)) $data['category'] = 'Conference';
                elseif (preg_match('/intern|industrial training/i', $text)) $data['category'] = 'Internship';
                elseif (preg_match('/course/i',   $text)) $data['category'] = 'Course';

                // 6. Online detection
                if (preg_match('/online|virtual|webinar|zoom/i', $text)) {
                    $data['is_online'] = true;
                }

                // 6.5 Country detection
                $targetCountries = ['UK', 'USA', 'Japan', 'India', 'Malaysia', 'Singapore', 'Australia', 'Canada', 'Germany', 'Thailand', 'Nepal', 'Sri Lanka'];
                foreach ($targetCountries as $c) {
                    if (preg_match("/\b$c\b/i", $text)) {
                        $data['country_id'] = $this->resolveCountry($c);
                        break;
                    }
                }

                // 7. Organization Heuristic (split by "at", "from", "by", "organized by")
                if (preg_match('/(.+?)\s+(?:at|from|by|organized by|offered by)\s+(.+)/i', $text, $m)) {
                    $data['title'] = trim($m[1], " ,;");
                    $data['organization'] = trim($m[2], " ,;.");
                    
                    // Cleanup title if it still has trailing date garbage
                    $data['title'] = preg_replace('/\s*\(?(?:19|20)\d{2}\)?$/', '', $data['title']);
                }

                $result[$empId][] = $data;
            }
        }
        return $result;
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function cleanHtmlForPrompt(string $html): string
    {
        if (empty(trim($html))) return '';
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        // Explicitly remove HTML comments to save tokens
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>', '</ul>', '</ol>'], "\n", $html);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $lines = explode("\n", $cleaned);
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B\xc2\xa0-•*");
            if (!empty($line)) {
                $result[] = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            }
        }
        return implode("\n", $result);
    }
}
