<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Gender;
use Illuminate\Auth\Access\HandlesAuthorization;

class GenderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Gender');
    }

    public function view(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('View:Gender');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Gender');
    }

    public function update(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('Update:Gender');
    }

    public function delete(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('Delete:Gender');
    }

    public function restore(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('Restore:Gender');
    }

    public function forceDelete(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('ForceDelete:Gender');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Gender');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Gender');
    }

    public function replicate(AuthUser $authUser, Gender $gender): bool
    {
        return $authUser->can('Replicate:Gender');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Gender');
    }

}