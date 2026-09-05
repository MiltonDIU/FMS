<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailBatchRecipient;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A recipient row is part of its batch, and is governed by it.
 *
 * Whoever may read the batch may read who was in it; there is no separate
 * permission, because the two are one record split across two tables. Nothing
 * here may be written by hand: these rows are filled in by the queue, the mail
 * server and the recipients' own mail clients, and a row an administrator could
 * edit would stop being evidence of anything. Removing one happens by deleting
 * the batch it belongs to.
 *
 * Needed as a file of its own because Filament runs in strict authorization
 * mode: a model shown in a relation manager with no policy raises rather than
 * quietly allowing.
 */
class EmailBatchRecipientPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(EmailBatchPolicy::VIEW_ANY);
    }

    public function view(AuthUser $authUser, EmailBatchRecipient $recipient): bool
    {
        return $authUser->can(EmailBatchPolicy::VIEW);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, EmailBatchRecipient $recipient): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, EmailBatchRecipient $recipient): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, EmailBatchRecipient $recipient): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, EmailBatchRecipient $recipient): bool
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

    public function replicate(AuthUser $authUser, EmailBatchRecipient $recipient): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
