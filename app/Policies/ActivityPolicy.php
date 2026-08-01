<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may read the audit trail.
 *
 * Granted by permission rather than by naming a role, so that access normally
 * sits with super_admin but can be handed to someone else — an auditor, an
 * investigation — without touching code. The two permissions follow the same
 * naming as every other resource here, so they appear in Shield's role editor
 * alongside the rest.
 *
 * Registered explicitly in AppServiceProvider: Laravel guesses
 * App\Models\Foo -> App\Policies\FooPolicy, and this model has to be named
 * because config/activitylog.php can point the package at a different class.
 *
 * Everything except reading is refused to everyone, super_admin included. An
 * entry records something that happened; creating one by hand or editing one
 * afterwards would make the trail worth less than nothing. Pruning old entries
 * still happens, but through the scheduled activitylog:clean command rather than
 * through a person clicking delete.
 */
class ActivityPolicy
{
    use HandlesAuthorization;

    public const VIEW_ANY = 'ViewAny:Activity';

    public const VIEW = 'View:Activity';

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::VIEW_ANY);
    }

    public function view(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can(self::VIEW);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
