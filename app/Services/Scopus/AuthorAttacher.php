<?php

namespace App\Services\Scopus;

use App\Models\Author;
use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Files a paper's author list against a publication.
 *
 * One copy, used by both importers. There used to be two, a hundred and seventy
 * lines each and meant to be identical, and they drifted exactly as far as you
 * would expect: the online path learned to record where each author was writing
 * from and the workbook path never did, so the same review checked in Excel
 * produced rows the browser would not have produced. The comments in each kept
 * insisting the other did the same thing. Now one of them does.
 *
 * What it writes per author, beyond the link itself:
 *
 *   - the affiliation line the export printed against them, and whether that
 *     line is our own institution's;
 *   - their Scopus identifier, bound to whoever they resolved to;
 *   - the role, which is the part position alone was getting wrong.
 */
class AuthorAttacher
{
    public function __construct(
        protected RecordResolver $resolver,
        protected AffiliationMatcher $affiliations,
    ) {}

    /** Both matchers hold indexes built for one set of rules, so a run gets its own. */
    public static function for(?MatchingOptions $options = null): self
    {
        $options ??= new MatchingOptions;

        return new self(new RecordResolver($options), new AffiliationMatcher($options));
    }

    /**
     * @param  array<int, int>  $correspondingPositions  indexes into the author list
     * @param  array<array-key, array<string, mixed>>  $people  the run's own people, as the analysis keyed them
     */
    public function attach(
        Publication $publication,
        string $allAuthors,
        string $allAuthorIds = '',
        string $allAuthorAffiliations = '',
        array $correspondingPositions = [],
        array $people = [],
    ): void {
        $entries = $this->parse($allAuthors, $allAuthorIds, $allAuthorAffiliations);

        if ($entries === []) {
            return;
        }

        $resolved = [];

        foreach ($entries as $position => $entry) {
            $resolved[$position] = $this->resolveOne($entry, $people);
        }

        $corresponding = $this->pickCorresponding($correspondingPositions, $resolved);

        // What our own copy already says, so nothing established here is
        // overwritten by a run that came along later.
        $existing = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->get()
            ->keyBy(fn ($row) => $row->authorable_type . ':' . $row->authorable_id . ':' . $row->author_role);

        /*
         * A corresponding author our own copy already names is left alone.
         *
         * Somebody decided that, whether a reviewer or an earlier run, and a
         * later run filling in what is missing is not the same thing as a later
         * run overruling what is there.
         */
        $hasCorresponding = $existing->contains(fn ($row) => $row->author_role === 'corresponding');

        foreach ($resolved as $position => $author) {
            $isCorresponding = $position === $corresponding && ! $hasCorresponding;

            /*
             * The corresponding author keeps first authorship and gains a second
             * row; anybody else simply is the corresponding author.
             *
             * "First" is a fact about the paper that nothing else records, so it
             * cannot be traded away — 512 of the 1,791 corresponding authors in
             * the July export are also the first-listed, and one row can hold
             * one enum value. "Co-author" is the default everybody starts as, so
             * replacing it costs nothing.
             */
            if ($position === 0) {
                $this->write($publication, $author, 'first', $position, $existing);

                if ($isCorresponding) {
                    $this->write($publication, $author, 'corresponding', $position, $existing);
                }

                continue;
            }

            if (! $isCorresponding) {
                $this->write($publication, $author, 'co_author', $position, $existing);

                continue;
            }

            /*
             * Promoted rather than duplicated.
             *
             * On a publication we already held, this person is probably already
             * on it as a co-author — filed there back when position was all
             * anyone read. That row is the same link, and the only thing wrong
             * with it is its role, so it is moved rather than joined by a second
             * row naming the same person twice.
             */
            $promoted = $existing->has($author['type'] . ':' . $author['id'] . ':co_author')
                && DB::table('publication_authors')
                    ->where('publication_id', $publication->id)
                    ->where('authorable_type', $author['type'])
                    ->where('authorable_id', $author['id'])
                    ->where('author_role', 'co_author')
                    ->update(['author_role' => 'corresponding', 'updated_at' => now()]) > 0;

            if ($promoted) {
                $this->fillBlanks($publication, $author, 'corresponding');

                continue;
            }

            $this->write($publication, $author, 'corresponding', $position, $existing);
        }
    }

