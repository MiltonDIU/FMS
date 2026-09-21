<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * What the search box on the public site matches a teacher against.
 *
 * This is the whole list, and it is the same list everywhere. Every frontend
 * search — the directory on the home and faculty pages, the one on a department
 * page, and ?q= on the public teachers API — calls this and nothing else, so
 * typing the same thing into any of them finds the same people.
 *
 * It did not, before. The same `orWhere` chain was copied into all three and
 * each copy had drifted: the directory matched faculty name and short name, the
 * department page did not, and the API matched neither. A visitor who found
 * somebody by their department on one page and not on the next was reading two
 * different searches that looked identical.
 *
 * Nothing is passed in, deliberately. An argument for "which columns this
 * caller wants" is how three lists became three different lists; the callers
 * join different tables for their own reasons, so the relations are reached
 * through whereHas here rather than borrowed from whatever the caller happened
 * to join.
 *
 * Three kinds of matching live here, because the three kinds of thing people
 * type do not behave alike:
 *
 *  - Names, employee IDs and the things a teacher belongs to — department,
 *    faculty, designation — are matched anywhere in the value. Somebody typing
 *    "islam" means any Islam.
 *
 *  - Email addresses are matched from the start only. Every teacher's address
 *    ends @diu.edu.bd or @daffodilvarsity.edu.bd, so matching anywhere would
 *    mean "diu" returned 1,609 people and "edu" very nearly the whole faculty.
 *    From the start, "mparvez" finds one person and the domain finds nobody,
 *    which is the useful way round.
 *
 *  - Phone numbers are compared digit to digit, because nobody types a number
 *    the way it is stored. The same mobile is on file as "01677-349368",
 *    "0174-114-0565" and "+88 01713493055", and a plain LIKE finds exactly the
 *    one spelling the visitor happened to guess.
 */
final class TeacherSearchTerm
{
    /**
     * Below this, an email prefix matches too much to be worth running.
     */
    public const MIN_EMAIL_LENGTH = 3;

    /**
     * Below this many digits a number is not a number anybody is looking for,
     * it is the start of one. Six is the tail of a local number, which is the
     * shortest thing people actually search by.
     */
    public const MIN_PHONE_DIGITS = 6;

    /**
     * The separators that mean nothing inside a phone number.
     *
     * The comma is deliberately not among them. A good number of these fields
     * hold two or three numbers at once — "01673922767, 01308206577" — and
     * removing the comma would run them together into one long digit string
     * that a search could match across the join, returning a teacher for a
     * number that is nobody's.
     */
    private const PHONE_NOISE = [' ', '-', '+', '(', ')', '.', '/'];

    /**
     * Narrows a teacher query to what somebody typed.
     *
     * Takes the term and nothing else. Any frontend search that wants to be the
     * same search as the others calls exactly this.
     *
     * @param  Builder<\App\Models\Teacher>  $query
     * @return Builder<\App\Models\Teacher>
     */
    public static function apply(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qb) use ($term): void {
            $like = '%' . $term . '%';

            $qb->where('teachers.first_name', 'like', $like)
                ->orWhere('teachers.middle_name', 'like', $like)
                ->orWhere('teachers.last_name', 'like', $like)
                ->orWhere('teachers.employee_id', 'like', $like);

            self::applyPlacement($qb, $like);
            self::applyEmail($qb, $term);
            self::applyPhone($qb, $term);
        });
    }

    /**
     * Where a teacher sits: department, faculty, designation.
     *
     * Reached through the relations rather than through columns the caller has
     * joined. The three callers join different tables — the department page
     * never joins faculties at all — so borrowing their joins was what made the
     * same search behave differently on each page.
     *
     * The department is the one on the teacher's own record. Teachers assigned
     * to further departments through the pivot are not matched here, which is
     * how this has always worked: those assignments are what the department
     * filter is for, and folding them into the free-text search would mean a
     * name typed into the box quietly returning a wider set than the same name
     * picked from the filter beside it.
     */
    private static function applyPlacement(Builder $qb, string $like): void
    {
        $qb->orWhereHas('department', fn ($department) => $department
            ->where('departments.name', 'like', $like)
            ->orWhere('departments.code', 'like', $like))
            ->orWhereHas('department.faculty', fn ($faculty) => $faculty
                ->where('faculties.name', 'like', $like)
                ->orWhere('faculties.short_name', 'like', $like))
            ->orWhereHas('designation', fn ($designation) => $designation
                ->where('designations.name', 'like', $like));
    }

    /**
     * The address people are actually reachable on lives on the user account,
     * not on the teacher row.
     *
     * secondary_email was the only email the search looked at, and it is empty
     * for all 2,118 teachers — so searching by email has never once worked on
     * this site. It stays in the list because the field is still on the form
     * and will fill up; users.email is what makes the search answer today.
     */
    private static function applyEmail(Builder $qb, string $term): void
    {
        if (mb_strlen($term) < self::MIN_EMAIL_LENGTH) {
            return;
        }

        $prefix = $term . '%';

        $qb->orWhere('teachers.secondary_email', 'like', $prefix)
            ->orWhereHas('user', fn ($user) => $user->where('email', 'like', $prefix));
    }

    /**
     * Matches a number however either side has punctuated it.
     *
     * Both columns are searched. Which of the two a given number sits in is not
     * something a visitor knows or should have to guess — a lot of these rows
     * hold the same number in both.
     */
    private static function applyPhone(Builder $qb, string $term): void
    {
        $digits = self::digitsOf($term);

        if (strlen($digits) < self::MIN_PHONE_DIGITS) {
            return;
        }

        $needle = '%' . self::withoutCountryCode($digits) . '%';

        foreach (['teachers.phone', 'teachers.personal_phone'] as $column) {
            $qb->orWhereRaw(self::comparableColumn($column) . ' LIKE ?', [$needle]);
        }
    }

    /** Everything that is not a digit, gone. */
    private static function digitsOf(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Drops the Bangladesh country code so that the two ways of writing the
     * same number find each other.
     *
     * Stored numbers are inconsistent about it — "+88 01713493055" sits beside
     * "01713493145" in the same column — so the code is removed from what was
     * typed and the match is left as a substring. A stored 88017… still
     * contains 017…, so both spellings are reached from either direction.
     *
     * Ten digits, because no local number that short begins 88: mobiles start
     * 01 and landlines 0.
     */
    private static function withoutCountryCode(string $digits): string
    {
        return str_starts_with($digits, '88') && strlen($digits) >= 10
            ? substr($digits, 2)
            : $digits;
    }

    /**
     * The SQL that strips a stored number down to what can be compared.
     *
     * A nest of REPLACE rather than a regular expression so it runs the same on
     * MySQL 8 and MariaDB, neither of which agrees with the other about
     * REGEXP_REPLACE. It cannot use an index, which is fine at this size: the
     * whole table is 2,118 rows and the query is already joining four tables.
     */
    private static function comparableColumn(string $column): string
    {
        $sql = "COALESCE({$column}, '')";

        foreach (self::PHONE_NOISE as $character) {
            $sql = "REPLACE({$sql}, '{$character}', '')";
        }

        return $sql;
    }
}
