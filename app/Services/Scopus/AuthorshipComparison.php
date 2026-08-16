<?php

namespace App\Services\Scopus;

use App\Models\Author;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * For a paper we already hold, how our record of who wrote it differs from
 * Scopus's.
 *
 * Finding the paper is only half the answer. Our copy may credit nobody, or
 * credit an external name where Scopus names one of our teachers, or disagree
 * about which of them was the first author — and first authorship is not a
 * detail here, it decides the share of the incentive.
 *
 * Three things are reported, and each is a different kind of problem:
 *
 *   - **missing** — Scopus names one of our people on the paper and our copy
 *     does not credit them at all.
 *   - **first author** — Scopus lists somebody first and our copy marks
 *     somebody else as first author.
 *   - **extra** — our copy credits a teacher Scopus does not name at Daffodil.
 *     Often legitimate, so it is stated rather than flagged.
 */
class AuthorshipComparison
{
    public const CLEAN = 'clean';

    public const MISSING_AUTHORS = 'missing-authors';

    public const FIRST_AUTHOR_DIFFERS = 'first-author-differs';

    public const NOBODY_CREDITED = 'nobody-credited';

    /** publication id => [['type' => , 'id' => , 'name' => , 'role' => ]] */
    protected array $credited = [];

    public function __construct(protected RecordResolver $resolver) {}

    /**
     * Loads who our copies of these publications credit, in one pass.
     *
     * @param  Collection<int, array<string, mixed>>  $papers
     */
    public function prepare(Collection $papers): void
    {
        $ids = $papers
            ->filter(fn ($paper) => $paper['publication'] !== null)
            ->map(fn ($paper) => $paper['publication']->id)
            ->all();

        if ($ids === []) {
            return;
        }

        $pivots = DB::table('publication_authors')
            ->whereIn('publication_id', $ids)
            ->orderBy('sort_order')
            ->get();

        $teachers = Teacher::whereIn('id', $pivots->where('authorable_type', Teacher::class)
            ->pluck('authorable_id'))->get()->keyBy('id');

        $authors = Author::whereIn('id', $pivots->where('authorable_type', Author::class)
            ->pluck('authorable_id'))->get()->keyBy('id');

        foreach ($pivots as $pivot) {
            $isTeacher = $pivot->authorable_type === Teacher::class;

            $model = $isTeacher
                ? $teachers->get($pivot->authorable_id)
                : $authors->get($pivot->authorable_id);

            $this->credited[$pivot->publication_id][] = [
                'type' => $isTeacher ? 'teacher' : 'author',
                'id' => (int) $pivot->authorable_id,
                'name' => $isTeacher ? ($model?->full_name ?? 'Unknown') : ($model?->name ?? 'Unknown'),
                'role' => (string) $pivot->author_role,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $paper  a row from ScopusAnalysis
     * @param  Collection<string, array<string, mixed>>  $people  resolved people, keyed as in the paper
     * @return array<string, mixed>
     */
    public function compare(array $paper, Collection $people): array
    {
        $publication = $paper['publication'];

        if ($publication === null) {
            return $this->result(self::CLEAN, [], null, null, []);
        }

        $ours = $this->credited[$publication->id] ?? [];

        $ourTeacherIds = collect($ours)->where('type', 'teacher')->pluck('id')->all();
        $ourAuthorIds = collect($ours)->where('type', 'author')->pluck('id')->all();

        $missing = [];

        foreach ($paper['diu_authors'] as $author) {
            $person = $people[$author['key']] ?? null;

            if ($person === null) {
                continue;
            }

            $match = $person['match'];

            // Only somebody we could actually name can be called missing. An
            // unresolved name is a different problem, reported elsewhere.
            if ($match['teacher'] && ! in_array($match['teacher']->id, $ourTeacherIds, true)) {
                $missing[] = $match['teacher']->full_name . ' (teacher)';
            } elseif ($match['author'] && ! in_array($match['author']->id, $ourAuthorIds, true)) {
                $missing[] = $match['author']->name . ' (external)';
            }
        }

        // Scopus lists authors in order; the first position is the first author.
        $scopusFirst = $paper['diu_authors'][0]['name'] ?? null;
        $scopusFirstIsDiu = ($paper['first_position_is_ours'] ?? false);

        $ourFirst = collect($ours)->firstWhere('role', 'first');

        $firstDiffers = false;

        if ($scopusFirstIsDiu && $ourFirst && $scopusFirst) {
            $firstDiffers = $this->resolver->nameKey($ourFirst['name']) !== $this->resolver->nameKey($scopusFirst);
        }

        $extra = collect($ours)
            ->where('type', 'teacher')
            ->reject(function (array $credited) use ($paper, $people) {
                foreach ($paper['diu_authors'] as $author) {
                    $match = $people[$author['key']]['match'] ?? null;

                    if ($match && $match['teacher'] && $match['teacher']->id === $credited['id']) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('name')
            ->all();

        $status = match (true) {
            $ours === [] => self::NOBODY_CREDITED,
            $firstDiffers => self::FIRST_AUTHOR_DIFFERS,
            $missing !== [] => self::MISSING_AUTHORS,
            default => self::CLEAN,
        };

        return $this->result(
            $status,
            $missing,
            $scopusFirstIsDiu ? $scopusFirst : null,
            $ourFirst['name'] ?? null,
            $extra,
        );
    }

    /**
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $extra
     * @return array<string, mixed>
     */
    protected function result(string $status, array $missing, ?string $scopusFirst, ?string $ourFirst, array $extra): array
    {
        return [
            'status' => $status,
            'missing' => $missing,
            'scopus_first_author' => $scopusFirst,
            'our_first_author' => $ourFirst,
            'extra_here' => $extra,
            'note' => match ($status) {
                self::NOBODY_CREDITED => 'We hold this paper but credit nobody on it.',
                self::FIRST_AUTHOR_DIFFERS => 'Scopus lists ' . $scopusFirst . ' first; we mark ' . $ourFirst . ' as first author.',
                self::MISSING_AUTHORS => count($missing) . ' author(s) named by Scopus are not credited here: ' . implode('; ', $missing),
                default => '',
            },
        ];
    }

    /** How our copy of every credited author reads, for the workbook. */
    public function creditedOn(?Publication $publication): string
    {
        if ($publication === null) {
            return '';
        }

        return collect($this->credited[$publication->id] ?? [])
            ->map(fn ($credited) => $credited['name'] . ' (' . $this->roleLabel($credited['role']) . ')')
            ->implode('; ');
    }

    protected function roleLabel(string $role): string
    {
        return match ($role) {
            'first' => '1st',
            'corresponding' => 'corresponding',
            'co_author' => 'co-author',
            default => $role,
        };
    }
}
