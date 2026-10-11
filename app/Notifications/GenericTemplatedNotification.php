<?php

namespace App\Notifications;

use App\Services\EmailTracking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email;

/**
 * One composed message, ready to send.
 *
 * Deliberately not queued, although it used to be. The jobs that send it are
 * queued already, so implementing ShouldQueue here put a second job behind the
 * first and moved the actual delivery out of the job that was watching it: a
 * mail server refusing the message threw inside a job nobody was recording, and
 * the batch went on saying "queued" forever. Sending it inline means the job
 * that dispatched it sees the failure and writes it down.
 */
class GenericTemplatedNotification extends Notification
{
    use Queueable;

    /**
     * $trackToken identifies the recipient's row so the pixel and the links can
     * report back. It is optional because a message can be sent without a batch
     * behind it — a test, a one-off from a console command — and such a message
     * is simply not tracked.
     */
    public function __construct(
        public string $customSubject,
        public string $customBody,
        public ?string $trackToken = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->customSubject);

        $body = str_replace(["\r\n", "\r"], "\n", trim($this->customBody));

        // 1. Extract greeting if present (e.g. "Dear Dr. John Doe," or "Hello Jane,")
        if (preg_match('/^(Dear\s+[^\n]+|Hello\s+[^\n]+|Hi\s+[^\n]+)(\n+|$)/i', $body, $matches)) {
            $mail->greeting(trim($matches[1]));
            $body = trim(substr($body, strlen($matches[0])));
        }

        // 2. Extract salutation / sign-off from the end if present
        $salutationPattern = '/\n{2,}((?:Best regards|Warm regards|Kind regards|Regards|Sincerely|Thank you|Thanks)[,\s]*\n+[\s\S]+)$/i';
        if (preg_match($salutationPattern, $body, $matches)) {
            $mail->salutation(trim($matches[1]));
            $body = trim(substr($body, 0, -strlen($matches[0])));
        }

        // 3. Split body into paragraphs/blocks (separated by 2 or more newlines)
        $blocks = preg_split('/\n{2,}/', $body);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            // A) Check if block is an explicit button tag: [button:Label|URL] or [button:Label](URL)
            if (preg_match('/^\[button:\s*(.+?)\s*[\|:]\s*(https?:\/\/[^\s\]]+)\s*\]$/i', $block, $btnMatch) ||
                preg_match('/^\[button:\s*(.+?)\s*\]\((https?:\/\/[^\s\)]+)\)$/i', $block, $btnMatch)) {
                $mail->line(self::renderButton($btnMatch[1], $btnMatch[2]));
                continue;
            }

            // B) Check if block contains [button:Label|URL] embedded in text
            if (preg_match('/\[button:\s*(.+?)\s*[\|:]\s*(https?:\/\/[^\s\]]+)\s*\]/i', $block, $btnMatch) ||
                preg_match('/\[button:\s*(.+?)\s*\]\((https?:\/\/[^\s\)]+)\)/i', $block, $btnMatch)) {
                $mail->line(self::renderButton($btnMatch[1], $btnMatch[2]));
                continue;
            }

            // C) Check if block is a standalone action URL (e.g. activation or verification link without button tag)
            if (preg_match('/^(?:Verification Link:\s*)?(https?:\/\/[^\s]+)$/i', $block, $urlMatch)) {
                $url = $urlMatch[1];
                $label = self::deduceButtonLabel($url);
                $mail->line(self::renderButton($label, $url));
                $mail->line(self::renderFallbackLink($url));
                continue;
            }

            // D) If block is a fallback instruction with URL: "If the button above does not work... {url}"
            if (preg_match('/(?:button.*(?:not work|doesn\'t work).*?(?:browser|below)[:\s]*)\s*(https?:\/\/[^\s]+)/is', $block, $fallbackMatch)) {
                $url = $fallbackMatch[1];
                $mail->line(self::renderFallbackLink($url));
                continue;
            }

            // E) If block contains URLs, ensure they are rendered as clickable links
            if (preg_match('/https?:\/\/[^\s]+/', $block)) {
                $converted = preg_replace_callback('/(https?:\/\/[^\s\<\>"\']+)/i', function ($m) {
                    $u = e($m[1]);
                    return '<a href="' . $u . '" style="color: #2563eb; text-decoration: underline; word-break: break-all;">' . $u . '</a>';
                }, e($block));

                $converted = nl2br($converted);
                $mail->line(new HtmlString('<p style="box-sizing: border-box; font-size: 16px; line-height: 1.5em; margin-top: 0; margin-bottom: 16px; text-align: left;">' . $converted . '</p>'));
                continue;
            }

            // F) Key-value or list-like blocks (e.g. Employee ID: ..., Department: ...)
            if (str_contains($block, "\n") && (str_contains($block, ':') || str_starts_with($block, '-'))) {
                $lines = explode("\n", $block);
                $joined = implode("<br>\n", array_map('e', array_map('trim', $lines)));
                $mail->line(new HtmlString('<p style="box-sizing: border-box; font-size: 16px; line-height: 1.5em; margin-top: 0; margin-bottom: 16px; text-align: left;">' . $joined . '</p>'));
                continue;
            }

            // G) Standard paragraph with soft-wraps joined
            $cleanParagraph = preg_replace('/\s*\n\s*/', ' ', $block);
            $mail->line($cleanParagraph);
        }

        if ($this->trackToken) {
            // The header is read and removed by InjectEmailTracking, which adds
            // the pixel to the finished HTML. Passing the token this way keeps
            // this class unaware of how tracking is done.
            $token = $this->trackToken;

            $mail->withSymfonyMessage(function (Email $message) use ($token): void {
                $message->getHeaders()->addTextHeader(EmailTracking::HEADER, $token);
            });
        }

        return $mail;
    }

    public static function renderButton(string $label, string $url): HtmlString
    {
        $cleanLabel = e($label);
        $cleanUrl = e($url);

        $html = <<<HTML
<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 100%; float: unset; box-sizing: border-box; margin: 24px auto; padding: 0; text-align: center; width: 100%;">
<tr>
<td align="center" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; box-sizing: border-box;">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; box-sizing: border-box;">
<tr>
<td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; box-sizing: border-box;">
<a href="{$cleanUrl}" class="button button-primary" target="_blank" rel="noopener" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -webkit-text-size-adjust: none; word-break: break-all; box-sizing: border-box; border-radius: 4px; color: #ffffff; display: inline-block; overflow: hidden; text-decoration: none; background-color: #18181b; border-bottom: 10px solid #18181b; border-left: 22px solid #18181b; border-right: 22px solid #18181b; border-top: 10px solid #18181b; font-weight: 600; font-size: 15px;">{$cleanLabel}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
HTML;

        return new HtmlString($html);
    }

    public static function renderFallbackLink(string $url): HtmlString
    {
        $cleanUrl = e($url);

        $html = <<<HTML
<p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; box-sizing: border-box; font-size: 13px; line-height: 1.5em; margin-top: -12px; margin-bottom: 24px; text-align: center; color: #71717a; word-break: break-all;">
If the button above does not work, click the link below or copy and paste it into your browser:<br>
<a href="{$cleanUrl}" style="box-sizing: border-box; color: #2563eb; text-decoration: underline; word-break: break-all;">{$cleanUrl}</a>
</p>
HTML;

        return new HtmlString($html);
    }

    protected static function deduceButtonLabel(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (str_contains($path, 'activate')) {
            return 'Activate Your Account';
        }

        if (str_contains($path, 'verify')) {
            return 'Review & Confirm Profile';
        }

        return 'Open Link';
    }
}