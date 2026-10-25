<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Feedback;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may work the feedback inbox, and who may read a given item.
 *
 * Two screens share this model. The inbox follows these permissions. "My
 * Feedback" answers its own questions in MyFeedbackResource — it is open to
 * anybody signed in while the System Settings switch is on — and never needs a
 * permission, because deploys run no seeders and a teacher should not be locked
 * out of reporting a problem by a missing row.
 */
class FeedbackPolicy
{
    use HandlesAuthorization;

    public const VIEW_ANY = 'ViewAny:Feedback';

    public const VIEW = 'View:Feedback';

    /** Answering, and moving the status. */
    public const UPDATE = 'Update:Feedback';

    public const DELETE = 'Delete:Feedback';

    public const DELETE_ANY = 'DeleteAny:Feedback';

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::VIEW_ANY);
    }

    /** The team, or the person who sent it. */
    public function view(AuthUser $authUser, Feedback $feedback): bool
    {
        return $authUser->can(self::VIEW) || (int) $feedback->user_id === (int) $authUser->getKey();
    }

    public function create(AuthUser $authUser): bool
    {
        return Feedback::isEnabled();
    }

    public function update(AuthUser $authUser, Feedback $feedback): bool
    {
        return $authUser->can(self::UPDATE);
    }

    public function delete(AuthUser $authUser, Feedback $feedback): bool
    {
        return $authUser->can(self::DELETE);
    }

    /** Filament checks this, not delete(), for the bulk delete action. */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::DELETE_ANY);
    }

    public function restore(AuthUser $authUser, Feedback $feedback): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Feedback $feedback): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Feedback $feedback): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
