<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ApprovalSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApprovalSetting');
    }

    public function view(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('View:ApprovalSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApprovalSetting');
    }

    public function update(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('Update:ApprovalSetting');
    }

    public function delete(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('Delete:ApprovalSetting');
    }

    public function restore(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('Restore:ApprovalSetting');
    }

    public function forceDelete(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('ForceDelete:ApprovalSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApprovalSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApprovalSetting');
    }

    public function replicate(AuthUser $authUser, ApprovalSetting $approvalSetting): bool
    {
        return $authUser->can('Replicate:ApprovalSetting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApprovalSetting');
    }

}