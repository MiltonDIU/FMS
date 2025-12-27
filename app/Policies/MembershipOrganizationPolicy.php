<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MembershipOrganization;
use Illuminate\Auth\Access\HandlesAuthorization;

class MembershipOrganizationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MembershipOrganization');
    }

    public function view(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('View:MembershipOrganization');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MembershipOrganization');
    }

    public function update(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('Update:MembershipOrganization');
    }

    public function delete(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('Delete:MembershipOrganization');
    }

    public function restore(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('Restore:MembershipOrganization');
    }

    public function forceDelete(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('ForceDelete:MembershipOrganization');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MembershipOrganization');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MembershipOrganization');
    }

    public function replicate(AuthUser $authUser, MembershipOrganization $membershipOrganization): bool
    {
        return $authUser->can('Replicate:MembershipOrganization');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MembershipOrganization');
    }

}