<?php

namespace App\Services\Scopus;

use App\Helpers\Institution;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Support\Collection;

/**
 * Decides whether an affiliation line is ours, and which faculty it belongs to.
 *
 * The institution's name is written 27 different ways across one export —
 * "Daffodil International University" 4,551 times, but also "Daffodill",
 * "Daffodils", "Univeristy", "Univ.", and lower-cased. Matching on the literal
 * string would lose all of those.
 *
 * Two traps a plain "contains the name" test falls into:
 *
 *   - "Daffodil Smart City" (589 lines) is the campus address, not the
 *     institution. On its own it names no university.
 *   - "Daffodil Polytechnic Institute" and "Daffodil Institute of IT" are
 *     separate institutions in the same family, and counting their papers as
 *     ours would overstate the university's output.
 *
 * None of which is peculiar to Daffodil, so the answers now come from
 * [[Institution]] rather than from constants here: the same three questions get
 * asked wherever this is installed, only with different words in them.
 */
class AffiliationMatcher
{
    /**
     * Patterns an affiliation line counts as ours by, and the institutions that
     * carry the name without being us. Read once per run.
     *
     * @var array<int, string>
     */
    protected array $patterns;

    /** @var array<int, string> */
    protected array $notUs;

    /** @var array<string, array{0: string, 1: ?string}> */
    protected array $unitAliases;

    protected Collection $departments;

    protected Collection $faculties;

    /** Normalised name or code => Department. */
    protected array $departmentIndex = [];

    /** Normalised name or short name => Faculty. */
    protected array $facultyIndex = [];

    protected MatchingOptions $options;

    public function __construct(?MatchingOptions $options = null)
    {
        $this->options = $options ?? new MatchingOptions;

        $this->patterns = Institution::matchPatterns();
        $this->notUs = Institution::notUs();
        $this->unitAliases = Institution::unitAliases();

        $this->departments = Department::with('faculty')->get();
        $this->faculties = Faculty::all();

        foreach ($this->departments as $department) {
            $this->departmentIndex[$this->normalise((string) $department->name)] ??= $department;

            foreach ([$department->code, $department->short_name] as $alias) {
                if (filled($alias)) {
                    $this->departmentIndex[$this->normalise((string) $alias)] ??= $department;
                }
            }
        }

        foreach ($this->faculties as $faculty) {
            $this->facultyIndex[$this->normalise((string) $faculty->name)] ??= $faculty;

            if (filled($faculty->short_name)) {
                $this->facultyIndex[$this->normalise((string) $faculty->short_name)] ??= $faculty;
            }
        }
    }

    /** Is this one affiliation segment the institution's? */
    public function isOurs(string $segment): bool
    {
        foreach ($this->notUs as $other) {
            if (stripos($segment, $other) !== false) {
                // Counted only when the run was told to include the sister
                // institutions; otherwise their papers are not the university's.
                return $this->options->includeSisterInstitutions;
            }
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The faculty and department an affiliation segment points at.
     *
     * The rule the registrar's office asked for: when the department is
     * recognised, its faculty comes from our own tables rather than from what
     * Scopus wrote — our structure is the authority, and taking the faculty
     * from the file could contradict it. When only the faculty is recognised,
     * the department is left null for the reviewer.
     *
     * @return array{faculty: ?Faculty, department: ?Department, unit: ?string, source: string}
     */
    public function resolve(string $segment): array
    {
        $units = $this->unitsIn($segment);

        foreach ($units as $unit) {
            $key = $this->normalise($unit);

            if ($department = $this->departmentIndex[$key] ?? null) {
                return [
                    'faculty' => $department->faculty,
                    'department' => $department,
                    'unit' => $unit,
                    'source' => 'department',
                ];
            }
        }

        // Acronyms: "Computer Science and Engineering" as CSE.
        foreach ($units as $unit) {
            $initials = $this->initials($this->normalise($unit));

            if ($initials !== '' && ($department = $this->departmentIndex[$initials] ?? null)) {
                return [
                    'faculty' => $department->faculty,
                    'department' => $department,
                    'unit' => $unit,
                    'source' => 'department-acronym',
                ];
            }
        }

        foreach ($units as $unit) {
            $key = $this->normalise($unit);

            if ($faculty = $this->facultyIndex[$key] ?? null) {
                return ['faculty' => $faculty, 'department' => null, 'unit' => $unit, 'source' => 'faculty'];
            }
        }

        foreach ($units as $unit) {
            $mapped = $this->unitAliases[mb_strtolower(trim($unit))] ?? null;

            if ($mapped === null) {
                continue;
            }

            [$facultyShort, $departmentCode] = $mapped;

            $faculty = $this->faculties->firstWhere('short_name', $facultyShort);
            $department = $departmentCode
                ? $this->departments->firstWhere('code', $departmentCode)
                : null;

            return [
                'faculty' => $department?->faculty ?? $faculty,
                'department' => $department,
                'unit' => $unit,
                'source' => 'hand-mapped',
            ];
        }

        return [
            'faculty' => null,
            'department' => null,
            'unit' => $units[0] ?? null,
            'source' => 'none',
        ];
    }

    /**
     * The department- and faculty-looking parts of an affiliation segment.
     *
     * Scopus writes them as free comma-separated text, in no fixed order:
     * "Murshid M.M., Daffodil International University, Department of Computer
     * Science and Engineering, Dhaka, Bangladesh".
     *
     * @return array<int, string>
     */
    public function unitsIn(string $segment): array
    {
        $units = [];

        foreach (explode(',', $segment) as $piece) {
            $piece = trim($piece);

            if (preg_match('/^(Department|Dept\.?|Faculty|School|Institute|Centre|Center|Division)\b/i', $piece)) {
                $units[] = $piece;
            }
        }

        return $units;
    }

    /** Lower-cased, with the words that carry no distinguishing weight removed. */
    protected function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['&'], [' and '], $value);
        $value = preg_replace('/\b(department|dept|faculty|school|division|of|the|and|for|at)\b/', ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    protected function initials(string $normalised): string
    {
        $words = array_filter(explode(' ', $normalised));

        if (count($words) < 2) {
            return '';
        }

        return implode('', array_map(fn ($word) => $word[0], $words));
    }
}
