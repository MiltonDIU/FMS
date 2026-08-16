<?php

namespace App\Services\Scopus;

use App\Helpers\Institution;
use App\Models\Author;
use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use App\Models\User;

/**
 * Works out what each Scopus record and each Scopus author corresponds to here.
 *
 * Nothing is written. Every answer carries how it was reached and how much it
 * can be trusted, because most of them will be wrong often enough that a person
 * has to look — 97.2% of the papers in the July export are already in the
 * system, and of 1,257 DIU-affiliated authors only 2.9% can be matched by
 * something as certain as an email address.
 *
 * The order of preference is the one the registrar's office asked for:
 * Scopus ID first once we start storing them, then email, then the name — and
 * a name match is never better than a suggestion.
 */
class RecordResolver
{
    public const CERTAIN = 'certain';

    public const LIKELY = 'likely';

    public const AMBIGUOUS = 'ambiguous';

    public const NONE = 'none';

    /** Normalised title => publication. */
    protected array $publicationsByTitle = [];

    /** Lower-cased DOI => publication. */
    protected array $publicationsByDoi = [];

    /** Scopus EID => publication. */
    protected array $publicationsByEid = [];

    /** Normalised name tokens => [teachers]. */
    protected array $teachersByName = [];

    /** Normalised name tokens => [authors]. */
    protected array $authorsByName = [];

    /** Lower-cased email => user id. */
    protected array $usersByEmail = [];

    /** Lower-cased email => teacher. */
    protected array $teachersByEmail = [];

    /** Lower-cased email => author. */
    protected array $authorsByEmail = [];

    /** Scopus author id => ['teacher'|'author', model]. */
    protected array $byScopusId = [];

    /**
     * Words that carry no distinguishing weight in a Bangladeshi name.
     *
     * Without this, "Md." alone matches half the university. With it, a handful
     * of names reduce to a single token and go ambiguous instead — which is the
     * honest answer: "Al-Amin, Md." really does match 21 of our teachers.
     */
    protected const FILLER = ['md', 'mohammad', 'mohammed', 'muhammad', 'mst', 'most',
        'mrs', 'mr', 'ms', 'dr', 'professor', 'prof', 'engr', 'phd', 'bin', 'abdul'];

    protected MatchingOptions $options;

    public function __construct(?MatchingOptions $options = null)
    {
        $this->options = $options ?? new MatchingOptions;

        // come_from_* travel with the record because the workbook reports where
        // our copy came from beside what Scopus says about it.
        foreach (Publication::query()
            ->select('id', 'title', 'publication_year', 'doi', 'scopus_eid', 'come_from_old_site', 'come_from_pd')
            ->cursor() as $publication) {
            $key = $this->normaliseTitle((string) $publication->title);

            if ($key !== '') {
                $this->publicationsByTitle[$key] ??= $publication;
            }

            if (filled($publication->doi)) {
                $this->publicationsByDoi[mb_strtolower(trim($publication->doi))] ??= $publication;
            }

            if (filled($publication->scopus_eid)) {
                $this->publicationsByEid[trim($publication->scopus_eid)] ??= $publication;
            }
        }

        foreach (Teacher::with('user', 'department.faculty')->get() as $teacher) {
            $key = $this->nameKey($teacher->full_name);

            if ($key !== '') {
                $this->teachersByName[$key][] = $teacher;
            }

            foreach ([$teacher->user?->email, $teacher->secondary_email] as $email) {
                if (filled($email)) {
                    $this->teachersByEmail[mb_strtolower(trim($email))] ??= $teacher;
                }
            }
        }

        foreach (Author::with('mergedIntoTeacher')->get() as $author) {
            $key = $this->nameKey((string) $author->name);

            if ($key !== '') {
                $this->authorsByName[$key][] = $author;
            }

            if (filled($author->email)) {
                $this->authorsByEmail[mb_strtolower(trim($author->email))] ??= $author;
            }
        }

        foreach (User::query()->select('id', 'email')->cursor() as $user) {
            if (filled($user->email)) {
                $this->usersByEmail[mb_strtolower($user->email)] = $user->id;
            }
        }

        // Identifiers recorded by earlier reviews. Empty on the first run, and
        // the whole point of recording them is that it does not stay that way.
        foreach (ScopusAuthorId::with('authorable')->get() as $recorded) {
            $owner = $recorded->authorable;

            if ($owner instanceof Teacher) {
                $this->byScopusId[$recorded->scopus_author_id] = ['teacher', $owner];
            } elseif ($owner instanceof Author) {
                $this->byScopusId[$recorded->scopus_author_id] = ['author', $owner];
            }
        }
    }

