<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AuthorType;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuthorTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuthorType');
    }

    public function view(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('View:AuthorType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuthorType');
    }

    public function update(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('Update:AuthorType');
    }

    public function delete(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('Delete:AuthorType');
    }

    public function restore(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('Restore:AuthorType');
    }

    public function forceDelete(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('ForceDelete:AuthorType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuthorType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuthorType');
    }

    public function replicate(AuthUser $authUser, AuthorType $authorType): bool
    {
        return $authUser->can('Replicate:AuthorType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuthorType');
    }

}