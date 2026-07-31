<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EmploymentStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmploymentStatusPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmploymentStatus');
    }

    public function view(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('View:EmploymentStatus');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmploymentStatus');
    }

    public function update(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('Update:EmploymentStatus');
    }

    public function delete(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('Delete:EmploymentStatus');
    }

    /**
     * Filament checks this, not delete(), for the bulk delete action.
     * While it was absent, non-strict authorization silently allowed it,
     * so anyone who could list the table could empty it.
     */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmploymentStatus');
    }

    public function restore(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('Restore:EmploymentStatus');
    }

    public function forceDelete(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('ForceDelete:EmploymentStatus');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmploymentStatus');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmploymentStatus');
    }

    public function replicate(AuthUser $authUser, EmploymentStatus $employmentStatus): bool
    {
        return $authUser->can('Replicate:EmploymentStatus');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmploymentStatus');
    }

}