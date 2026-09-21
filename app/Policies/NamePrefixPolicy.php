<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NamePrefix;
use Illuminate\Auth\Access\HandlesAuthorization;

class NamePrefixPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NamePrefix');
    }

    public function view(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('View:NamePrefix');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NamePrefix');
    }

    public function update(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('Update:NamePrefix');
    }

    public function delete(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('Delete:NamePrefix');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NamePrefix');
    }

    public function restore(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('Restore:NamePrefix');
    }

    public function forceDelete(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('ForceDelete:NamePrefix');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NamePrefix');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NamePrefix');
    }

    public function replicate(AuthUser $authUser, NamePrefix $namePrefix): bool
    {
        return $authUser->can('Replicate:NamePrefix');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NamePrefix');
    }

}