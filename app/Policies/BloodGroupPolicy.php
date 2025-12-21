<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BloodGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class BloodGroupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BloodGroup');
    }

    public function view(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('View:BloodGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BloodGroup');
    }

    public function update(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('Update:BloodGroup');
    }

    public function delete(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('Delete:BloodGroup');
    }

    public function restore(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('Restore:BloodGroup');
    }

    public function forceDelete(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('ForceDelete:BloodGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BloodGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BloodGroup');
    }

    public function replicate(AuthUser $authUser, BloodGroup $bloodGroup): bool
    {
        return $authUser->can('Replicate:BloodGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BloodGroup');
    }

}