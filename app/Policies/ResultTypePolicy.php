<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ResultType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResultTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ResultType');
    }

    public function view(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('View:ResultType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ResultType');
    }

    public function update(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('Update:ResultType');
    }

    public function delete(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('Delete:ResultType');
    }

    public function restore(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('Restore:ResultType');
    }

    public function forceDelete(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('ForceDelete:ResultType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ResultType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ResultType');
    }

    public function replicate(AuthUser $authUser, ResultType $resultType): bool
    {
        return $authUser->can('Replicate:ResultType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ResultType');
    }

}