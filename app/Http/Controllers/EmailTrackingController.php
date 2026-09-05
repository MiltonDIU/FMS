<?php

namespace App\Http\Controllers;

use App\Services\EmailTracking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The two endpoints an emailed message calls back on.
 *
 * Both are opened by strangers: a mail client on the other side of the world, a
 * scanner, a proxy, months after the batch was sent. So neither authenticates,
 * neither trusts its input, and neither ever shows an error — an unknown or
 * deleted token is answered exactly like a known one, because the alternative is
 * a broken image or an error page in somebody's inbox.
 */
class EmailTrackingController extends Controller
{
    /** A 1x1 transparent GIF, the smallest thing that can be an image. */
    protected const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $token): Response
    {
        EmailTracking::findRecipient($token)?->registerOpen();

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            // Without this a client that caches the image reports one open and
            // never another, and a proxy could serve it to a second recipient.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(Request $request, string $token): RedirectResponse
    {
        $target = (string) $request->query('to');

        /*
         * The signature is what stops this being an open redirect. Anything
         * unsigned, re-signed or edited goes to the front page instead of the
         * address in the query string — a visitor is never forwarded somewhere
         * this system did not put in the message itself.
         */
        if (! $request->hasValidSignature() || ! preg_match('#^https?://#i', $target)) {
            return redirect('/');
        }

        EmailTracking::findRecipient($token)?->registerClick();

        return redirect()->away($target);
    }
}
