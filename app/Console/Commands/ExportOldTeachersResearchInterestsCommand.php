<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ExportOldTeachersResearchInterestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'export:old-teachers-research-interests
                            {--source=db                                        : Data source: "db" (old_db connection) or "json"}
                            {--json-file=old_teacher.json                       : Source JSON filename (inside storage/app/public/)}
                            {--output=teachers_research_interests_export.json   : Output filename (inside storage/app/public/exports/)}
                            {--limit=0                                           : Limit number of teachers processed (0 = all)}
                            {--batch-size=10                                     : Teachers per AI API call}
                            {--provider=auto                                     : AI provider: auto|openrouter|vertex|gemini|groq|anthropic|deepseek|heuristic}
                            {--dry-run                                           : Parse but do not write output file}
                            {--overwrite                                         : Re-process the selected teachers even if already in the output file (others are kept)}
                            {--employee=                                         : Process only a specific employee ID}';

    protected $description = 'Export and AI-parse teacher research interests (old teacher.currentResearch) from old database/JSON';

    const MODELS = [
        'anthropic'  => 'claude-sonnet-4-20250514',
        'groq'       => 'llama-3.3-70b-versatile',
        'gemini'     => 'gemini-2.5-flash',
        'vertex'     => 'gemini-2.5-flash',
        'openrouter' => 'google/gemini-2.5-flash',
        'deepseek'   => 'deepseek-v4-flash',
    ];

    /**
     * USD per 1M tokens [input, output], list prices as of 2026 — check the
     * provider's pricing page before relying on them. Gemini 2.5 thinking
     * tokens are billed as output and are counted as output here.
     */
    const PRICING = [
        'anthropic'  => [3.00, 15.00],
        'groq'       => [0.59, 0.79],
        'gemini'     => [0.30, 2.50],
        'vertex'     => [0.30, 2.50],
        'openrouter' => [0.30, 2.50],
        'deepseek'   => [0.27, 1.10],
    ];

    protected string $aiProvider = 'openrouter';
    protected array $employeeToOldId = [];
    protected float $totalCost = 0.0;
    protected int $totalInputTokens = 0;
    protected int $totalOutputTokens = 0;

    public function handle(): int
    {
        $this->resolveAiProvider();
        $this->buildEmployeeIdMap();

        $rawRecords = $this->loadSourceRecords();
        if (empty($rawRecords)) {
            $this->error('No source records found.');
            return Command::FAILURE;
        }

        $sourceTotal = count($rawRecords);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $rawRecords = array_slice($rawRecords, 0, $limit);
        }

        $this->info('Total teachers to process (before skip): ' . count($rawRecords));

        // Auto-resume: teachers already in the output file are not sent to AI again.
        // --overwrite re-processes the selected teachers (all, --limit or --employee)
        // and keeps every other teacher's existing result.
        $existingData = $this->loadExistingOutput();
        if ($this->option('overwrite')) {
            $redoIds = array_map(fn($r) => (string) $r['employeeID'], $rawRecords);
            $before = count($existingData);
            $existingData = array_values(array_filter(
                $existingData,
                fn($e) => !in_array((string) ($e['_employee_id'] ?? ''), $redoIds, true)
            ));
            if ($before > count($existingData)) {
                $this->info('♻️  --overwrite: re-processing ' . ($before - count($existingData)) . ' teachers already in the output file.');
            }
        } else {
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

            foreach ($parsed as $employeeId => $interests) {
                $oldTeacherId = $this->employeeToOldId[(string)$employeeId] ?? null;
                $exportData[] = [
                    '_employee_id'       => (string) $employeeId,
                    '_old_teacher_id'    => $oldTeacherId,
                    'research_interests' => $interests,
                ];
                $totalParsed += count($interests);
            }

            $bar->advance(count($batch));
            if ($this->aiProvider !== 'heuristic') {
                $bar->setMessage(sprintf('Batch %d/%d — cost so far: $%.4f', $batchIndex + 1, count($batches), $this->totalCost));
            }

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
                $failPath = $exportDir . 'teachers_research_interests_export_errors.json';
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
                "[%s] === TOTAL EXECUTION COST (%s, %s) === Input: %d tokens | Output: %d tokens | Total Cost: $%f\n\n",
                date('Y-m-d H:i:s'),
                class_basename($this),
                $this->aiProvider,
                $this->totalInputTokens,
                $this->totalOutputTokens,
                $this->totalCost
            );
            file_put_contents($logPath, $logMessage, FILE_APPEND);
        }

        $metrics = [
            ['Teachers processed',              count($rawRecords)],
            ['Research interests extracted',    $totalParsed],
            ['Failed batches (teachers)',       $totalFailed],
            ['Avg interests / teacher',         $processed > 0 ? round($totalParsed / $processed, 1) : 0],
        ];

        $this->table(['Metric', 'Count'], $metrics);

        if ($this->aiProvider !== 'heuristic' && $processed > 0) {
            [$inPrice, $outPrice] = self::PRICING[$this->aiProvider];
            $perTeacher = $this->totalCost / $processed;

            $this->table(['Cost', 'Value'], [
                ['Provider / model',                    $this->aiProvider . ' / ' . self::MODELS[$this->aiProvider]],
                ['Price per 1M tokens (in / out)',      sprintf('$%.2f / $%.2f', $inPrice, $outPrice)],
                ['Input tokens',                        number_format($this->totalInputTokens)],
                ['Output tokens (incl. thinking)',      number_format($this->totalOutputTokens)],
                ['Cost of this run',                    '$' . number_format($this->totalCost, 6)],
                ['Avg cost / teacher',                  '$' . number_format($perTeacher, 6)],
                ["Projected cost — all {$sourceTotal} teachers", '$' . number_format($perTeacher * $sourceTotal, 4)],
            ]);
        }

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
            ->whereNotNull('currentResearch')
            ->where('currentResearch', '!=', '');

        if ($employeeId = $this->option('employee')) {
            $query->where('employeeID', $employeeId);
        }

        $rows = $query->select('employeeID', 'currentResearch')->get();

        // Rows holding only empty markup (e.g. "<p><br></p>") carry nothing to parse
        $rows = $rows->filter(fn($r) => trim(strip_tags(html_entity_decode((string) $r->currentResearch))) !== '');

        $this->info("Loaded " . $rows->count() . " records from old DB.");
        return $rows->map(fn($r) => (array) $r)->values()->toArray();
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
            fn($r) => !empty(trim(strip_tags($r['currentResearch'] ?? '')))
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

        $priority = ['gemini','deepseek','openrouter','groq','anthropic','vertex'];
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

        $this->recordUsage($response->json('usage.input_tokens', 0), $response->json('usage.output_tokens', 0));

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

        $this->recordUsage($response->json('usage.prompt_tokens', 0), $response->json('usage.completion_tokens', 0));

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

        $this->recordUsage($response->json('usage.input_tokens', 0), $response->json('usage.output_tokens', 0));

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

        $this->recordUsage($response->json('usage.prompt_tokens', 0), $response->json('usage.completion_tokens', 0));

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

        $this->recordUsage(
            $response->json('usageMetadata.promptTokenCount', 0),
            $response->json('usageMetadata.candidatesTokenCount', 0) + $response->json('usageMetadata.thoughtsTokenCount', 0)
        );

        return $this->parseAiResponse($response->json('candidates.0.content.parts.0.text', ''), $batch);
    }

    private function callVertex(array $batch): array
    {
        $prompt          = $this->buildPrompt($batch);
        $model           = self::MODELS['vertex'];
        $vertexAIService = resolve(\App\Services\VertexAIService::class);
        $result          = $vertexAIService->generateContent($model, $prompt, 0.0, 'application/json');

        $this->recordUsage($result['input_tokens'], $result['output_tokens']);

        return $this->parseAiResponse($result['content'], $batch);
    }

    /**
     * Add one call's token usage to the running totals and price it.
     */
    private function recordUsage(int $inputTokens, int $outputTokens): void
    {
        [$inPrice, $outPrice] = self::PRICING[$this->aiProvider];

        $this->totalInputTokens  += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
        $this->totalCost         += ($inputTokens * $inPrice + $outputTokens * $outPrice) / 1_000_000;
    }

    // ── Prompt Building ───────────────────────────────────────────────────────

    private function buildPrompt(array $batch): string
    {
        $teacherBlocks = '';
        foreach ($batch as $record) {
            $empId       = htmlspecialchars((string)$record['employeeID'], ENT_XML1);
            $htmlRaw     = $record['currentResearch'] ?? '';
            $cleanedText = $this->cleanHtmlForPrompt($htmlRaw);
            $teacherBlocks .= "\n<teacher employeeID=\"{$empId}\">\n{$cleanedText}\n</teacher>\n";
        }

        return <<<PROMPT
You are a structured data extraction assistant. Each teacher block below holds a free-text "current research" field from a university faculty profile. Extract the teacher's research interests from it as a clean list.

Return ONLY a valid JSON object — no explanation, no markdown fences.

## Output format:
{
  "EMPLOYEE_ID": [
    {
      "interest": "...",
      "description": "..."
    }
  ]
}

## Field rules:
- **interest** (required): A short, clean research area or topic name in Title Case (e.g. "Machine Learning", "Computer Vision", "Natural Language Processing", "Supply Chain Management", "Renewable Energy Systems"). Normally 1–6 words. Never a full sentence. Never null.
- **description**: Extra detail the text gives about THIS specific interest (e.g. the specific problem, application, method or sub-topic: "Deep learning for early detection of plant leaf diseases"). Must come from the source text — do not invent. null if the text only names the topic.

## Important rules:
- Split combined lists into separate interests: "Machine Learning, Computer Vision and NLP" → three entries.
- If the text is a paragraph or a list of research works/paper titles, derive the underlying research areas (topics), not the titles themselves; put the specific work in "description" of the matching interest.
- Expand obvious abbreviations when unambiguous (e.g. "ML" → "Machine Learning", "NLP" → "Natural Language Processing", "IoT" → "Internet of Things").
- Remove duplicates (case-insensitive and near-duplicates such as "AI" and "Artificial Intelligence").
- Keep the order in which the interests appear in the source text.
- Strip all HTML tags, bullets and numbering from extracted values.
- Ignore filler such as "N/A", "None", "Will be updated soon", "Ongoing".
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

        foreach ($decoded as $employeeId => $rawInterests) {
            if (!is_array($rawInterests)) continue;

            $result[(string)$employeeId] = $this->normalizeInterests(array_map(
                fn($item) => [
                    'interest'    => is_array($item) ? ($item['interest'] ?? null) : $item,
                    'description' => is_array($item) ? ($item['description'] ?? null) : null,
                ],
                $rawInterests
            ));
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

    /**
     * Clean, de-duplicate and number a parsed list into research_interests rows.
     */
    private function normalizeInterests(array $items): array
    {
        $interests = [];
        $seen = [];

        foreach ($items as $item) {
            $interest = $this->cleanText(is_string($item['interest']) ? $item['interest'] : null);
            if ($interest === null || mb_strlen($interest) < 2) continue;
            if (preg_match('/^(n\/?a|none|nil|null|-+|will be updated.*|ongoing)$/i', $interest)) continue;

            $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $interest));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;

            $interests[] = [
                'interest'    => $interest,
                'description' => $this->cleanText(is_string($item['description']) ? $item['description'] : null),
                'sort_order'  => count($interests),
            ];
        }

        return $interests;
    }

    // ── Heuristic Fallback ────────────────────────────────────────────────────

    private function parseWithHeuristics(array $batch): array
    {
        $result = [];
        foreach ($batch as $record) {
            $empId = (string) $record['employeeID'];
            $result[$empId] = $this->parseInterestsHeuristic($record['currentResearch'] ?? '');
        }
        return $result;
    }

    private function parseInterestsHeuristic(string $raw): array
    {
        $text = $this->cleanHtmlForPrompt($raw);
        if ($text === '') return [];

        // Split on lines, then on list separators. Not on "and" — it is part of
        // names like "Signal and Image Processing".
        $parts = [];
        foreach (explode("\n", $text) as $line) {
            $line = preg_replace('/^\s*(?:\d+[.)]\s*|(?:[a-z]|[ivx]+)[.)]\s+)/iu', '', $line);
            // Numbering written inline — "Mathematics 2.Management 3.Finance" — is a list too
            foreach (preg_split('/\s*[,;|]\s*|\s+\d+[.)]\s*/u', $line) as $part) {
                $part = trim($part, " \t\n\r\0\x0B\xc2\xa0.:-•*");
                if ($part !== '') {
                    $parts[] = ['interest' => $part, 'description' => null];
                }
            }
        }

        // A long fragment is a sentence, not a topic — keep it whole as a description
        $parts = array_map(function ($p) {
            if (str_word_count($p['interest']) > 8) {
                return ['interest' => mb_strimwidth($p['interest'], 0, 80, '…'), 'description' => $p['interest']];
            }
            return ['interest' => ucwords($p['interest']), 'description' => null];
        }, $parts);

        return $this->normalizeInterests($parts);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function cleanHtmlForPrompt(string $html): string
    {
        if (empty(trim($html))) return '';
        $html    = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        // Comments hold MS Word's pasted XML; style/script hold text strip_tags() would keep
        $html    = preg_replace(['/<!--.*?-->/s', '/<(style|script)\b[^>]*>.*?<\/\1>/is'], '', $html);
        $cleaned = preg_replace('/<\/(p|li|div|ul|ol|h[1-6]|tr)>|<br\s*\/?>/i', "\n", $html);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = str_replace("\xc2\xa0", ' ', $cleaned);

        $lines  = explode("\n", $cleaned);
        $result = [];
        foreach ($lines as $line) {
            $line = preg_replace('/[ \t]+/', ' ', $line);
            $line = trim($line, " \t\n\r\0\x0B-•*");
            if (!empty($line)) {
                $result[] = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            }
        }
        return implode("\n", $result);
    }
}
