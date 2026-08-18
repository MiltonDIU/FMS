<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\ScopusImport;
use App\Services\Scopus\ScopusAnalysisPayloadService;
use Illuminate\Console\Command;

/**
 * Fills in where each external author was writing from, from runs already done.
 *
 * The authors table had nothing to tell one row from another, so a co-author at
 * Southeast University and one of our own teachers under a misspelt name looked
 * identical. New imports record it as they go; this is for the 7,347 rows that
 * arrived before the column existed.
 *
 * Reads the analysis json each run left behind rather than re-importing, which
 * would mean re-applying decisions somebody has already applied. Nothing is
 * created and no publication is touched — the only column written is on authors.
 */
class BackfillAuthorAffiliationsCommand extends Command
{
    protected $signature = 'scopus:backfill-affiliations
                            {--import=* : Only these import ids, newest first by default}
                            {--fresh : Clear what is already recorded before starting}';

    protected $description = 'Record which external authors ever wrote under our own affiliation, from finished Scopus runs';

    public function handle(ScopusAnalysisPayloadService $payloads): int
    {
        if ($this->option('fresh')) {
            $cleared = Author::query()->update(['used_our_affiliation' => null]);
            $this->warn("Cleared {$cleared} author(s) before starting.");
        }

        $ids = $this->option('import')
            ?: ScopusImport::query()->where('status', ScopusImport::STATUS_READY)
                ->orderBy('id')
                ->pluck('id')
                ->all();

        if ($ids === []) {
            $this->error('No finished runs to read.');

            return self::FAILURE;
        }

        /*
         * Oldest first, deliberately.
         *
         * recordAffiliationStanding never turns a true back into a false, so
         * whichever run last saw somebody under our own name is the one that
         * decides — and reading in order means the newest run's answer is the
         * one left standing where they disagree.
         */
        $totals = ['seen' => 0, 'ours' => 0, 'elsewhere' => 0, 'unknown' => 0, 'bound' => 0];

        foreach ($ids as $id) {
            $counts = $payloads->recordAffiliationStandings((int) $id);

            if ($counts['seen'] === 0 && $counts['unknown'] === 0) {
                $this->line("  run #{$id}: nothing to read (no people recorded)");

                continue;
            }

            $this->line(sprintf(
                '  run #%s: %d stamped — %d ours, %d elsewhere; %d Scopus ids bound; %d not in the authors table',
                $id,
                $counts['seen'],
                $counts['ours'],
                $counts['elsewhere'],
                $counts['bound'],
                $counts['unknown'],
            ));

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $counts[$key];
            }
        }

        $this->newLine();
        $this->info(sprintf('%d author entries read across %d run(s).', $totals['seen'], count($ids)));

        $this->table(
            ['Standing', 'Authors'],
            [
                ['Wrote under our affiliation', Author::query()->where('used_our_affiliation', true)->count()],
                ['Only ever under another', Author::query()->where('used_our_affiliation', false)->count()],
                ['Never established', Author::query()->whereNull('used_our_affiliation')->count()],
            ],
        );

        $this->table(
            ['Scopus identifiers', 'Count'],
            [
                ['Recorded in total', \App\Models\ScopusAuthorId::query()->count()],
                ['Against a teacher', \App\Models\ScopusAuthorId::query()->where('authorable_type', \App\Models\Teacher::class)->count()],
                ['Against an author', \App\Models\ScopusAuthorId::query()->where('authorable_type', Author::class)->count()],
                ['Teachers with the column filled', \App\Models\Teacher::query()->whereNotNull('scopus_id')->count()],
                ['Authors with the column filled', Author::query()->whereNotNull('scopus_id')->count()],
            ],
        );

        return self::SUCCESS;
    }
}
