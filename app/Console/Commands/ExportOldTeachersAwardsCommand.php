<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ExportOldTeachersAwardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'export:old-teachers-awards
                            {--source=db                               : Data source: "db" (old_db connection) or "json"}
                            {--json-file=old_teacher.json              : Source JSON filename (inside storage/app/public/)}
                            {--output=teachers_awards_export.json      : Output filename (inside storage/app/public/exports/)}
                            {--limit=0                                  : Limit number of teachers processed (0 = all)}
                            {--batch-size=10                            : Teachers per AI API call}
                            {--provider=auto                            : AI provider: auto|openrouter|gemini|groq|anthropic|deepseek|heuristic}
                            {--dry-run                                  : Parse but do not write output file}
                            {--overwrite                                : Overwrite the output file and re-process all records}';

    protected $description = 'Export and AI-parse teacher awards/scholarships from old database/JSON';

    const MODELS = [
        'anthropic'  => 'claude-sonnet-4-20250514',
        'groq'       => 'llama-3.3-70b-versatile',
        'gemini'     => 'gemini-2.5-flash',
        'openrouter' => 'google/gemini-3.5-flash',
        'deepseek'   => 'deepseek-v4-flash',
    ];

    protected string $aiProvider = 'openrouter'; // resolved at runtime
    protected array $employeeToOldId = [];

    public function handle(): int
    {
        $this->resolveAiProvider();
        $this->buildEmployeeIdMap();

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

        // Batch process
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

            foreach ($parsed as $employeeId => $awards) {
                $oldTeacherId = $this->employeeToOldId[(string)$employeeId] ?? null;
                $exportData[] = [
                    '_employee_id'    => (string) $employeeId,
                    '_old_teacher_id' => $oldTeacherId,
                    'awards'          => $awards,
                ];
                $totalParsed += count($awards);
            }

            $bar->advance(count($batch));

            if ($batchIndex < count($batches) - 1 && $this->aiProvider !== 'heuristic') {
                usleep(300_000);
            }
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine();

        // Write output
        $exportDir = storage_path('app/public/exports/');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        if (!$this->option('dry-run')) {
            $path = $exportDir . $this->option('output');
            file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✅ Export complete → {$path}");

            if (!empty($failLog)) {
                $failPath = $exportDir . 'awards_export_errors.json';
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
                ['Awards records extracted',   $totalParsed],
                ['Failed batches (teachers)',  $totalFailed],
                ['Avg awards / teacher',       $processed > 0 ? round($totalParsed / $processed, 1) : 0],
            ]
        );

        return $totalFailed > 0 ? 1 : 0;
    }

    private function loadSourceRecords(): array
    {
        return $this->option('source') === 'db'
            ? $this->loadFromDb()
            : $this->loadFromJson();
    }

    private function loadFromDb(): array
    {
        $rows = DB::connection('old_db')
            ->table('teacher')
            ->whereNotNull('awardScholarship')
            ->where('awardScholarship', '!=', '')
            ->select('employeeID', 'awardScholarship')
            ->get();

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
            fn($r) => !empty(trim(strip_tags($r['awardScholarship'] ?? '')))
        ));

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

        $priority = [ 'gemini','deepseek', 'openrouter', 'groq', 'anthropic'];
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

    private function parseWithAi(array $batch): array
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
                'response_format' => ['type' => 'json_object'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Groq API {$response->status()}: " . $response->body());
        }

        $content = $response->json('choices.0.message.content', '');
        return $this->parseClaudeResponse($content, $batch);
    }

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
                    'responseMimeType' => 'application/json',
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
            $htmlRaw      = $record['awardScholarship'] ?? '';
            $cleanedText  = $this->cleanHtmlForPrompt($htmlRaw);
            $teacherBlocks .= "\n<teacher employeeID=\"{$empId}\">\n{$cleanedText}\n</teacher>\n";
        }

        return <<<PROMPT
You are a structured data extraction assistant. Parse each teacher's awards, scholarships, recognitions, and achievements list from the HTML below.

Return ONLY a valid JSON object — no explanation, no markdown fences.

## Output format:
{
  "EMPLOYEE_ID": [
    {
      "title": "...",
      "awarding_body": "...",
      "type": "...",
      "year": 2024,
      "remarks": "..."
    }
  ]
}

## Field rules:
- **title** (required): The clean name of the award, scholarship, or achievement (e.g., "Excellence in Teaching 2023", "Evaluation Panel Member of the 9th International Conference on Hospitality and Tourism Management (ICOHT 2022)"). Strip HTML tags. Never null.
- **awarding_body**: The organization, institution, university, ministry, or body that gave the award or hosted the event/role (e.g., "Tazkera and Golam Mustafa Center for Teaching and Learning of HRDI institute", "The International Institute Of Knowledge Management (TIIKM)", "Ministry of Civil Aviation and Tourism"). If not explicitly mentioned or cannot be inferred, return null.
- **type**: Must be exactly one of: award | scholarship | recognition | appreciation | other
  - "award" -> for winning a prize, best paper, outstanding teaching skills award, etc.
  - "scholarship" -> for fellowships, research grants, international credit mobility projects, teaching mobility funding, etc.
  - "recognition" -> for being nominated, serving as a panel member, speaker, advisor, external reviewer, committee member, scientific member, or scholarly publications.
  - "appreciation" -> for letters of appreciation or gratitude.
  - "other" -> for other activities not fitting the above.
