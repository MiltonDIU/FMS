<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserAdministrativeRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserAdministrativeRolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserAdministrativeRole');
    }

    public function view(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('View:UserAdministrativeRole');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserAdministrativeRole');
    }

    public function update(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('Update:UserAdministrativeRole');
    }

    public function delete(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('Delete:UserAdministrativeRole');
    }

    public function restore(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('Restore:UserAdministrativeRole');
    }

    public function forceDelete(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('ForceDelete:UserAdministrativeRole');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserAdministrativeRole');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserAdministrativeRole');
    }

    public function replicate(AuthUser $authUser, UserAdministrativeRole $userAdministrativeRole): bool
    {
        return $authUser->can('Replicate:UserAdministrativeRole');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserAdministrativeRole');
    }

}