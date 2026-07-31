<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Major;
use Illuminate\Auth\Access\HandlesAuthorization;

class MajorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Major');
    }

    public function view(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('View:Major');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Major');
    }

    public function update(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('Update:Major');
    }

    public function delete(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('Delete:Major');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Major');
    }

    public function restore(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('Restore:Major');
    }

    public function forceDelete(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('ForceDelete:Major');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Major');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Major');
    }

    public function replicate(AuthUser $authUser, Major $major): bool
    {
        return $authUser->can('Replicate:Major');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Major');
    }

}