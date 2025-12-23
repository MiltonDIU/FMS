<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ResearchCollaboration;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResearchCollaborationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ResearchCollaboration');
    }

    public function view(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('View:ResearchCollaboration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ResearchCollaboration');
    }

    public function update(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('Update:ResearchCollaboration');
    }

    public function delete(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('Delete:ResearchCollaboration');
    }

    public function restore(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('Restore:ResearchCollaboration');
    }

    public function forceDelete(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('ForceDelete:ResearchCollaboration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ResearchCollaboration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ResearchCollaboration');
    }

    public function replicate(AuthUser $authUser, ResearchCollaboration $researchCollaboration): bool
    {
        return $authUser->can('Replicate:ResearchCollaboration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ResearchCollaboration');
    }

}