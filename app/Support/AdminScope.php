<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The part of the university a signed-in user's lists are limited to.
 *
 * Read from the user's active administrative role assignment, the same one
 * every scoped resource reads: a Dean or Associate Dean holds theirs over a
 * faculty, a Head or Associate Head over a department. Anyone without such an
 * assignment — super admin, admin, registrar — is not limited, and every
 * method here leaves their query alone.
 *
 * Written for the lookup screens (faculties, designations, employment
 * statuses, job types), whose rows are shared by everyone but whose counts
 * should only cover the teachers the viewer is responsible for.
 */
final class AdminScope
{
    /** The department a Head or Associate Head is limited to. */
    public static function departmentId(?User $user = null): ?int
    {
        $pivot = self::assignment($user);

        return $pivot?->department_id ? (int) $pivot->department_id : null;
    }

    /**
     * The faculty the user is limited to: a Dean's own, or the faculty their
     * department sits in for a Head.
     */
    public static function facultyId(?User $user = null): ?int
    {
        $pivot = self::assignment($user);

        if (! $pivot) {
            return null;
        }

        if ($pivot->department_id) {
            return Department::whereKey($pivot->department_id)->value('faculty_id');
        }

        return $pivot->faculty_id ? (int) $pivot->faculty_id : null;
    }

    /** Limit a teachers query to those whose home department is in scope. */
    public static function teachers(Builder $query, ?User $user = null): Builder
    {
        if ($departmentId = self::departmentId($user)) {
            return $query->where('teachers.department_id', $departmentId);
        }

        if ($facultyId = self::facultyId($user)) {
            return $query->whereIn(
                'teachers.department_id',
                Department::select('id')->where('faculty_id', $facultyId)
            );
        }

        return $query;
    }

    /**
     * The pivot of the active assignment, or null for a user with none, or
     * none that is held over a faculty or a department.
     */
    private static function assignment(?User $user): ?object
    {
        $user ??= auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return null;
        }

        $pivot = $user->administrativeRoles()
            ->wherePivot('is_active', true)
            ->whereNull('administrative_role_user.end_date')
            ->first()
            ?->pivot;

        return ($pivot && ($pivot->department_id || $pivot->faculty_id)) ? $pivot : null;
    }
}
