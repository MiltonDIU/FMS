<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ExportOldTeachersMembershipsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'export:old-teachers-memberships
                            {--source=db                                    : Data source: "db" (old_db connection) or "json"}
                            {--json-file=old_teacher.json                   : Source JSON filename (inside storage/app/public/)}
                            {--output=teachers_memberships_export.json      : Output filename (inside storage/app/public/exports/)}
                            {--limit=0                                       : Limit number of teachers processed (0 = all)}
                            {--batch-size=5                                  : Teachers per AI API call}
                            {--provider=auto                                 : AI provider: auto|openrouter|vertex|gemini|groq|anthropic|deepseek|heuristic}
                            {--dry-run                                       : Parse but do not write output file}
                            {--overwrite                                     : Overwrite the output file and re-process all records}
                            {--employee=                                     : Process only a specific employee ID}';

    protected $description = 'Export and AI-parse teacher memberships from old database/JSON into structured JSON for import';

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

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $rawRecords = array_slice($rawRecords, 0, $limit);
        }

        $this->info('Total teachers to process (before skip): ' . count($rawRecords));

        // Auto-resume: skip already-done records unless --overwrite
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

            foreach ($parsed as $employeeId => $memberships) {
                $oldTeacherId = $this->employeeToOldId[(string)$employeeId] ?? null;
                $exportData[] = [
                    '_employee_id'    => (string) $employeeId,
                    '_old_teacher_id' => $oldTeacherId,
                    'memberships'     => $memberships,
                ];
                $totalParsed += count($memberships);
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
                $failPath = $exportDir . 'memberships_export_errors.json';
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
            ['Teachers processed',             count($rawRecords)],
            ['Membership records extracted',   $totalParsed],
            ['Failed batches (teachers)',      $totalFailed],
            ['Avg memberships / teacher',      $processed > 0 ? round($totalParsed / $processed, 1) : 0],
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
            ->whereNotNull('membership')
            ->where('membership', '!=', '');

        if ($employeeId = $this->option('employee')) {
            $query->where('employeeID', $employeeId);
        }

        $rows = $query->select('employeeID', 'membership', 'position')->get();

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
            fn($r) => !empty(trim(strip_tags($r['membership'] ?? '')))
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
            $htmlRaw     = $record['membership'] ?? '';
            $position    = $record['position'] ?? '';
            $cleanedText = $this->cleanHtmlForPrompt($htmlRaw);
            $posText     = $position ? "\n[position/title in old record: {$position}]" : '';
            $teacherBlocks .= "\n<teacher employeeID=\"{$empId}\">{$posText}\n{$cleanedText}\n</teacher>\n";
        }

        return <<<PROMPT
You are a structured data extraction assistant. Parse each teacher's professional memberships, affiliations, and society memberships from the HTML/text below.

Return ONLY a valid JSON object — no explanation, no markdown fences.

## Output format:
{
  "EMPLOYEE_ID": [
    {
      "organization": "...",
      "record_type": "membership",
      "type": "...",
      "position": "...",
      "membership_id": "...",
      "scope": "...",
      "start_year": 2010,
      "end_year": 2023,
      "url": "...",
      "description": "..."
    }
  ]
}

## Field rules:
- **organization** (required): The clean name of the organization, society, association, body, committee, or institution the teacher is a member of (e.g., "Bangladesh Mathematical Society", "IEEE", "Institution of Engineers, Bangladesh"). Strip all HTML. Never null.
- **record_type**: Must be exactly one of: membership | affiliation
  - "membership" → formal society or professional body membership (IEEE, Life Member of Bangladesh Mathematical Society, Fellow of IEB, etc.)
  - "affiliation" → roles, positions, committee memberships, editorial board, conference roles, administrative/academic positions, alumni, club roles, event convener, etc.
- **type**: The role/membership type. Must be exactly one of: member | life_member | fellow | associate_member | honorary_member | advisor | executive_member | committee_member | reviewer | others
  - "member" → regular/ordinary/scientific/country representative/working group member
  - "life_member" → life member, life fellow
  - "fellow" → Fellow (e.g., "LIFE FELLOW")
  - "associate_member" → associate member
  - "honorary_member" → honorary member
  - "advisor" → advisor, advisory member, convener
  - "executive_member" → executive member, joint convener, organizing committee member
  - "committee_member" → board member, editorial board member, managing committee member, academic council member, regent board member, curriculum committee member
  - "reviewer" → reviewer, referee
  - "others" → anything not fitting the above categories clearly
- **position**: The specific role or position title within the organization (e.g. "Vice President", "Editorial Board Member", "Country Representative", "Deputy Proctor"). null if not applicable.
- **membership_id**: Any explicit membership ID or registration number mentioned (e.g., "LIFE FELLOW / 5020", "DMINB/CE-0359"). null if not mentioned.
- **scope**: Geographic reach of the organization. Must be exactly one of: local | national | international | null
  - "local" → university-level clubs, DIU committees, local chapters
  - "national" → Bangladesh-level societies, national associations
  - "international" → IEEE, ACM, global organizations, conferences held abroad
  - null → if cannot be determined
- **start_year**: 4-digit integer if a start year or "from" year is mentioned; null if unknown.
- **end_year**: 4-digit integer if an end year is mentioned; null if ongoing or unknown.
- **url**: Any URL found in the text related to this membership/affiliation. null if not present.
- **description**: Any extra context like location, conference name, purpose, etc. If none, null.

