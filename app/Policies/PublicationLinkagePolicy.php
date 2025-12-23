<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PublicationLinkage;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicationLinkagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationLinkage');
    }

    public function view(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('View:PublicationLinkage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationLinkage');
    }

    public function update(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('Update:PublicationLinkage');
    }

    public function delete(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('Delete:PublicationLinkage');
    }

    public function restore(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('Restore:PublicationLinkage');
    }

    public function forceDelete(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('ForceDelete:PublicationLinkage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationLinkage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationLinkage');
    }

    public function replicate(AuthUser $authUser, PublicationLinkage $publicationLinkage): bool
    {
        return $authUser->can('Replicate:PublicationLinkage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationLinkage');
    }

}