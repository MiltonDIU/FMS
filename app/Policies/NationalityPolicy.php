<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Nationality;
use Illuminate\Auth\Access\HandlesAuthorization;

class NationalityPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Nationality');
    }

    public function view(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('View:Nationality');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Nationality');
    }

    public function update(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('Update:Nationality');
    }

    public function delete(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('Delete:Nationality');
    }

    public function restore(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('Restore:Nationality');
    }

    public function forceDelete(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('ForceDelete:Nationality');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Nationality');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Nationality');
    }

    public function replicate(AuthUser $authUser, Nationality $nationality): bool
    {
        return $authUser->can('Replicate:Nationality');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Nationality');
    }

}