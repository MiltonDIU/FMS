<?php

namespace App\Services;

use App\Models\EmailBatchRecipient;
use Illuminate\Support\Facades\URL;

/**
 * Turns a sent message into something that can report back.
 *
 * Two signals, and they are not equally good. The pixel is fetched when the
 * recipient's mail client loads images, which many do not and some — Gmail's
 * proxy among them — do on the recipient's behalf whether or not they read
 * anything. A click is far stronger evidence, so both are recorded and the
 * screens say which is which rather than adding them together.
 *
 * The rewriting happens on the finished HTML in a MessageSending listener rather
 * than while composing the body, so it applies to every template — the ones in
 * the database now and any added later — without each one having to remember.
 */
class EmailTracking
{
    /**
     * Carries the recipient row's token from the job to the listener.
     *
     * A header rather than a constructor argument because the notification is
     * rendered by Laravel, and the listener sees only the finished message.
     */
    public const HEADER = 'X-FMS-Track';

    public static function openUrl(string $token): string
    {
        return route('email.track.open', ['token' => $token]);
    }

    /**
     * Signed, so the redirect cannot be handed an address of somebody else's
     * choosing. Without the signature this route would forward a visitor
     * anywhere, under this institution's domain.
     */
    public static function clickUrl(string $token, string $target): string
    {
        return URL::signedRoute('email.track.click', [
            'token' => $token,
            'to' => $target,
        ]);
    }

    /**
     * Point the message's links at the redirect and add the pixel.
     */
    public static function inject(string $html, string $token): string
    {
        $html = self::rewriteLinks($html, $token);

        $pixel = '<img src="' . e(self::openUrl($token)) . '" alt="" width="1" height="1" '
            . 'style="display:block;width:1px;height:1px;border:0;outline:none" />';

        $position = strripos($html, '</body>');

        return $position === false
            ? $html . $pixel
            : substr($html, 0, $position) . $pixel . substr($html, $position);
    }

    /**
     * Record a fetch of the pixel, or a click, against its recipient.
     *
     * An unknown token is answered normally by the callers rather than raised:
     * these two routes are opened by mail clients, months later and from
     * addresses nobody controls, and a batch that has since been deleted must
     * not turn into an error page in somebody's inbox.
     */
    public static function findRecipient(string $token): ?EmailBatchRecipient
    {
        if (strlen($token) < 32) {
            return null;
        }

        return EmailBatchRecipient::where('track_token', $token)->first();
    }

    protected static function rewriteLinks(string $html, string $token): string
    {
        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\shref=)(["\'])(.*?)\2/i',
            function (array $match) use ($token): string {
                $url = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');

                if (! self::shouldRewrite($url)) {
                    return $match[0];
                }

                return $match[1] . $match[2] . e(self::clickUrl($token, $url)) . $match[2];
            },
            $html,
        );
    }

    /**
     * Which links are worth redirecting through us — and which must not be.
     *
     * The activation link is the exception that matters. It signs its holder in
     * and is spent on first use, and mail scanners of the sort corporate
     * mailboxes sit behind follow links before anybody reads them. Redirecting
     * it would let a scanner burn the token, locking out the teacher it was
     * issued for. Its click is already recorded, far more reliably, by
     * verification_token_used_at.
     */
    protected static function shouldRewrite(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (str_starts_with($path, '/teacher/activate')) {
            return false;
        }

        // Already ours: re-wrapping a redirect would nest it on every resend.
        return ! str_starts_with($path, '/email-track/');
    }
}
