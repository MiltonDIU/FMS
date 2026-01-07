<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JobType;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobType');
    }

    public function view(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('View:JobType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobType');
    }

    public function update(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('Update:JobType');
    }

    public function delete(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('Delete:JobType');
    }

    public function restore(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('Restore:JobType');
    }

    public function forceDelete(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('ForceDelete:JobType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JobType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JobType');
    }

    public function replicate(AuthUser $authUser, JobType $jobType): bool
    {
        return $authUser->can('Replicate:JobType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JobType');
    }

}