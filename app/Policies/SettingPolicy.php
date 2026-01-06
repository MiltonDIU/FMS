<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Setting;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Setting');
    }

    public function view(AuthUser $authUser, Setting $setting): bool
    {
        return $authUser->can('View:Setting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Setting');
    }

    public function update(AuthUser $authUser, Setting $setting): bool
    {
        return $authUser->can('Update:Setting');
    }

    public function delete(AuthUser $authUser, Setting $setting): bool
    {
        return $authUser->can('Delete:Setting');
    }

    public function restore(AuthUser $authUser, Setting $setting): bool
    {
        return $authUser->can('Restore:Setting');
    }

    public function forceDelete(AuthUser $authUser, Setting $setting): bool
    {
        return $authUser->can('ForceDelete:Setting');
    }
}
