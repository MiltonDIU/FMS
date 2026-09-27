<?php

namespace App\Listeners;

use App\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * The last check before any message leaves: nothing goes to a blocked address.
 *
 * The teacher email dialogs skip blocked addresses themselves and record why.
 * This covers everything else that mails a teacher — approval and rejection
 * notices, the welcome mail — so a known-bad address stops bouncing no matter
 * which part of the system tried to write to it.
 *
 * Returning false cancels the send (Mailer::shouldSendMessage). Only when every
 * recipient is blocked: a message with one good address among several still
 * goes, rather than being lost for everyone over one bad one.
 *
 * Registration is automatic, like InjectEmailTracking.
 */
class BlockSuppressedEmails
{
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;

        $addresses = array_map(
            fn (Address $address): string => $address->getAddress(),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! EmailSuppression::isSuppressed($address)) {
                return null;
            }
        }

        Log::info('[email-block] Not sent to blocked address(es): ' . implode(', ', $addresses)
            . ' | subject: ' . $message->getSubject());

        return false;
    }
}
