<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FieldOfStudy;
use Illuminate\Auth\Access\HandlesAuthorization;

class FieldOfStudyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FieldOfStudy');
    }

    public function view(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('View:FieldOfStudy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FieldOfStudy');
    }

    public function update(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('Update:FieldOfStudy');
    }

    public function delete(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('Delete:FieldOfStudy');
    }

    public function restore(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('Restore:FieldOfStudy');
    }

    public function forceDelete(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('ForceDelete:FieldOfStudy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FieldOfStudy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FieldOfStudy');
    }

    public function replicate(AuthUser $authUser, FieldOfStudy $fieldOfStudy): bool
    {
        return $authUser->can('Replicate:FieldOfStudy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FieldOfStudy');
    }

}