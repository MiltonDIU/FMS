<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DepartmentTeacher;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentTeacherPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DepartmentTeacher');
    }

    public function view(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('View:DepartmentTeacher');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DepartmentTeacher');
    }

    public function update(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('Update:DepartmentTeacher');
    }

    public function delete(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('Delete:DepartmentTeacher');
    }

    public function restore(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('Restore:DepartmentTeacher');
    }

    public function forceDelete(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('ForceDelete:DepartmentTeacher');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DepartmentTeacher');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DepartmentTeacher');
    }

    public function replicate(AuthUser $authUser, DepartmentTeacher $departmentTeacher): bool
    {
        return $authUser->can('Replicate:DepartmentTeacher');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DepartmentTeacher');
    }

}