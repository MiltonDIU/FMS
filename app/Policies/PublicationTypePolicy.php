<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PublicationType;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicationTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationType');
    }

    public function view(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('View:PublicationType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationType');
    }

    public function update(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('Update:PublicationType');
    }

    public function delete(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('Delete:PublicationType');
    }

    public function restore(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('Restore:PublicationType');
    }

    public function forceDelete(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('ForceDelete:PublicationType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationType');
    }

    public function replicate(AuthUser $authUser, PublicationType $publicationType): bool
    {
        return $authUser->can('Replicate:PublicationType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationType');
    }

}