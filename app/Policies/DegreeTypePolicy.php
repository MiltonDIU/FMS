<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DegreeType;
use Illuminate\Auth\Access\HandlesAuthorization;

class DegreeTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DegreeType');
    }

    public function view(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('View:DegreeType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DegreeType');
    }

    public function update(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('Update:DegreeType');
    }

    public function delete(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('Delete:DegreeType');
    }

    public function restore(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('Restore:DegreeType');
    }

    public function forceDelete(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('ForceDelete:DegreeType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DegreeType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DegreeType');
    }

    public function replicate(AuthUser $authUser, DegreeType $degreeType): bool
    {
        return $authUser->can('Replicate:DegreeType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DegreeType');
    }

}