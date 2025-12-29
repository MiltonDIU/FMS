<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotificationRouting;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationRoutingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotificationRouting');
    }

    public function view(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('View:NotificationRouting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotificationRouting');
    }

    public function update(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('Update:NotificationRouting');
    }

    public function delete(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('Delete:NotificationRouting');
    }

    public function restore(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('Restore:NotificationRouting');
    }

    public function forceDelete(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('ForceDelete:NotificationRouting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotificationRouting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotificationRouting');
    }

    public function replicate(AuthUser $authUser, NotificationRouting $notificationRouting): bool
    {
        return $authUser->can('Replicate:NotificationRouting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotificationRouting');
    }

}