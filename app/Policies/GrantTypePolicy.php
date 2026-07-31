<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GrantType;
use Illuminate\Auth\Access\HandlesAuthorization;

class GrantTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GrantType');
    }

    public function view(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('View:GrantType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GrantType');
    }

    public function update(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('Update:GrantType');
    }

    public function delete(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('Delete:GrantType');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GrantType');
    }

    public function restore(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('Restore:GrantType');
    }

    public function forceDelete(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('ForceDelete:GrantType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GrantType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GrantType');
    }

    public function replicate(AuthUser $authUser, GrantType $grantType): bool
    {
        return $authUser->can('Replicate:GrantType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GrantType');
    }

}