    /**
     * The author list as positions, with whatever the export lined up beside them.
     *
     * @return array<int, array{name: string, scopus_id: ?string, affiliation: ?string}>
     */
    protected function parse(string $allAuthors, string $allAuthorIds, string $allAuthorAffiliations): array
    {
        $names = $this->split($allAuthors);

        if ($names === []) {
            return [];
        }

        /*
         * The parallel lists are trusted only when there is exactly one entry
         * for each name. An identifier is unique and binding one to the wrong
         * person is not something a later run can undo by itself; an affiliation
         * filed against the wrong author would put somebody at an institution
         * they have never been near.
         */
        $ids = $this->split($allAuthorIds);
        $idsAlign = $ids !== [] && count($ids) === count($names);

        $affiliations = $this->split($allAuthorAffiliations);
        $affiliationsAlign = $affiliations !== [] && count($affiliations) === count($names);

        $entries = [];

        foreach ($names as $position => $entry) {
            $name = $entry;
            $scopusId = $idsAlign ? ($ids[$position] ?? null) : null;

            // A workbook column still carries "Name (57190123)", so the id is
            // taken from the name too when the parallel list is not there.
            if (preg_match('/^(.*?)\s*\((\d+)\)$/', $entry, $matches)) {
                $name = trim($matches[1]);
                $scopusId ??= trim($matches[2]);
            }

            $entries[$position] = [
                'name' => static::formatAuthorName($name),
                'scopus_id' => filled($scopusId) ? $scopusId : null,
                'affiliation' => $affiliationsAlign ? ($affiliations[$position] ?? null) : null,
            ];
        }

        return $entries;
    }

    /**
     * Who an entry is, and what the export said about where they were writing from.
     *
     * @param  array{name: string, scopus_id: ?string, affiliation: ?string}  $entry
     * @param  array<array-key, array<string, mixed>>  $people
     * @return array{type: class-string, id: int, affiliation: ?string, used_ours: ?bool}
     */
    protected function resolveOne(array $entry, array $people): array
    {
        $resolved = $this->resolver->resolveAuthor($entry['name'], null, null, [], $entry['scopus_id']);

        /*
         * Whether this line is our own institution's, for this paper alone.
         *
         * Left null when the export's columns did not line up, because then
         * there is no line to read — and "we do not know" has to stay
         * distinguishable from "no, they were somewhere else". A teacher who
         * joined last year has papers written under a previous employer, and the
         * website counts a paper as the university's on exactly this answer.
         */
        $usedOurs = $entry['affiliation'] === null
            ? null
            : $this->affiliations->isOurs($entry['affiliation']);

        if ($resolved['kind'] === 'teacher' && $resolved['teacher'] !== null) {
            $teacher = $resolved['teacher'];

            ScopusAuthorId::bindTo($teacher, $entry['scopus_id'], ScopusAuthorId::SOURCE_REVIEW, Auth::id());

            return [
                'type' => Teacher::class,
                'id' => $teacher->id,
                'affiliation' => $entry['affiliation'],
                'used_ours' => $usedOurs,
                'is_teacher' => true,
            ];
        }

        $author = $this->externalAuthor($entry['name'], $entry['scopus_id']);

        ScopusAuthorId::bindTo($author, $entry['scopus_id'], ScopusAuthorId::SOURCE_REVIEW, Auth::id());

        /*
         * The author-level standing, which is a different question from the one
         * above: has this person *ever* written as one of ours, across every
         * paper we have seen. It decides whether a row in the authors table is a
         * merge waiting to happen or somebody who belongs there permanently.
         *
         * Only stamped when the run actually knew. A payload with no people,
         * which is what the older ones are, must not be read as "none of these
         * were ours".
         */
        if ($people !== []) {
            [$everOurs, $named] = $this->standingOf($entry['name'], $people);

            $author->recordAffiliationStanding($everOurs, $named);
        }

        return [
            'type' => Author::class,
            'id' => $author->id,
            'affiliation' => $entry['affiliation'],
            'used_ours' => $usedOurs,
            'is_teacher' => false,
        ];
    }

    /**
     * The one corresponding author to record, out of however many were named.
     *
     * A fifth of the papers in the July export name more than one, and the
     * column holds a single value, so the rest cannot be written down. Which one
     * survives is not arbitrary: the point of recording it is to say who at this
     * university corresponded, so one of ours outranks a collaborator, and one
     * of ours writing under our own name outranks one who was not. Failing all
     * of that, the earliest-listed.
     *
     * @param  array<int, int>  $positions
     * @param  array<int, array<string, mixed>>  $resolved
     */
    protected function pickCorresponding(array $positions, array $resolved): ?int
    {
        $known = array_values(array_filter($positions, fn (int $p) => isset($resolved[$p])));

        if ($known === []) {
            return null;
        }

        usort($known, function (int $a, int $b) use ($resolved) {
            $rank = fn (int $p) => [
                $resolved[$p]['is_teacher'] ? 0 : 1,
                ($resolved[$p]['used_ours'] === true) ? 0 : 1,
                $p,
            ];

            return $rank($a) <=> $rank($b);
        });

        return $known[0];
    }

