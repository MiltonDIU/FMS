<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AcademicSuffix;
use Illuminate\Auth\Access\HandlesAuthorization;

class AcademicSuffixPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AcademicSuffix');
    }

    public function view(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('View:AcademicSuffix');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AcademicSuffix');
    }

    public function update(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('Update:AcademicSuffix');
    }

    public function delete(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('Delete:AcademicSuffix');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AcademicSuffix');
    }

    public function restore(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('Restore:AcademicSuffix');
    }

    public function forceDelete(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('ForceDelete:AcademicSuffix');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AcademicSuffix');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AcademicSuffix');
    }

    public function replicate(AuthUser $authUser, AcademicSuffix $academicSuffix): bool
    {
        return $authUser->can('Replicate:AcademicSuffix');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AcademicSuffix');
    }

}