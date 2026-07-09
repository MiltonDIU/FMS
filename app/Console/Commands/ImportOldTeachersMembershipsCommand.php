<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\MembershipType;
use App\Models\Teacher;
use Illuminate\Console\Command;

class ImportOldTeachersMembershipsCommand extends Command
{
    protected $signature = 'import:old-teachers-memberships
                            {--file=teachers_memberships_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0                                : Limit the number of teachers to process}
                            {--dry-run                                : Preview without writing to DB}
                            {--verbose-resolve                        : Show each org/type lookup result (found vs created)}
                            {--skip-existing                          : Skip already existing database entries}';

    protected $description = 'Import teacher memberships from exported JSON into the new database.
    Automatically resolves MembershipType and MembershipOrganization:
      - If already exists → returns existing ID
      - If not found      → creates new record and returns new ID
    Recommended: run export:old-teachers-memberships --provider=vertex first.';

    /**
     * Mapping from AI-exported type strings → canonical MembershipType name in DB.
     *
     * The import checks if a MembershipType with this name already exists.
     * If found   → returns existing id (no new record).
     * If missing → creates new MembershipType and returns its id.
     */
    const TYPE_LABEL_MAP = [
        'member'           => 'Member',
        'life_member'      => 'Life Member',
        'fellow'           => 'Fellow',
        'associate_member' => 'Associate Member',
        'honorary_member'  => 'Honorary Member',
        'advisor'          => 'Advisor',
        'executive_member' => 'Executive Member',
        'committee_member' => 'Committee Member',
        'reviewer'         => 'Reviewer',
        'others'           => 'Others',
    ];

    /** Runtime in-memory cache: typeKey → id */
    protected array $typeCache = [];

    /** Runtime in-memory cache: normalized org name → id */
    protected array $orgCache = [];

    /** Counters for type/org resolution telemetry */
    protected int $typesFound   = 0;
    protected int $typesCreated = 0;
    protected int $orgsFound    = 0;
    protected int $orgsCreated  = 0;

    public function handle(): int
    {
        $file    = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun  = (bool) $this->option('dry-run');
        $verbose = (bool) $this->option('verbose-resolve');

        // ── Guard: file must exist and be valid JSON ──────────────────────────
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run first: php artisan export:old-teachers-memberships --provider=vertex --overwrite");
            return Command::FAILURE;
        }