    /**
     * One link, written only where our own copy has nothing to say.
     *
     * @param  array{type: class-string, id: int, affiliation: ?string, used_ours: ?bool}  $author
     * @param  \Illuminate\Support\Collection<string, object>  $existing
     */
    protected function write(Publication $publication, array $author, string $role, int $position, $existing): void
    {
        if ($existing->has($author['type'] . ':' . $author['id'] . ':' . $role)) {
            $this->fillBlanks($publication, $author, $role);

            return;
        }

        DB::table('publication_authors')->insert([
            'publication_id' => $publication->id,
            'authorable_type' => $author['type'],
            'authorable_id' => $author['id'],
            'author_role' => $role,
            'sort_order' => $position,
            'affiliation' => $author['affiliation'],
            'used_our_affiliation' => $author['used_ours'],
            'incentive_amount' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * What a row already here is missing, and nothing else.
     *
     * A second run must not undo a correction somebody made by hand, so only
     * columns still standing at null are written.
     *
     * @param  array{affiliation: ?string, used_ours: ?bool}  $author
     */
    protected function fillBlanks(Publication $publication, array $author, string $role): void
    {
        $fill = [];

        if ($author['affiliation'] !== null) {
            $fill['affiliation'] = $author['affiliation'];
        }

        if ($author['used_ours'] !== null) {
            $fill['used_our_affiliation'] = $author['used_ours'];
        }

        if ($fill === []) {
            return;
        }

        foreach ($fill as $column => $value) {
            DB::table('publication_authors')
                ->where('publication_id', $publication->id)
                ->where('authorable_type', $author['type'])
                ->where('authorable_id', $author['id'])
                ->where('author_role', $role)
                ->whereNull($column)
                ->update([$column => $value, 'updated_at' => now()]);
        }
    }

    /**
     * Who a written name already corresponds to here, without creating anybody.
     *
     * The read-only half of resolveOne, for going back over runs that have
     * already been applied: the rows exist, and what is wanted is which of them
     * this position in the author list is.
     *
     * @return array{type: class-string, id: int}|null
     */
    public function locate(string $name, ?string $scopusId = null): ?array
    {
        $name = static::formatAuthorName($name);
        $resolved = $this->resolver->resolveAuthor($name, null, null, [], $scopusId);

        if ($resolved['kind'] === 'teacher' && $resolved['teacher'] !== null) {
            return ['type' => Teacher::class, 'id' => $resolved['teacher']->id];
        }

        if (filled($scopusId)) {
            $byId = Author::whereHas('scopusAuthorIds', fn ($q) => $q->where('scopus_author_id', $scopusId))->first();

            if ($byId) {
                return ['type' => Author::class, 'id' => $byId->id];
            }
        }

        // The placeholder address too, which is what actually collapses two
        // spellings of one name onto a single row.
        $author = Author::where('name', $name)->first()
            ?? Author::where('email', Author::placeholderEmail($name))->first();

        return $author ? ['type' => Author::class, 'id' => $author->id] : null;
    }

    /** The same order of preference the resolver uses, then a new row. */
    protected function externalAuthor(string $name, ?string $scopusId): Author
    {
        if (filled($scopusId)) {
            $byId = Author::whereHas('scopusAuthorIds', fn ($q) => $q->where('scopus_author_id', $scopusId))->first();

            if ($byId) {
                return $byId;
            }
        }

        return Author::where('name', $name)->first() ?? Author::createExternal($name);
    }

    /**
     * Whether this author ever carried our affiliation in this run, and whose
     * they carried if not.
     *
     * The run's people are keyed by Scopus identifier and the author list has
     * those identifiers stripped out of it, so matching has to go through the
     * name. nameKey reduces both sides to the same distinguishing tokens, so
     * "Murshid, Md Mahmud" as the export writes it meets "Md Mahmud Murshid" as
     * we would.
     *
     * @param  array<array-key, array<string, mixed>>  $people
     * @return array{0: bool, 1: ?string}
     */
    protected function standingOf(string $name, array $people): array
    {
        $this->byName ??= $this->peopleByName($people);

        $person = $this->byName[$this->resolver->nameKey($name)] ?? null;

        if ($person === null) {
            // Never in the run's people at all: the export named somebody
            // else's institution against every paper we saw them on.
            return [false, null];
        }

        $standing = $person['standing'] ?? ScopusAnalysis::AFFILIATED_HERE;

        if ($standing === ScopusAnalysis::AFFILIATED_HERE) {
            return [true, null];
        }

        // In the people list, but only because a name or a recorded identifier
        // put them there — the affiliation line still named somewhere else.
        return [false, implode('; ', $person['other_affiliations'] ?? []) ?: null];
    }

    /** @var array<string, array<string, mixed>>|null  built on first use */
    protected ?array $byName = null;

    /**
     * @param  array<array-key, array<string, mixed>>  $people
     * @return array<string, array<string, mixed>>
     */
    protected function peopleByName(array $people): array
    {
        $indexed = [];

        foreach ($people as $person) {
            $key = $this->resolver->nameKey((string) ($person['name'] ?? ''));

            if ($key !== '') {
                $indexed[$key] ??= $person;
            }
        }

        return $indexed;
    }

    /** @return array<int, string> */
    protected function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(';', $value)), 'strlen'));
    }

    public static function formatAuthorName(string $name): string
    {
        $name = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $name));

        if (str_contains($name, ',')) {
            $parts = array_map('trim', explode(',', $name, 2));

            if (count($parts) === 2 && filled($parts[0]) && filled($parts[1])) {
                return $parts[1] . ' ' . $parts[0];
            }
        }

        return $name;
    }
}