## Important rules:
- **Crucial distinction between type and position**: Do not put membership grades like "Life Member", "Fellow", "Member" in the "position" field. The "type" field indicates the membership grade (e.g., life_member, fellow), while the "position" field holds specific administrative or executive titles/roles within that organization (e.g., "Vice President", "General Secretary", "Editorial Board Member", "Deputy Proctor"). If no specific title is mentioned, "position" should be null.
- Parse EACH distinct membership/affiliation item separately. Do NOT merge bullet points.
- Strip all HTML tags from extracted values.
- If a bullet point describes a position held at an event/conference (e.g., "Scientific Member to 6th International Conference..."), treat the conference organizer as the organization.
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

        $validTypes = ['member', 'life_member', 'fellow', 'associate_member', 'honorary_member', 'advisor', 'executive_member', 'committee_member', 'reviewer', 'others'];

        $result = [];

        foreach ($decoded as $employeeId => $rawMemberships) {
            if (!is_array($rawMemberships)) continue;

            $result[(string)$employeeId] = [];

            foreach ($rawMemberships as $m) {
                $organization = trim($m['organization'] ?? '');
                if ($organization === '') continue;

                $type = strtolower(trim($m['type'] ?? ''));
                if (!in_array($type, $validTypes, true)) {
                    $type = 'others';
                }

                $result[(string)$employeeId][] = [
                    'organization'  => $organization,
                    'record_type'   => in_array($m['record_type'] ?? '', ['membership', 'affiliation'], true) ? $m['record_type'] : 'membership',
                    'type'          => $type,
                    'position'      => $this->cleanText($m['position'] ?? null),
                    'membership_id' => $this->cleanText($m['membership_id'] ?? null),
                    'scope'         => in_array($m['scope'] ?? '', ['local', 'national', 'international'], true) ? $m['scope'] : null,
                    'start_year'    => $this->validateYear($m['start_year'] ?? null),
                    'end_year'      => $this->validateYear($m['end_year'] ?? null),
                    'url'           => $this->cleanText($m['url'] ?? null),
                    'description'   => $this->cleanText($m['description'] ?? null),
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
            $result[$empId] = $this->parseMembershipsHeuristic($record['membership'] ?? '', $record['position'] ?? '');
        }
        return $result;
    }

    private function parseMembershipsHeuristic(string $raw, string $position = ''): array
    {
        if (empty(trim($raw))) return [];

        $raw     = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>', '</ul>', '</ol>'], "\n", $raw);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines       = explode("\n", $cleaned);
        $memberships = [];

        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', $line);
            $line = str_replace("\xc2\xa0", ' ', $line);
            // Remove leading list markers: numbers like "1.", "1)", letters "a.", bullet chars
            $line = preg_replace('/^[\s]*(?:\d+[\.\)]\s*|[a-zA-Z][\.\)]\s*|[-•*►▸▹◦‣⁃]\s*)/', '', $line);
            $line = trim($line);
            if (empty($line) || strlen($line) < 5) continue;

            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');

            // Detect years
            $startYear = null;
            $endYear   = null;
            if (preg_match('/\b(?:from\s+)?(\d{4})\s*(?:to|-)\s*(\d{4}|\bdate\b|\bpresent\b|\btill\s+date\b)/i', $line, $ym)) {
                $startYear = (int)$ym[1];
                $endYear   = is_numeric($ym[2]) ? (int)$ym[2] : null;
            } elseif (preg_match('/\b(19|20)\d{2}\b/', $line, $ym)) {
                $startYear = (int)$ym[0];
            }

            // Detect organization: take text after comma/colon/dash if it looks like an org name
            $organization = $line;

            // Detect type
            $type = $this->guessMembershipType($line);

            // Detect membership_id
            $membershipId = null;
            if (preg_match('/(?:no\.?|id\.?|#|registration\s*no\.?)[:\s]*([A-Z0-9\/\-]+)/i', $line, $mid)) {
                $membershipId = trim($mid[1]);
            } elseif (preg_match('/\b([A-Z]{2,}\/[A-Z]{2,}[\-\/][A-Z0-9\-]+)\b/', $line, $mid)) {
                $membershipId = $mid[1];
            } elseif (preg_match('/FELLOW\s*\/\s*(\d+)/i', $line, $mid)) {
                $membershipId = 'FELLOW/' . $mid[1];
            }

            $memberships[] = [
                'organization'  => $organization,
                'type'          => $type,
                'membership_id' => $membershipId,
                'start_year'    => $startYear,
                'end_year'      => $endYear,
                'description'   => null,
            ];
        }

        return $memberships;
    }

    private function guessMembershipType(string $text): string
    {
        $t = strtolower($text);

        if (str_contains($t, 'life fellow') || preg_match('/\blife\s+fellow\b/i', $t)) return 'life_member';
        if (str_contains($t, 'life member')) return 'life_member';
        if (str_contains($t, 'honorary')) return 'honorary_member';
        if (str_contains($t, 'associate member')) return 'associate_member';
        if (preg_match('/\bfellow\b/i', $t)) return 'fellow';
        if (preg_match('/\b(editorial\s+board|managing\s+committee|academic\s+council|regent\s+board|curriculum\s+committee|board\s+of\s+governor)\b/i', $t)) return 'committee_member';
        if (preg_match('/\b(executive\s+member|organizing\s+committee|joint\s+convener)\b/i', $t)) return 'executive_member';
        if (preg_match('/\b(advisor|advisory|convener)\b/i', $t)) return 'advisor';
        if (preg_match('/\b(reviewer|referee)\b/i', $t)) return 'reviewer';
        if (preg_match('/\b(member)\b/i', $t)) return 'member';

        return 'others';
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
