<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PublicationIncentive;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicationIncentivePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationIncentive');
    }

    public function view(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('View:PublicationIncentive');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationIncentive');
    }

    public function update(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('Update:PublicationIncentive');
    }

    public function delete(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('Delete:PublicationIncentive');
    }

    public function restore(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('Restore:PublicationIncentive');
    }

    public function forceDelete(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('ForceDelete:PublicationIncentive');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationIncentive');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationIncentive');
    }

    public function replicate(AuthUser $authUser, PublicationIncentive $publicationIncentive): bool
    {
        return $authUser->can('Replicate:PublicationIncentive');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationIncentive');
    }

}