<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\ScopusImport;
use App\Models\Teacher;
use App\Services\Scopus\AffiliationMatcher;
use App\Services\Scopus\AuthorAttacher;
use App\Services\Scopus\ScopusAnalysisPayloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Says, for every authorship already recorded, whether that author wrote the
 * paper under our own affiliation.
 *
 * The column is filled going forward by the importers, but nearly everything in
 * the table predates it, and a publication page that can only show authors it
 * has an answer for would show almost nobody. Two sources, because the rows came
 * from two places:
 *
 *   - Scopus runs, whose analysis json still holds the affiliation line printed
 *     against each author. Read back rather than re-imported, so no decision
 *     anybody made is applied a second time.
 *
 *   - The old site and the PD export, which carry no affiliations at all. What
 *     each of them is a list *of* stands in for the missing column — see
 *     fromLegacyImports, which reads the two differently for that reason.
 */
class BackfillPublicationAuthorAffiliationsCommand extends Command
{
    protected $signature = 'publications:backfill-author-affiliations
                            {--import=* : Only these Scopus import ids}
                            {--skip-legacy : Leave old-site and PD publications alone}
                            {--fresh : Clear what is already recorded before starting}';

    protected $description = 'Record, per paper, which of its authors wrote it under our own affiliation';

    public function handle(ScopusAnalysisPayloadService $payloads): int
    {
        if ($this->option('fresh')) {
            $cleared = DB::table('publication_authors')->update(['used_our_affiliation' => null]);
            $this->warn("Cleared {$cleared} authorship(s) before starting.");
        }

        $fromScopus = $this->fromScopusRuns($payloads);
        $fromLegacy = $this->option('skip-legacy') ? 0 : $this->fromLegacyImports();

        $this->newLine();
        $this->table(['Source', 'Authorships answered'], [
            ['Scopus runs', $fromScopus],
            ['Old site / PD (teachers)', $fromLegacy],
        ]);

        $this->table(['Standing', 'Authorships'], [
            ['Wrote under our affiliation', DB::table('publication_authors')->where('used_our_affiliation', true)->count()],
            ['Wrote under another', DB::table('publication_authors')->where('used_our_affiliation', false)->count()],
            ['Still unestablished', DB::table('publication_authors')->whereNull('used_our_affiliation')->count()],
        ]);

        return self::SUCCESS;
    }

