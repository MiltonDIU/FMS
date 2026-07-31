<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AdministrativeRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdministrativeRolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdministrativeRole');
    }

    public function view(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('View:AdministrativeRole');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdministrativeRole');
    }

    public function update(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('Update:AdministrativeRole');
    }

    public function delete(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('Delete:AdministrativeRole');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdministrativeRole');
    }

    public function restore(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('Restore:AdministrativeRole');
    }

    public function forceDelete(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('ForceDelete:AdministrativeRole');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdministrativeRole');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdministrativeRole');
    }

    public function replicate(AuthUser $authUser, AdministrativeRole $administrativeRole): bool
    {
        return $authUser->can('Replicate:AdministrativeRole');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdministrativeRole');
    }

}