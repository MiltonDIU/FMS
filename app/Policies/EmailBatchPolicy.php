<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailBatch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may read the record of what has been emailed.
 *
 * Reading is by permission rather than by role, so it can be handed to whoever
 * actually chases teachers about their profiles without a deployment.
 *
 * There is no create and no update, for anybody. A batch is written by the act
 * of sending, and a recipient row is written by the queue, the mail server and
 * the recipient's own client. Editing either by hand would turn a delivery
 * record into a claim about one, which is worth nothing. Sending again is a new
 * batch, not an edit of the old one — that is what the resend action does.
 *
 * Deleting is allowed, and gated tightly. Old batches are worth clearing out
 * eventually, and a batch carries the addresses it went to.
 */
class EmailBatchPolicy
{
    use HandlesAuthorization;

    public const VIEW_ANY = 'ViewAny:EmailBatch';

    public const VIEW = 'View:EmailBatch';

    public const DELETE = 'Delete:EmailBatch';

    public const DELETE_ANY = 'DeleteAny:EmailBatch';

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::VIEW_ANY);
    }

    public function view(AuthUser $authUser, EmailBatch $emailBatch): bool
    {
        return $authUser->can(self::VIEW);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, EmailBatch $emailBatch): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, EmailBatch $emailBatch): bool
    {
        return $authUser->can(self::DELETE);
    }

    /** Filament checks this, not delete(), for the bulk delete action. */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can(self::DELETE_ANY);
    }

    public function restore(AuthUser $authUser, EmailBatch $emailBatch): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, EmailBatch $emailBatch): bool
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

    public function replicate(AuthUser $authUser, EmailBatch $emailBatch): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
