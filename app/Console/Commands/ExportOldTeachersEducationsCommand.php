<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ExportOldTeachersEducationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'export:old-teachers-educations
                            {--source=db                                    : Data source: "db" (old_db connection) or "json"}
                            {--json-file=old_teacher.json                   : Source JSON filename (inside storage/app/public/)}
                            {--output=teachers_educations_export.json        : Output filename (inside storage/app/public/exports/)}
                            {--limit=0                                       : Limit number of teachers processed (0 = all)}
                            {--batch-size=5                                  : Teachers per AI API call}
                            {--provider=auto                                 : AI provider: auto|openrouter|vertex|gemini|groq|anthropic|deepseek|heuristic}
                            {--dry-run                                       : Parse but do not write output file}
                            {--overwrite                                     : Overwrite the output file and re-process all records}
                            {--employee=                                     : Process only a specific employee ID}';

    protected $description = 'Export and AI-parse teacher academic qualifications and educational history from old database/JSON';

    const MODELS = [
        'anthropic'  => 'claude-sonnet-4-20250514',
        'groq'       => 'llama-3.3-70b-versatile',
        'gemini'     => 'gemini-2.5-flash',
        'vertex'     => 'gemini-2.5-flash',
        'openrouter' => 'google/gemini-2.5-flash',
        'deepseek'   => 'deepseek-v4-flash',
    ];

    protected string $aiProvider = 'openrouter';
    protected array $employeeToOldId = [];
    protected float $totalCost = 0.0;

    public function handle(): int
    {
        $this->resolveAiProvider();
        $this->buildEmployeeIdMap();

        $rawRecords = $this->loadSourceRecords();
        if (empty($rawRecords)) {
            $this->error('No source records found.');
            return Command::FAILURE;
        }

        // Apply limit before skip (standard logic requested by user)
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $rawRecords = array_slice($rawRecords, 0, $limit);
        }

        $this->info('Total teachers to process (before skip): ' . count($rawRecords));

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

        if (empty($rawRecords)) {
            $this->info("✅ All selected teachers have already been processed. Nothing to do.");
            return Command::SUCCESS;
        }

        $this->info('Actual teachers to send to AI: ' . count($rawRecords));

        $batchSize = max(1, (int) $this->option('batch-size'));
        $batches = array_chunk($rawRecords, $batchSize);
        $exportData = $existingData;
        $totalParsed = 0;
        $totalFailed = 0;
        $failLog = [];

        $bar = $this->output->createProgressBar(count($rawRecords));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($batches as $batchIndex => $batch) {
            $bar->setMessage('Batch ' . ($batchIndex + 1) . '/' . count($batches) . ' — calling parser...');

            try {
                $parsed = $this->parseWithAi($batch);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Parser API error on batch {$batchIndex}: " . $e->getMessage());
                foreach ($batch as $record) {
                    $failLog[] = [
                        'employeeID' => $record['employeeID'],
                        'reason'     => 'parser_api_error',
                        'error'      => $e->getMessage(),
                    ];
                    $totalFailed++;
                }
                $bar->advance(count($batch));
                continue;
            }

            foreach ($parsed as $employeeId => $educations) {
                $oldTeacherId = $this->employeeToOldId[(string)$employeeId] ?? null;
                $exportData[] = [
                    '_employee_id'     => (string) $employeeId,
                    '_old_teacher_id'  => $oldTeacherId,
                    'educations'       => $educations,
                ];
                $totalParsed += count($educations);
            }

            $bar->advance(count($batch));

            // Save progress incrementally
            if (!$this->option('dry-run')) {
                $exportDir = storage_path('app/public/exports/');
                if (!is_dir($exportDir)) {
                    mkdir($exportDir, 0755, true);
                }
                $outputPath = $exportDir . $this->option('output');
                file_put_contents($outputPath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            if ($batchIndex < count($batches) - 1 && $this->aiProvider !== 'heuristic') {
                usleep(300_000);
            }
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine();

        $exportDir = storage_path('app/public/exports/');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        if (!$this->option('dry-run')) {
            $path = $exportDir . $this->option('output');
            file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✅ Export complete → {$path}");

            if (!empty($failLog)) {
                $failPath = $exportDir . 'teachers_educations_export_errors.json';
                file_put_contents($failPath, json_encode($failLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->warn("⚠️  Error log → {$failPath}");
            }
        } else {
            $this->warn('DRY RUN — output not written.');
        }

        $processed = count($rawRecords) - $totalFailed;

        if ($this->totalCost > 0) {
            $logPath = storage_path('logs/vertex_ai_cost.log');
            $logMessage = sprintf(
                "[%s] === TOTAL EXECUTION COST (%s) === Total Cost: $%f\n\n",
                date('Y-m-d H:i:s'),
                class_basename($this),
                $this->totalCost
            );
            file_put_contents($logPath, $logMessage, FILE_APPEND);
        }

        $metrics = [
            ['Teachers processed',                 count($rawRecords)],
            ['Educations extracted',               $totalParsed],
            ['Failed batches (teachers)',          $totalFailed],
            ['Avg educations / teacher',           $processed > 0 ? round($totalParsed / $processed, 1) : 0],
        ];

        if ($this->totalCost > 0) {
            $metrics[] = ['Total Estimated Cost (Vertex AI)', '$' . number_format($this->totalCost, 6)];
        }

        $this->table(['Metric', 'Count'], $metrics);

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // ── Data Loading ──────────────────────────────────────────────────────────

    private function loadSourceRecords(): array
    {
        $data = $this->option('source') === 'db'
            ? $this->loadFromDb()
            : $this->loadFromJson();

        if (env('AI_PROCESS_ONLY_ASSIGNED_DEPARTMENT', false) && !$this->option('employee')) {
            $assignedEmployeeIds = \App\Models\Teacher::where(function ($query) {
                $query->whereNotNull('department_id')->orWhereHas('departments');
            })
            ->whereHas('user', fn($q) => $q->where('is_active', 1))
            ->pluck('employee_id')
            ->filter()
            ->toArray();

            $data = array_values(array_filter($data, function ($r) use ($assignedEmployeeIds) {
                return in_array((string)($r['employeeID'] ?? ''), $assignedEmployeeIds, true);
            }));

            $this->info("Filter enabled: Only processing active teachers with an assigned department. Remaining: " . count($data) . " records.");
        }

        return $data;
    }

    private function loadFromDb(): array
    {
        $query = DB::connection('old_db')
            ->table('teacher')
            ->whereNotNull('academicQualification')
            ->where('academicQualification', '!=', '');

        if ($employeeId = $this->option('employee')) {
            $query->where('employeeID', $employeeId);
        }

        $rows = $query->select('employeeID', 'academicQualification')->get();

        $this->info("Loaded " . $rows->count() . " records from old DB.");
        return $rows->map(fn($r) => (array) $r)->toArray();
    }

    private function loadFromJson(): array
    {
        $filename = $this->option('json-file');
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
        if (isset($raw['data']) && is_array($raw['data'])) {
            $data = $raw['data'];
        } else {
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                if (isset($item['employeeID'])) {
                    $data[] = $item;
                }
            }
        }

        $data = array_values(array_filter(
            $data,
            fn($r) => !empty(trim(strip_tags($r['academicQualification'] ?? '')))
        ));

        if ($employeeId = $this->option('employee')) {
            $data = array_values(array_filter($data, fn($r) => (string)($r['employeeID'] ?? '') === (string)$employeeId));
        }

        $this->info("Loaded " . count($data) . " records from JSON.");
        return $data;
    }

    private function loadExistingOutput(): array
    {
        $path = storage_path('app/public/exports/' . $this->option('output'));
        if (!file_exists($path)) return [];
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    // ── AI Provider Resolution ────────────────────────────────────────────────

    private function resolveAiProvider(): void
    {
        $option = strtolower(trim($this->option('provider')));

        if ($option !== 'auto') {
            if ($option === 'heuristic') {
                $this->aiProvider = 'heuristic';
                $this->info('AI provider: Heuristic (Regex-based)');
                return;
            }

            $keyMap = [
                'anthropic'  => env('ANTHROPIC_API_KEY'),
                'groq'       => env('GROQ_API_KEY'),
                'gemini'     => env('GEMINI_API_KEY'),
                'vertex'     => env('VERTEX_AI_KEY_PATH'),
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

        $priority = ['vertex', 'gemini', 'deepseek', 'openrouter', 'groq', 'anthropic'];
        foreach ($priority as $provider) {
            $key = match($provider) {
                'deepseek'   => env('DEEPSEEK_API_KEY'),
                'openrouter' => env('OPENROUTER_API_KEY'),
                'vertex'     => env('VERTEX_AI_KEY_PATH'),
                'gemini'     => env('GEMINI_API_KEY'),
                'groq'       => env('GROQ_API_KEY'),
                'anthropic'  => env('ANTHROPIC_API_KEY'),
            };
            if (!empty($key)) {
                $this->aiProvider = $provider;
                $this->info("AI provider: {$provider} (auto-detected) | model: " . self::MODELS[$provider]);
                return;
            }
        }

        $this->aiProvider = 'heuristic';
        $this->warn('⚠️ No AI API key found. Falling back to Heuristic (Regex-based).');
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
    }

    // ── AI Dispatch ───────────────────────────────────────────────────────────

    private function parseWithAi(array $batch): array
    {
        return match($this->aiProvider) {
            'heuristic'  => $this->parseWithHeuristics($batch),
            'deepseek'   => $this->callDeepSeek($batch),
            'openrouter' => $this->callOpenRouter($batch),
            'groq'       => $this->callGroq($batch),
            'gemini'     => $this->callGemini($batch),
            'vertex'     => $this->callVertex($batch),
            default      => $this->callAnthropic($batch),
        };
    }

    // ── AI API Calls ──────────────────────────────────────────────────────────

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
        return $this->parseAiResponse($content, $batch);
    }

    private function callOpenRouter(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => env('APP_URL', 'http://localhost:8000'),
                'X-Title'       => env('APP_NAME', 'Faculty | Daffodil International University'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'           => self::MODELS['openrouter'],
                'temperature'     => 0,
                'max_tokens'      => 2000,
                'messages'        => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenRouter API {$response->status()}: " . $response->body());
        }

        return $this->parseAiResponse($response->json('choices.0.message.content', ''), $batch);
    }

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

        return $this->parseAiResponse($response->json('content.0.text', ''), $batch);
    }

    private function callGroq(array $batch): array
    {
        $prompt = $this->buildPrompt($batch);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'           => self::MODELS['groq'],
                'temperature'     => 0,
                'messages'        => [
                    ['role' => 'system', 'content' => 'You are a structured data extraction assistant. Always respond with valid JSON only — no explanation, no markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Groq API {$response->status()}: " . $response->body());
        }

        return $this->parseAiResponse($response->json('choices.0.message.content', ''), $batch);
    }

    private function callGemini(array $batch): array
    {
        $prompt   = $this->buildPrompt($batch);
        $model    = self::MODELS['gemini'];
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent"
            . '?key=' . env('GEMINI_API_KEY');

        $response = Http::timeout(90)
            ->post($endpoint, [
                'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Gemini API {$response->status()}: " . $response->body());
        }

        return $this->parseAiResponse($response->json('candidates.0.content.parts.0.text', ''), $batch);
    }

    private function callVertex(array $batch): array
    {
        $prompt          = $this->buildPrompt($batch);
        $model           = self::MODELS['vertex'];
        $vertexAIService = resolve(\App\Services\VertexAIService::class);
        $result          = $vertexAIService->generateContent($model, $prompt, 0.0, 'application/json');

        $this->totalCost += $result['cost'];

        $this->info(sprintf(
            "   [Vertex AI] Tokens used - Input: %d, Output: %d | Est. Cost: $%f",
            $result['input_tokens'],
            $result['output_tokens'],
            $result['cost']
        ));

        return $this->parseAiResponse($result['content'], $batch);
    }

    // ── Prompt Building ───────────────────────────────────────────────────────

    private function buildPrompt(array $batch): string
    {
        $teacherBlocks = '';
        foreach ($batch as $record) {
            $empId       = htmlspecialchars((string)$record['employeeID'], ENT_XML1);
            $htmlRaw     = $record['academicQualification'] ?? '';
            $cleanedText = $this->cleanHtmlForPrompt($htmlRaw);
            $teacherBlocks .= "\n<teacher employeeID=\"{$empId}\">\n{$cleanedText}\n</teacher>\n";
        }

        return <<<PROMPT
You are a structured data extraction assistant. Parse each teacher's academic qualifications and educational history from the HTML/text below.

Return ONLY a valid JSON object — no explanation, no markdown fences.

## Output format:
{
  "EMPLOYEE_ID": [
    {
      "degree_name": "...",
      "major": "...",
      "institution": "...",
      "passing_year": 1995,
      "result_type": "...",
      "cgpa": 3.87,
      "scale": 4.0,
      "marks": null,
      "grade": "...",
      "duration": "...",
      "country": "..."
    }
  ]
}

## Field rules:
- **degree_name** (required): The clean name of the degree (e.g. "Doctor of Philosophy", "Master of Science", "Bachelor of Science", "Master of Business Administration", "Secondary School Certificate", "Higher Secondary Certificate"). If it is a diploma, write "Diploma in [Major]" or "Diploma". Never null.
- **major**: The field of study, major, or concentration (e.g. "Mathematics", "Pure Mathematics", "Management Information System", "Economics"). null if not mentioned.
- **institution**: The clean name of the university, institute, board, or school (e.g. "University of Dhaka", "Jahangirnagar University", "Asian University of Bangladesh", "Lancaster University"). Never null.
- **passing_year**: 4-digit integer for passing year (e.g. 1995, 2003); null if unknown.
- **result_type**: Must be exactly one of: CGPA | GPA | Percentage | Grade | Division | Pass/Fail | Not Applicable
  - "CGPA" → if CGPA is mentioned (e.g. "CGPA 3.875")
  - "GPA" → if GPA is mentioned
  - "Percentage" → if percentage is mentioned (e.g. "85%")
  - "Grade" → if letter grade is mentioned (e.g. "A+", "A")
  - "Division" → if division/class is mentioned (e.g. "1st Class", "2nd Division")
  - "Pass/Fail" → if pass/fail is mentioned
  - "Not Applicable" → if no result is mentioned or ongoing (e.g. Ph.D researcher, in-progress)
- **cgpa**: Decimal value if result_type is CGPA or GPA (e.g. 3.87, 4.0). null if not applicable.
- **scale**: Decimal value representing the maximum possible score if CGPA or GPA is used (e.g. 4.0, 5.0). null if not mentioned.
- **marks**: Decimal value if percentage is mentioned (e.g. 85.0). null if not applicable.
- **grade**: String value for grade or division (e.g. "A+", "1st Class", "1st in order of merit"). If result_type is Division, write the division name here. If result_type is Grade, write the letter grade here.
- **duration**: Duration of study (e.g. "4 years", "1 year", "2 years") if mentioned. null if not.
- **country**: The country where the institution is located (e.g. "Bangladesh", "England", "United Kingdom", "USA"). If not mentioned or cannot be inferred, default to "Bangladesh".

## Important rules:
- Parse EACH distinct degree separately.
- Strip all HTML tags from extracted values.
- Ignore incomplete entries or non-academic info.
- Include ALL employeeIDs in the output even if their list is empty ([]).

{$teacherBlocks}
PROMPT;
    }

    // ── Response Parsing ──────────────────────────────────────────────────────

    private function parseAiResponse(string $content, array $batch): array
    {
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Could not parse AI response as JSON: ' . substr($content, 0, 400)
            );
        }

        $result = [];

        foreach ($decoded as $employeeId => $rawEducations) {
            if (!is_array($rawEducations)) continue;

            $result[(string)$employeeId] = [];

            foreach ($rawEducations as $edu) {
                $degree = trim($edu['degree_name'] ?? '');
                $inst = trim($edu['institution'] ?? '');
                if ($degree === '' || $inst === '') continue;

                $resType = trim($edu['result_type'] ?? 'Not Applicable');
                $validTypes = ['CGPA', 'GPA', 'Percentage', 'Grade', 'Division', 'Pass/Fail', 'Not Applicable'];
                if (!in_array($resType, $validTypes, true)) {
                    $resType = 'Not Applicable';
                }

                $result[(string)$employeeId][] = [
                    'degree_name'  => $degree,
                    'major'        => $this->cleanText($edu['major'] ?? null),
                    'institution'  => $inst,
                    'passing_year' => $this->validateYear($edu['passing_year'] ?? null),
                    'result_type'  => $resType,
                    'cgpa'         => isset($edu['cgpa']) ? (float)$edu['cgpa'] : null,
                    'scale'        => isset($edu['scale']) ? (float)$edu['scale'] : null,
                    'marks'        => isset($edu['marks']) ? (float)$edu['marks'] : null,
                    'grade'        => $this->cleanText($edu['grade'] ?? null),
                    'duration'     => $this->cleanText($edu['duration'] ?? null),
                    'country'      => $this->cleanText($edu['country'] ?? 'Bangladesh'),
                ];
            }
        }

        // Ensure all batch items appear in output
        foreach ($batch as $record) {
            $empId = (string)$record['employeeID'];
            if (!isset($result[$empId])) {
                $result[$empId] = [];
            }
        }

        return $result;
    }

    // ── Heuristic Fallback ────────────────────────────────────────────────────

    private function parseWithHeuristics(array $batch): array
    {
        $result = [];
        foreach ($batch as $record) {
            $empId = (string) $record['employeeID'];
            $result[$empId] = $this->parseEducationsHeuristic($record['academicQualification'] ?? '');
        }
        return $result;
    }

    private function parseEducationsHeuristic(string $raw): array
    {
        if (empty(trim($raw))) return [];

        $raw     = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>', '</ul>', '</ol>'], "\n", $raw);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = explode("\n", $cleaned);
        $educations = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 4) continue;

            $degree = 'Degree';
            if (stripos($line, 'Ph.D') !== false || stripos($line, 'PhD') !== false) $degree = 'Doctor of Philosophy';
            elseif (stripos($line, 'M.Sc') !== false || stripos($line, 'MSc') !== false || stripos($line, 'Master of Science') !== false) $degree = 'Master of Science';
            elseif (stripos($line, 'MBA') !== false || stripos($line, 'Master of Business Administration') !== false) $degree = 'Master of Business Administration';
            elseif (stripos($line, 'B.Sc') !== false || stripos($line, 'BSc') !== false || stripos($line, 'Bachelor of Science') !== false) $degree = 'Bachelor of Science';
            elseif (stripos($line, 'BBA') !== false || stripos($line, 'Bachelor of Business Administration') !== false) $degree = 'Bachelor of Business Administration';
            elseif (stripos($line, 'H.S.C') !== false || stripos($line, 'HSC') !== false) $degree = 'Higher Secondary Certificate';
            elseif (stripos($line, 'S.S.C') !== false || stripos($line, 'SSC') !== false) $degree = 'Secondary School Certificate';

            $passingYear = null;
            if (preg_match('/\b(19|20)\d{2}\b/', $line, $matches)) {
                $passingYear = (int)$matches[0];
            }

            $educations[] = [
                'degree_name'  => $degree,
                'major'        => null,
                'institution'  => 'Unknown Institution',
                'passing_year' => $passingYear,
                'result_type'  => 'Not Applicable',
                'cgpa'         => null,
                'scale'        => null,
                'marks'        => null,
                'grade'        => null,
                'duration'     => null,
                'country'      => 'Bangladesh',
            ];
        }

        return $educations;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function validateYear($year): ?int
    {
        $y = (int) $year;
        return ($y >= 1950 && $y <= (int) date('Y') + 1) ? $y : null;
    }

    private function cleanHtmlForPrompt(string $html): string
    {
        if (empty(trim($html))) return '';
        $html    = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $html    = preg_replace('/<!--.*?-->/s', '', $html);
        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>', '</ul>', '</ol>'], "\n", $html);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines  = explode("\n", $cleaned);
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
