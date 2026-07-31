<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PublicationQuartile;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicationQuartilePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationQuartile');
    }

    public function view(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('View:PublicationQuartile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationQuartile');
    }

    public function update(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('Update:PublicationQuartile');
    }

    public function delete(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('Delete:PublicationQuartile');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PublicationQuartile');
    }

    public function restore(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('Restore:PublicationQuartile');
    }

    public function forceDelete(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('ForceDelete:PublicationQuartile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationQuartile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationQuartile');
    }

    public function replicate(AuthUser $authUser, PublicationQuartile $publicationQuartile): bool
    {
        return $authUser->can('Replicate:PublicationQuartile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationQuartile');
    }

}