    /**
     * The publication this Scopus record already is, if we hold it.
     *
     * The identifiers first and the title last, because the title is the
     * weakest of the three: a trailing full stop, a subtitle punctuated
     * differently, or a "Retraction notice to ..." prefix is enough to miss a
     * paper we plainly have.
     *
     * @return array{publication: ?Publication, confidence: string, basis: string}
     */
    public function resolvePublication(string $title, ?string $doi = null, ?string $eid = null): array
    {
        if ($this->options->matchPublicationsByIdentifier) {
            if (filled($eid) && ($publication = $this->publicationsByEid[trim($eid)] ?? null)) {
                return ['publication' => $publication, 'confidence' => self::CERTAIN, 'basis' => 'eid'];
            }

            if (filled($doi) && ($publication = $this->publicationsByDoi[mb_strtolower(trim($doi))] ?? null)) {
                return ['publication' => $publication, 'confidence' => self::CERTAIN, 'basis' => 'doi'];
            }
        }

        $publication = $this->publicationsByTitle[$this->normaliseTitle($title)] ?? null;

        return [
            'publication' => $publication,
            'confidence' => $publication ? self::LIKELY : self::NONE,
            'basis' => $publication ? 'title' : 'none',
        ];
    }

    /**
     * Who a Scopus author is, here.
     *
     * @param  string  $name  as Scopus writes it: "Alom, Md. Masud"
     * @param  ?string  $email  the correspondence address, when it is theirs
     * @param  ?int  $departmentId  the department Scopus named for them
     * @param  array<int, int>  $paperTeacherIds  teachers already credited on their papers here
     * @param  ?string  $scopusId  the author identifier Scopus gave for them
     * @return array{kind: string, teacher: ?Teacher, author: ?Author, confidence: string, basis: string, candidates: int}
     */
    /**
     * Whether this Scopus identifier is already recorded against somebody here.
     *
     * Asked before the affiliation is even looked at. An identifier we have
     * bound names one profile and one person, so where Scopus says they were
     * writing from cannot make them somebody else — a teacher who published
     * under a previous employer is still that teacher.
     */
    public function hasRecordedScopusId(?string $scopusId): bool
    {
        return filled($scopusId) && isset($this->byScopusId[trim($scopusId)]);
    }

    public function resolveAuthor(string $name, ?string $email = null, ?int $departmentId = null, array $paperTeacherIds = [], ?string $scopusId = null): array
    {
        /*
         * The identifier first, because it is the only basis that cannot be
         * wrong: one Scopus profile is one person, so a recorded identifier
         * settles the question outright and no name is compared at all.
         */
        if ($this->options->matchByScopusId && filled($scopusId)) {
            [$kind, $owner] = $this->byScopusId[trim($scopusId)] ?? [null, null];

            if ($kind === 'teacher') {
                return $this->answer('teacher', $owner, null, self::CERTAIN, 'scopus id');
            }

            if ($kind === 'author') {
                return $this->answer('author', null, $owner, self::CERTAIN, 'scopus id');
            }
        }

        $email = filled($email) ? mb_strtolower(trim($email)) : null;

        if ($this->options->matchByEmail && $email !== null) {
            if ($teacher = $this->teachersByEmail[$email] ?? null) {
                return $this->answer('teacher', $teacher, null, self::CERTAIN, 'email');
            }

            if ($author = $this->authorsByEmail[$email] ?? null) {
                return $this->answer('author', null, $author, self::CERTAIN, 'email');
            }
        }

        if (! $this->options->matchByName) {
            return $this->answer($this->studentOrUnknown($email), null, null, self::NONE, 'none');
        }

        $key = $this->nameKey($name);

        $teachers = $this->teachersByName[$key] ?? [];

        if (count($teachers) === 1) {
            return $this->answer('teacher', $teachers[0], null, self::LIKELY, 'name');
        }

        if (count($teachers) > 1) {
            /*
             * Several people carry this name. Rather than picking one — which is
             * how a paper lands on the wrong profile — narrow the field with
             * something the file already told us.
             */
            if ($this->options->usePaperAuthorsTiebreak && $paperTeacherIds) {
                $onTheirPapers = array_values(array_filter(
                    $teachers,
                    fn (Teacher $teacher) => in_array($teacher->id, $paperTeacherIds, true),
                ));

                if (count($onTheirPapers) === 1) {
                    return $this->answer('teacher', $onTheirPapers[0], null, self::LIKELY, 'name + paper authors', count($teachers));
                }
            }

            if ($this->options->useDepartmentTiebreak && $departmentId !== null) {
                $inDepartment = array_values(array_filter(
                    $teachers,
                    fn (Teacher $teacher) => $teacher->department_id === $departmentId,
                ));

                if (count($inDepartment) === 1) {
                    return $this->answer('teacher', $inDepartment[0], null, self::LIKELY, 'name + department', count($teachers));
                }
            }

            return $this->answer('teacher', null, null, self::AMBIGUOUS, 'name', count($teachers));
        }

        $authors = $this->authorsByName[$key] ?? [];

        if (count($authors) === 1) {
            $author = $authors[0];

            // An author already merged into a teacher answers as that teacher:
            // the papers belong there now, and offering the retired name again
            // would recreate the split we just closed.
            if ($author->merged_into_teacher_id && $author->mergedIntoTeacher) {
                return $this->answer('teacher', $author->mergedIntoTeacher, null, self::LIKELY, 'merged-author');
            }

            return $this->answer('author', null, $author, self::LIKELY, 'name');
        }

        if (count($authors) > 1) {
            return $this->answer('author', null, null, self::AMBIGUOUS, 'name', count($authors));
        }

        return $this->answer($this->studentOrUnknown($email), null, null, self::NONE, 'none');
    }

