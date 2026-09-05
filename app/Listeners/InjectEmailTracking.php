<?php

namespace App\Listeners;

use App\Services\EmailTracking;
use Illuminate\Mail\Events\MessageSending;

/**
 * Adds the tracking pixel and rewrites links, just before a message goes.
 *
 * It works on the rendered HTML rather than on the template text, which is why
 * it lives here: by this point Laravel has turned the markdown body, the theme
 * and the inlined CSS into one document, and there is nothing left that could
 * escape an <img> tag or mangle a href.
 *
 * Only messages carrying the tracking header are touched, so password resets,
 * Filament's own notifications and anything else this system sends pass through
 * untouched.
 *
 * Registration is automatic: Laravel discovers listeners in app/Listeners by the
 * event the handle method type-hints.
 */
class InjectEmailTracking
{
    public function handle(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();
        $header = $headers->get(EmailTracking::HEADER);

        if (! $header) {
            return;
        }

        $token = trim($header->getBodyAsString());

        // Carried the token this far and no further: it is in the pixel URL the
        // recipient's client fetches anyway, but there is no reason to ship it
        // as a header as well.
        $headers->remove(EmailTracking::HEADER);

        $html = $event->message->getHtmlBody();

        // A plain-text-only message has nowhere to put a pixel. It still counts
        // as sent; it simply cannot report being read.
        if ($token === '' || ! is_string($html) || $html === '') {
            return;
        }

        $event->message->html(EmailTracking::inject($html, $token));
    }
}
