<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailSuppression;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may read and change the list of addresses the system will not email.
 *
 * Adding is given to the people who send, since they are the ones who see the
 * bounces come back. Removing — which lets mail flow to an address again — is
 * a separate permission, so it can be kept narrower.
 */
class EmailSuppressionPolicy
{
    use HandlesAuthorization;

    public const VIEW_ANY = 'ViewAny:EmailSuppression';

    public const VIEW = 'View:EmailSuppression';

    public const CREATE = 'Create:EmailSuppression';

    public const UPDATE = 'Update:EmailSuppression';

    public const DELETE = 'Delete:EmailSuppression';

    public const DELETE_ANY = 'DeleteAny:EmailSuppression';

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::VIEW_ANY);
    }

    public function view(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return $authUser->can(self::VIEW);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can(self::CREATE);
    }

    public function update(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return $authUser->can(self::UPDATE);
    }

    public function delete(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return $authUser->can(self::DELETE);
    }

    /** Filament checks this, not delete(), for the bulk delete action. */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::DELETE_ANY);
    }

    public function restore(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, EmailSuppression $suppression): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