    protected function studentOrUnknown(?string $email): string
    {
        return $this->options->flagStudentsByEmail && $this->looksLikeStudent($email)
            ? 'student'
            : 'unknown';
    }

    /**
     * Whether an address looks like a student's.
     *
     * DIU writes staff as name.department and students with their admission
     * number — murshid15-6122@diu.edu.bd against kabir.cse@diu.edu.bd — so a
     * digit in the local part is the whole rule there. It is a convention, not
     * a law, and another institution's will differ, so both the rule and the
     * addresses it applies to come from [[Institution]]; an empty rule turns
     * this off entirely.
     *
     * Either way it is offered as a suggestion in the workbook rather than
     * acted on: a member of staff could have a digit too.
     */
    public function looksLikeStudent(?string $email): bool
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return false;
        }

        $pattern = Institution::studentEmailPattern();

        if ($pattern === null) {
            return false;
        }

        // Outside addresses say nothing about how this institution numbers its
        // students, so the rule is not applied to them. No configured domains
        // means the rule applies everywhere, which is what it did before.
        $domains = Institution::emailDomains();

        if ($domains !== []) {
            $domain = mb_strtolower(substr(strrchr($email, '@') ?: '', 1));

            $ours = array_filter(
                $domains,
                fn (string $ourDomain) => $domain === $ourDomain || str_ends_with($domain, '.' . $ourDomain),
            );

            if ($ours === []) {
                return false;
            }
        }

        return (bool) preg_match($pattern, strstr($email, '@', true));
    }

    /** @return array<string, mixed> */
    protected function answer(string $kind, ?Teacher $teacher, ?Author $author, string $confidence, string $basis, int $candidates = 0): array
    {
        return compact('kind', 'teacher', 'author', 'confidence', 'basis', 'candidates');
    }

    /**
     * Candidate teachers for a name that matched more than one, or none.
     *
     * Offered so a reviewer has something to choose between rather than a blank
     * cell — "Al-Amin, Md." matches 21 teachers, and the useful thing to show is
     * those 21, not the fact that we gave up.
     *
     * @return \Illuminate\Support\Collection<int, Teacher>
     */
    public function candidatesFor(string $name): \Illuminate\Support\Collection
    {
        $exact = $this->teachersByName[$this->nameKey($name)] ?? [];

        if ($exact) {
            return collect($exact);
        }

        /*
         * Nothing matched the whole name, so try each word in it separately —
         * longest first, since a family name carries more than an initial, but
         * every word gets a turn. Trying only the longest missed the obvious
         * case: an unfamiliar given name beside a family name we do hold.
         *
         * Loose on purpose. This is a list for a person to choose from, not a
         * match, and it is only offered where the matcher already gave up.
         */
        $words = array_filter(explode(' ', $this->nameKey($name)), fn ($word) => mb_strlen($word) > 2);

        usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $found = collect();

        foreach ($words as $probe) {
            $found = $found->concat(
                collect($this->teachersByName)
                    ->filter(fn ($teachers, $key) => in_array($probe, explode(' ', $key), true))
                    ->flatten()
            );

            if ($found->count() >= 8) {
                break;
            }
        }

        return $found->unique(fn (Teacher $teacher) => $teacher->id)->take(8)->values();
    }

    /** Punctuation and case removed, so two spellings of a title compare equal. */
    public function normaliseTitle(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $title))));
    }

    /**
     * A name reduced to its distinguishing words, sorted.
     *
     * Sorted because Scopus writes "Hossain, Mohammad Reyad" where we hold
     * "Mohammad Reyad Hossain" — the same person, the same words, a different
     * order. Sorting makes the two identical without any reordering rules.
     */
    public function nameKey(string $name): string
    {
        $name = preg_replace('/\(\d+\)/', ' ', $name);
        $name = mb_strtolower(str_replace(['.', ',', '-', '_'], ' ', $name));

        $words = collect(preg_split('/\s+/', $name))
            ->map(fn ($word) => trim($word))
            ->filter(fn ($word) => mb_strlen($word) > 1 && ! in_array($word, self::FILLER, true))
            ->unique()
            ->sort()
            ->values();

        return $words->implode(' ');
    }
}
