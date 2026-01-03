<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ResearchProject;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResearchProjectPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ResearchProject');
    }

    public function view(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('View:ResearchProject');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ResearchProject');
    }

    public function update(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('Update:ResearchProject');
    }

    public function delete(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('Delete:ResearchProject');
    }

    public function restore(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('Restore:ResearchProject');
    }

    public function forceDelete(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('ForceDelete:ResearchProject');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ResearchProject');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ResearchProject');
    }

    public function replicate(AuthUser $authUser, ResearchProject $researchProject): bool
    {
        return $authUser->can('Replicate:ResearchProject');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ResearchProject');
    }

}