- **year**: 4-digit integer if any year is mentioned (e.g., 2023, 2020); null if truly unknown.
- **remarks**: Any extra information, locations (e.g., "Turkey", "Malaysia", "Vietnam"), dates, or details not covered. If none, return null.

## Important:
- Parse each distinct item/bullet point separately. Do NOT merge them.
- Strip all HTML tags from extracted values.
- Include ALL employeeIDs in the output even if their list is empty.

{$teacherBlocks}
PROMPT;
    }

    private function parseClaudeResponse(string $content, array $batch): array
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

        foreach ($decoded as $employeeId => $rawAwards) {
            if (!is_array($rawAwards)) continue;

            $result[(string)$employeeId] = [];

            foreach ($rawAwards as $aw) {
                $title = trim($aw['title'] ?? '');
                if ($title === '') continue;

                $result[(string)$employeeId][] = [
                    'title'         => $title,
                    'awarding_body' => $this->cleanText($aw['awarding_body'] ?? null),
                    'type'          => $this->validateCategory($aw['type'] ?? null),
                    'year'          => $this->validateYear($aw['year'] ?? null),
                    'remarks'       => $this->cleanText($aw['remarks'] ?? null),
                ];
            }
        }

        foreach ($batch as $record) {
            $empId = (string)$record['employeeID'];
            if (!isset($result[$empId])) {
                $result[$empId] = [];
            }
        }

        return $result;
    }

    private function parseWithHeuristics(array $batch): array
    {
        $result = [];
        foreach ($batch as $record) {
            $empId = (string) $record['employeeID'];
            $raw = $record['awardScholarship'] ?? '';
            $result[$empId] = $this->parseAwards($raw);
        }
        return $result;
    }

    private function parseAwards(string $raw): array
    {
        if (empty(trim($raw))) return [];

        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

        $cleaned = str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</div>'], "\n", $raw);
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = explode("\n", $cleaned);
        $awards = [];

        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', $line);
            $line = str_replace("\xc2\xa0", ' ', $line);
            $line = trim($line, " \t\n\r\0\x0B-•*");
            if (empty($line) || strlen($line) < 3) continue;

            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');

            $year = null;
            if (preg_match('/\b(19|20)\d{2}\b/', $line, $matches)) {
                $year = $matches[0];
            }

            $awardingBody = null;
            $patterns = [
                '/\((?:for|to|at|by|from)\s+((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)\)/',
                '/(?:by|from|at|to)\s+((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)/',
                '/[,:-]\s*([A-Z][A-Z\s0-9]+)[\.\s]*$/',
                '/[,:-]\s*((?:[A-Z][a-z&0-9\.]+\s*|of\s+|and\s+)+)[\.\s]*$/',
            ];

            $instKeywords = ['University', 'College', 'School', 'Ministry', 'Division', 'Board', 'Institute', 'Department', 'Committee', 'Council', 'Center', 'Academy', 'Organization', 'Agency', 'Association', 'Foundation', 'Society', 'DIU', 'Cisco', 'UGC', 'ICT', 'Govt'];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $candidate = trim($matches[1]);
                    $candidate = preg_replace('/[,;\.\(\)\s]+$/', '', $candidate);
                    $candidate = trim($candidate);

                    if (empty($candidate) || strlen($candidate) < 2) continue;
                    if (is_numeric($candidate)) continue;

                    $isInstitution = preg_match('/^[A-Z]{2,}$/', $candidate);
                    if (!$isInstitution) {
                        foreach ($instKeywords as $kw) {
                            if (stripos($candidate, $kw) !== false) {
                                $isInstitution = true;
                                break;
                            }
                        }
                    }

                    if ($isInstitution) {
                        $candidate = preg_replace('/\b(19|20)\d{2}\b/', '', $candidate);
                        $awardingBody = trim($candidate, " \t\n\r\0\x0B,-.");
                        if (!empty($awardingBody)) break;
                    }
                }
            }

            if (!$awardingBody) {
                $instPattern = '/(?:University|College|Ministry|Division|Board|Institute|Academy|Society|Center)\s+(?:of|at|in)?\s*[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*/i';
                if (preg_match($instPattern, $line, $matches)) {
                    $candidate = trim($matches[0]);
                    if (strlen($candidate) > 10) {
                        $awardingBody = $candidate;
                    }
                }
            }

            $awards[] = [
                'title'         => $line,
                'awarding_body' => $awardingBody,
                'type'          => $this->guessType($line),
                'year'          => $year ? (int)$year : null,
                'remarks'       => null,
            ];
        }

        return $awards;
    }

    private function guessType(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'scholarship')) return 'scholarship';
        if (str_contains($text, 'recognition')) return 'recognition';
        if (str_contains($text, 'appreciation')) return 'appreciation';
        return 'award';
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function validateCategory(?string $cat): string
    {
        $cat = strtolower(trim($cat ?? ''));
        $valid = ['award', 'scholarship', 'recognition', 'appreciation', 'other'];
        return in_array($cat, $valid, true) ? $cat : 'award';
    }

    private function validateYear($year): ?int
    {
        $y = (int) $year;
        return ($y >= 1970 && $y <= (int) date('Y') + 1) ? $y : null;
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
