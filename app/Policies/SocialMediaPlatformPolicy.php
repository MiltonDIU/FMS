<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SocialMediaPlatform;
use Illuminate\Auth\Access\HandlesAuthorization;

class SocialMediaPlatformPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SocialMediaPlatform');
    }

    public function view(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('View:SocialMediaPlatform');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SocialMediaPlatform');
    }

    public function update(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('Update:SocialMediaPlatform');
    }

    public function delete(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('Delete:SocialMediaPlatform');
    }

    public function restore(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('Restore:SocialMediaPlatform');
    }

    public function forceDelete(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('ForceDelete:SocialMediaPlatform');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SocialMediaPlatform');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SocialMediaPlatform');
    }

    public function replicate(AuthUser $authUser, SocialMediaPlatform $socialMediaPlatform): bool
    {
        return $authUser->can('Replicate:SocialMediaPlatform');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SocialMediaPlatform');
    }

}