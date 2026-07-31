<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\IncentiveLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class IncentiveLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IncentiveLog');
    }

    public function view(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('View:IncentiveLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IncentiveLog');
    }

    public function update(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('Update:IncentiveLog');
    }

    public function delete(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('Delete:IncentiveLog');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:IncentiveLog');
    }

    public function restore(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('Restore:IncentiveLog');
    }

    public function forceDelete(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('ForceDelete:IncentiveLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IncentiveLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IncentiveLog');
    }

    public function replicate(AuthUser $authUser, IncentiveLog $incentiveLog): bool
    {
        return $authUser->can('Replicate:IncentiveLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IncentiveLog');
    }

}