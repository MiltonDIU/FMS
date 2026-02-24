<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\IntegrationMapping;
use Illuminate\Auth\Access\HandlesAuthorization;

class IntegrationMappingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IntegrationMapping');
    }

    public function view(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('View:IntegrationMapping');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IntegrationMapping');
    }

    public function update(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('Update:IntegrationMapping');
    }

    public function delete(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('Delete:IntegrationMapping');
    }

    public function restore(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('Restore:IntegrationMapping');
    }

    public function forceDelete(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('ForceDelete:IntegrationMapping');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IntegrationMapping');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IntegrationMapping');
    }

    public function replicate(AuthUser $authUser, IntegrationMapping $integrationMapping): bool
    {
        return $authUser->can('Replicate:IntegrationMapping');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IntegrationMapping');
    }

}