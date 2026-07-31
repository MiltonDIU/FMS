<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TeacherVersion;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeacherVersionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TeacherVersion');
    }

    public function view(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('View:TeacherVersion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TeacherVersion');
    }

    public function update(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('Update:TeacherVersion');
    }

    public function delete(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('Delete:TeacherVersion');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TeacherVersion');
    }

    public function restore(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('Restore:TeacherVersion');
    }

    public function forceDelete(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('ForceDelete:TeacherVersion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TeacherVersion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TeacherVersion');
    }

    public function replicate(AuthUser $authUser, TeacherVersion $teacherVersion): bool
    {
        return $authUser->can('Replicate:TeacherVersion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TeacherVersion');
    }

}