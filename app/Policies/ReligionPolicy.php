<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Religion;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReligionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Religion');
    }

    public function view(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('View:Religion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Religion');
    }

    public function update(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('Update:Religion');
    }

    public function delete(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('Delete:Religion');
    }

    public function restore(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('Restore:Religion');
    }

    public function forceDelete(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('ForceDelete:Religion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Religion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Religion');
    }

    public function replicate(AuthUser $authUser, Religion $religion): bool
    {
        return $authUser->can('Replicate:Religion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Religion');
    }

}