<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MembershipType;
use Illuminate\Auth\Access\HandlesAuthorization;

class MembershipTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MembershipType');
    }

    public function view(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('View:MembershipType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MembershipType');
    }

    public function update(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('Update:MembershipType');
    }

    public function delete(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('Delete:MembershipType');
    }

    public function restore(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('Restore:MembershipType');
    }

    public function forceDelete(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('ForceDelete:MembershipType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MembershipType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MembershipType');
    }

    public function replicate(AuthUser $authUser, MembershipType $membershipType): bool
    {
        return $authUser->can('Replicate:MembershipType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MembershipType');
    }

}