    /**
     * The affiliation lines each finished run recorded, applied to the rows that
     * run created.
     */
    protected function fromScopusRuns(ScopusAnalysisPayloadService $payloads): int
    {
        $ids = $this->option('import')
            ?: ScopusImport::query()
                ->where('status', ScopusImport::STATUS_READY)
                ->orderBy('id')
                ->pluck('id')
                ->all();

        $answered = 0;

        foreach ($ids as $id) {
            $payload = $payloads->getPayload((int) $id);

            if (! $payload) {
                continue;
            }

            $import = ScopusImport::find((int) $id);
            $options = $import?->matchingOptions();
            $matcher = new AffiliationMatcher($options);
            $attacher = AuthorAttacher::for($options);

            $papers = collect($payload['papers'] ?? [])
                ->filter(fn (array $paper) => filled($paper['existing_publication_id'] ?? null)
                    && filled($paper['all_author_affiliations'] ?? ''));

            $this->info("Run #{$id}: {$papers->count()} paper(s) with affiliations recorded.");
            $bar = $this->output->createProgressBar($papers->count());

            foreach ($papers as $paper) {
                $answered += $this->applyPaper($paper, $matcher, $attacher);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        return $answered;
    }

    /**
     * One paper's authors, matched to its rows by resolving the names again.
     *
     * Not by sort_order, tempting as that is. The importer files each author
     * under their position in the list, but the publication edit form renumbers
     * the whole set from what its three fields hold — so on any paper somebody
     * has opened since, position 4 in the export is not row 4 here, and matching
     * on it would file one author's institution against another.
     *
     * @param  array<string, mixed>  $paper
     */
    protected function applyPaper(array $paper, AffiliationMatcher $matcher, AuthorAttacher $attacher): int
    {
        $names = $this->split((string) ($paper['all_authors'] ?? ''));
        $affiliations = $this->split((string) ($paper['all_author_affiliations'] ?? ''));
        $ids = $this->split((string) ($paper['all_author_ids'] ?? ''));

        // Without one affiliation per name the positions mean nothing, and a
        // line filed against the wrong author is worse than no line.
        if ($names === [] || count($names) !== count($affiliations)) {
            return 0;
        }

        $idsAlign = count($ids) === count($names);
        $answered = 0;

        foreach ($affiliations as $position => $affiliation) {
            $who = $attacher->locate($names[$position], $idsAlign ? $ids[$position] : null);

            if ($who === null) {
                continue;
            }

            $rows = DB::table('publication_authors')
                ->where('publication_id', $paper['existing_publication_id'])
                ->where('authorable_type', $who['type'])
                ->where('authorable_id', $who['id']);

            // Two writes rather than one COALESCE, so a row that already has an
            // affiliation recorded keeps it while still getting an answer to the
            // question this command exists for.
            (clone $rows)->whereNull('affiliation')->update(['affiliation' => $affiliation, 'updated_at' => now()]);

            $answered += (clone $rows)->whereNull('used_our_affiliation')->update([
                'used_our_affiliation' => $matcher->isOurs($affiliation),
                'updated_at' => now(),
            ]);
        }

        return $answered;
    }

    /**
     * Publications that came from the PD export or the old site.
     *
     * Neither carries an affiliation — there was no column for one — but both
     * are records of this university's own output, so what they do say is worth
     * reading. They are not read the same way, because they were not built the
     * same way:
     *
     *   - **PD**: every author on a PD publication counts, teachers and the
     *     external rows alike. The PD export is the university's own list of who
     *     wrote what here, and the names on it that did not match a teacher are
     *     students, staff and former colleagues — not outside collaborators who
     *     happened to be listed.
     *
     *   - **Old site alone**: only the teachers. That data is per teacher — a
     *     profile page listing that person's papers — so the teacher on it is
     *     certainly ours and the co-authors beside them are just names typed
     *     into a form, from anywhere.
     *
     * A publication carrying both flags is read as PD, the wider of the two: it
     * is in the PD export, and that is what the PD export means.
     *
     * Only rows where nothing is recorded yet, so a Scopus run that already said
     * "this one was written at Universiti Malaysia Pahang" is not overruled by
     * an assumption.
     */
    protected function fromLegacyImports(): int
    {
        $fromPd = Publication::query()->where('come_from_pd', true)->pluck('id');

        $oldSiteOnly = Publication::query()
            ->where('come_from_old_site', true)
            ->where('come_from_pd', false)
            ->pluck('id');

        if ($fromPd->isEmpty() && $oldSiteOnly->isEmpty()) {
            $this->line('No old-site or PD publications found.');

            return 0;
        }

        $this->info("PD: {$fromPd->count()} publication(s), every author.");
        $this->info("Old site only: {$oldSiteOnly->count()} publication(s), teachers.");

        $answered = 0;

        foreach ($fromPd->chunk(500) as $chunk) {
            $answered += DB::table('publication_authors')
                ->whereIn('publication_id', $chunk)
                ->whereNull('used_our_affiliation')
                ->update(['used_our_affiliation' => true, 'updated_at' => now()]);
        }

        foreach ($oldSiteOnly->chunk(500) as $chunk) {
            $answered += DB::table('publication_authors')
                ->whereIn('publication_id', $chunk)
                ->where('authorable_type', Teacher::class)
                ->whereNull('used_our_affiliation')
                ->update(['used_our_affiliation' => true, 'updated_at' => now()]);
        }

        return $answered;
    }

    /** @return array<int, string> */
    protected function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(';', $value)), 'strlen'));
    }
}
