<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Publication;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Publication');
    }

    public function view(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('View:Publication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Publication');
    }

    public function update(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Update:Publication');
    }

    public function delete(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Delete:Publication');
    }

    public function restore(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Restore:Publication');
    }

    public function forceDelete(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('ForceDelete:Publication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Publication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Publication');
    }

    public function replicate(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Replicate:Publication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Publication');
    }

}