        if (filesize($file) === 0) {
            $this->error("Export file is empty (0 bytes): {$file}");
            $this->info("Re-run: php artisan export:old-teachers-memberships --provider=vertex --overwrite");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in {$file}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // ── Limits ───────────────────────────────────────────────────────────
        $limit        = (int) $this->option('limit');
        $processCount = ($limit > 0 && $limit < count($data)) ? $limit : count($data);

        $this->info($dryRun
            ? "🔍 DRY RUN — no changes will be written to DB"
            : "🚀 Importing memberships into new DB..."
        );
        $this->info("Total teacher records to process: {$processCount}");
        $this->newLine();

        // ── Counters ──────────────────────────────────────────────────────────
        // Build country name to ID mapping (case-insensitive keys for easy lookup)
        $countryMap = [];
        foreach (\App\Models\Country::all() as $country) {
            $countryMap[mb_strtolower($country->name)] = [
                'id'   => $country->id,
                'name' => $country->name,
            ];
        }

        $imported      = 0;
        $updated       = 0;
        $skipped       = 0;
        $teacherFailed = 0;
        $recordFailed  = 0;
        $count         = 0;

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        // ── Main loop ────────────────────────────────────────────────────────
        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId  = $record['_employee_id'] ?? $record['employee_id'] ?? null;
            $memberships = $record['memberships'] ?? [];

            if (!$employeeId) {
                $skipped++;
                $bar->setMessage("Skipped (no employeeId)");
                $bar->advance();
                continue;
            }

            // Look up teacher in new DB by employee_id
            $teacher = Teacher::where('employee_id', $employeeId)->first();
            if (!$teacher) {
                $bar->setMessage("Teacher not found: {$employeeId}");
                $teacherFailed++;
                $bar->advance();
                continue;
            }

            if (empty($memberships)) {
                $bar->setMessage("No memberships for: {$employeeId}");
                $skipped++;
                $bar->advance();
                continue;
            }

            $bar->setMessage("Processing: {$employeeId} (" . count($memberships) . " memberships)");

            foreach ($memberships as $m) {
                $organization = trim($m['organization'] ?? '');
                if ($organization === '') {
                    $skipped++;
                    continue;
                }

                // ── Resolve MembershipType ────────────────────────────────
                // Checks DB → returns existing id OR creates new and returns new id
                $typeKey = strtolower(trim($m['type'] ?? 'others'));
                $typeKey = array_key_exists($typeKey, self::TYPE_LABEL_MAP) ? $typeKey : 'others';
                $typeId  = $this->resolveMembershipType($typeKey, $dryRun, $verbose);

                // Resolve country ID
                $countryId = null;
                $extractedCountry = trim($m['country'] ?? '');
                if ($extractedCountry !== '') {
                    $cSearch = mb_strtolower($extractedCountry);
                    if (isset($countryMap[$cSearch])) {
                        $countryId = $countryMap[$cSearch]['id'];
                    } else {
                        foreach ($countryMap as $cKey => $cInfo) {
                            if (stripos($cKey, $cSearch) !== false || stripos($cSearch, $cKey) !== false) {
                                $countryId = $cInfo['id'];
                                break;
                            }
                        }
                    }
                }

                // Resolve parent organization ID if any
                $parentId = null;
                $parentName = trim($m['parent_organization'] ?? '');
                if ($parentName !== '') {
                    $parentId = $this->resolveMembershipOrganization($parentName, null, null, $countryId, $dryRun, $verbose);
                }

                // ── Resolve MembershipOrganization ──
                $orgId = $this->resolveMembershipOrganization($organization, $parentId, $parentName, $countryId, $dryRun, $verbose);

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY RUN] %-15s → %-40s | Type: %-18s | RecordType: %s | Scope: %s",
                        $employeeId,
                        mb_substr($organization, 0, 40),
                        self::TYPE_LABEL_MAP[$typeKey],
                        $m['record_type'] ?? 'membership',
                        $m['scope'] ?? '—'
                    ));
                    $imported++;
                    continue;
                }

                try {
                    $endYear  = isset($m['end_year']) ? (int)$m['end_year'] : null;
                    $isActive = !($endYear && $endYear < (int) date('Y'));

                    // Determine record_type: 'membership' or 'affiliation'
                    $recordType = $m['record_type'] ?? 'membership';
                    if (!in_array($recordType, ['membership', 'affiliation'], true)) {
                        $recordType = 'membership';
                    }

                    // Determine scope: local / national / international
                    $scope = $m['scope'] ?? null;
                    if (!in_array($scope, ['local', 'national', 'international'], true)) {
                        $scope = null;
                    }

                    if ($this->option('skip-existing')) {
                        $exists = Membership::where([
                            'teacher_id'                 => $teacher->id,
                            'membership_organization_id' => $orgId,
                            'membership_type_id'         => $typeId,
                        ])->exists();
                        if ($exists) {
                            $imported++;
                            continue;
                        }
                    }

                    $result = Membership::updateOrCreate(
                        [
                            'teacher_id'                 => $teacher->id,
                            'membership_organization_id' => $orgId,
                            'membership_type_id'         => $typeId,
                        ],
                        [
                            'record_type'   => $recordType,
                            'position'      => $this->cleanText($m['position'] ?? null),
                            'scope'         => $scope,
                            'url'           => $this->cleanText($m['url'] ?? null),
                            'membership_id' => $m['membership_id'] ?? null,
                            'start_date'    => $m['start_year'] ? $m['start_year'] . '-01-01' : null,
                            'end_date'      => $endYear ? $endYear . '-12-31' : null,
                            'status'        => $isActive ? 'active' : 'inactive',
                            'description'   => $m['description'] ?? null,
                            'sort_order'    => 0,
                            'is_active'     => $isActive,
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("  ✗ Failed [{$employeeId}] {$organization}: " . $e->getMessage());
                    $recordFailed++;
                }
            }

            $bar->advance();
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine(2);

        // ── Summary table ────────────────────────────────────────────────────
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed',                         $count],
                ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',      '━━━━━'],
                ['Membership records → NEW (created)',        $dryRun ? $imported : $imported],
                ['Membership records → UPDATED (existing)',  $dryRun ? '—' : $updated],
                ['Records skipped (empty org / no empId)',   $skipped],
                ['Teachers not found in new DB',             $teacherFailed],
                ['Individual record failures',               $recordFailed],
                ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',      '━━━━━'],
                ['MembershipType — found in DB (reused)',    $this->typesFound],
                ['MembershipType — not found → created',    $this->typesCreated],
                ['MembershipOrganization — found (reused)', $this->orgsFound],
                ['MembershipOrganization — not found → created', $this->orgsCreated],
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Import complete.");
        } else {
            $this->warn("DRY RUN complete — no changes written.");
        }

        return Command::SUCCESS;
    }

    // ── Lookup / Auto-Create Helpers ──────────────────────────────────────────

    /**
     * Resolve a MembershipType by its canonical label.
     *
     * Algorithm:
     *   1. Check in-memory cache (fastest — same process)
     *   2. Query DB by name (case-sensitive; firstOrCreate is atomic)
     *   3. If found  → increment $typesFound,   return existing id
     *   4. If created → increment $typesCreated, return new id
     */
    private function resolveMembershipType(string $typeKey, bool $dryRun, bool $verbose): ?int
    {
        // 1. In-memory cache hit
        if (array_key_exists($typeKey, $this->typeCache)) {
            return $this->typeCache[$typeKey];
        }

        $label = self::TYPE_LABEL_MAP[$typeKey] ?? 'Others';

        if ($dryRun) {
            $this->typeCache[$typeKey] = null;
            return null;
        }

        // 2. DB lookup + auto-create if missing
        $existsBefore = MembershipType::where('name', $label)->exists();

        $type = MembershipType::firstOrCreate(
            ['name' => $label],
            [
                'description' => null,
                'sort_order'  => 0,
                'is_active'   => true,
            ]
        );

        // 3/4. Track telemetry
        if ($existsBefore) {
            $this->typesFound++;
            if ($verbose) {
                $this->line("  [Type] ✓ Found existing   → \"{$label}\" (id: {$type->id})");
            }
        } else {
            $this->typesCreated++;
            if ($verbose) {
                $this->line("  [Type] + Created new      → \"{$label}\" (id: {$type->id})");
            }
        }

        $this->typeCache[$typeKey] = $type->id;
        return $type->id;
    }

    /**
     * Resolve a MembershipOrganization by normalized name.
     *
     * Algorithm:
     *   1. Check in-memory cache (fastest)
     *   2. Query DB with case-insensitive match on name
     *   3. If found  → increment $orgsFound,   return existing id
     *   4. If missing → create new record, increment $orgsCreated, return new id
     */
    private function resolveMembershipOrganization(
        string $name,
        ?int $parentId,
        ?string $parentName,
        ?int $countryId,
        bool $dryRun,
        bool $verbose
    ): ?int {
        $normalizedName = $this->normalizeOrgName($name);
        $cacheKey = $normalizedName . '|' . ($parentId ?? '') . '|' . ($countryId ?? '');

        // 1. In-memory cache hit
        if (array_key_exists($cacheKey, $this->orgCache)) {
            return $this->orgCache[$cacheKey];
        }

        if ($dryRun) {
            $this->orgCache[$cacheKey] = null;
            return null;
        }

        // 2. DB lookup and auto-create with auto-approval
        $existsBefore = \App\Models\Organization::whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->when($countryId, function ($q) use ($countryId) {
                $q->where(function ($sub) use ($countryId) {
                    $sub->where('country_id', $countryId)->orWhereNull('country_id');
                });
            })
            ->exists();

        $flags = ['is_professional_body' => true];
        if ($parentId) {
            $flags['parent_id'] = $parentId;
        }

        $org = \App\Models\Organization::findOrCreateWithAutoApproval(
            $normalizedName,
            null,
            $countryId,
            $flags
        );

        if ($parentId && !$org->parent_id) {
            $org->update(['parent_id' => $parentId]);
        }

        if ($existsBefore) {
            $this->orgsFound++;
            if ($verbose) {
                $this->line("  [Org]  ✓ Found existing   → \"{$normalizedName}\" (id: {$org->id})");
            }
        } else {
            $this->orgsCreated++;
            if ($verbose) {
                $this->line("  [Org]  + Created new      → \"{$normalizedName}\" (id: {$org->id})");
            }
        }

        $this->orgCache[$cacheKey] = $org->id;
        return $org->id;
    }

    /**
     * Normalize an organization name for consistent DB comparison:
     * - Strip HTML tags
     * - Decode HTML entities
     * - Collapse multiple whitespace into single space
     * - Trim punctuation from edges
     * - Limit to 255 characters (DB column limit)
     */
    private function normalizeOrgName(string $name): string
    {
        $name = strip_tags($name);
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, " \t\n\r\0\x0B.,;:-");
        return mb_substr($name, 0, 255);
    }

    /**
     * Clean a nullable text value: strip tags, decode entities, trim whitespace.
     * Returns null if empty after cleaning.
     */
    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
