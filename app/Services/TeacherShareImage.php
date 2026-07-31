<?php

namespace App\Services;

use App\Helpers\Branding;
use App\Helpers\ColorPalette;
use App\Helpers\FontManager;
use App\Models\Teacher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The picture a teacher's profile shows when the link is shared.
 *
 * A social preview is a fixed shape — one image, a title and a line of text —
 * so anything a reader should see at a glance has to be inside the image. A
 * profile shared as-is offered the 90-120px thumbnail the legacy host serves,
 * which a feed renders as a smear. This draws a 1200x630 card instead: the
 * portrait at a usable size beside the name, designation and department.
 *
 * Cached to disk and keyed on the teacher's own updated_at, so it is drawn once
 * and redrawn when their details change. Crawlers fetch it anonymously, which is
 * why the file is served from public storage rather than generated per request.
 */
class TeacherShareImage
{
    /** The size every network expects; smaller and Facebook refuses to enlarge. */
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Where cached cards live, inside the public disk. */
    public const DIRECTORY = 'share/teachers';

    /** Seconds to wait for the source photograph before giving up on it. */
    public const PHOTO_TIMEOUT = 5;

    /**
     * Path of the card on the public disk, drawing it first if needed.
     *
     * Returns null when the card cannot be drawn — no usable font, most likely —
     * and the caller falls back to the site logo rather than a broken image.
     */
    public static function pathFor(Teacher $teacher): ?string
    {
        $path = static::relativePath($teacher);
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        $png = static::draw($teacher);

        if ($png === null) {
            return null;
        }

        $disk->put($path, $png);

        return $path;
    }

    /** A name that changes when the teacher does, so stale cards are never served. */
    public static function relativePath(Teacher $teacher): string
    {
        $stamp = substr(md5(($teacher->updated_at?->timestamp ?? 0) . '|' . $teacher->photo), 0, 12);

        return static::DIRECTORY . "/{$teacher->id}-{$stamp}.png";
    }

    /**
     * @return string|null PNG bytes
     */
    protected static function draw(Teacher $teacher): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }

        $regular = FontManager::truetypePath('sans');
        $display = FontManager::truetypePath('display') ?: $regular;

        if ($regular === null) {
            return null;
        }

        $canvas = imagecreatetruecolor(static::WIDTH, static::HEIGHT);

        $ink = static::palette($canvas);

        imagefilledrectangle($canvas, 0, 0, static::WIDTH, static::HEIGHT, $ink['page']);

        // A brand bar down the left edge; the rest stays quiet so the face and
        // the name are what a thumbnail resolves to.
        imagefilledrectangle($canvas, 0, 0, 14, static::HEIGHT, $ink['brand']);

        $frameX = 78;
        $frameY = 105;
        $frameW = 350;
        $frameH = (int) round($frameW * 6 / 5);   // the 5:6 the photographs are

        static::drawPortrait($canvas, $teacher, $frameX, $frameY, $frameW, $frameH, $ink, $display);

        $textX = $frameX + $frameW + 62;
        $textRight = static::WIDTH - 78;
        $maxWidth = $textRight - $textX;

        /*
         * The text column is laid out before anything is drawn, so the block can
         * be centred against the portrait and so consecutive lines cannot
         * collide — a 46px name followed 24px later by a 27px designation had
         * them overlapping for anyone whose name ran short.
         *
         * Each block declares its own leading; the gap between blocks is a
         * fraction of the larger size rather than a fixed number of pixels.
         */
        $blocks = array_values(array_filter([
            static::block($teacher->full_name ?: trim("{$teacher->first_name} {$teacher->last_name}"),
                $display, 46, 60, $ink['strong'], 3, $maxWidth),
            static::block(optional($teacher->designation)->name, $regular, 28, 38, $ink['soft'], 2, $maxWidth, 26),
            static::block(optional($teacher->department)->name, $regular, 23, 32, $ink['muted'], 2, $maxWidth, 14),
        ]));

        $facts = array_filter([
            static::plural($teacher->publications_count ?? $teacher->publications()->count(), 'publication'),
            static::plural($teacher->teachingAreas()->count(), 'teaching area'),
        ]);

        // Hairline plus the counts line, measured so the whole column centres.
        $tailHeight = $facts ? 40 + 46 : 0;

        $blockHeight = 0;

        foreach ($blocks as $block) {
            $blockHeight += $block['gap'] + (count($block['lines']) * $block['leading']);
        }

        $eyebrowHeight = 60;
        $totalHeight = $eyebrowHeight + $blockHeight + $tailHeight;

        $y = (int) max($frameY, $frameY + ($frameH - $totalHeight) / 2);

        $eyebrow = strtoupper(trim(Branding::get('short_name') . ' · ' . Branding::get('badge_text')));
        imagettftext($canvas, 17, 0, $textX, $y, $ink['brand'], $display, $eyebrow);
        $y += $eyebrowHeight;

        foreach ($blocks as $block) {
            $y += $block['gap'];

            foreach ($block['lines'] as $i => $line) {
                imagettftext($canvas, $block['size'], 0, $textX, $y + ($i * $block['leading']), $block['colour'], $block['font'], $line);
            }

            $y += count($block['lines']) * $block['leading'];
        }

        // No email and no address: a share card is broadcast to everyone who
        // sees the link, and neither is needed to know whose profile this is.
        if ($facts) {
            $ruleY = $y + 24;
            imagefilledrectangle($canvas, $textX, $ruleY, $textRight, $ruleY + 1, $ink['rule']);
            imagettftext($canvas, 21, 0, $textX, $ruleY + 46, $ink['muted'], $regular, implode('   ·   ', $facts));
        }

        // Wordmark, bottom left, under the portrait.
        imagettftext($canvas, 18, 0, $frameX, static::HEIGHT - 52, $ink['muted'], $regular,
            (string) Branding::get('site_name'));

        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();

        imagedestroy($canvas);

        return $png ?: null;
    }

    /**
     * The teacher's photograph in an uncropped 5:6 frame, or their initials on a
     * brand tint when there is none — 139 teachers have no photograph.
     */
    protected static function drawPortrait($canvas, Teacher $teacher, int $x, int $y, int $w, int $h, array $ink, string $font): void
    {
        imagefilledrectangle($canvas, $x, $y, $x + $w, $y + $h, $ink['tint']);

        $source = static::photo($teacher);

        if ($source !== null) {
            $sw = imagesx($source);
            $sh = imagesy($source);

            // Cover the frame, and sit the crop high: faces are near the top.
            $scale = max($w / $sw, $h / $sh);
            $cropW = (int) round($w / $scale);
            $cropH = (int) round($h / $scale);
            $srcX = (int) round(($sw - $cropW) / 2);
            $srcY = (int) round(($sh - $cropH) * 0.22);

            imagecopyresampled($canvas, $source, $x, $y, $srcX, $srcY, $w, $h, $cropW, $cropH);
            imagedestroy($source);

            return;
        }

        // Teacher::initials skips the titles stored in the name fields, so
        // "Professor Dr. Md. Asif Nazrul" gives AN rather than PN.
        $initials = $teacher->initials;

        if ($initials === '') {
            return;
        }

        $box = imagettfbbox(120, 0, $font, $initials);
        $textW = $box[2] - $box[0];
        $textH = $box[1] - $box[7];

        imagettftext(
            $canvas, 120, 0,
            (int) ($x + ($w - $textW) / 2),
            (int) ($y + ($h + $textH) / 2),
            $ink['brand'], $font, $initials
        );
    }

    /** @return \GdImage|null */
    protected static function photo(Teacher $teacher)
    {
        // The guarded accessor: this runs inside the network, so an address the
        // legacy import wrote is not automatically safe to request.
        $url = $teacher->serverFetchablePhotoUrl();

        if (blank($url)) {
            return null;
        }

        try {
            $response = Http::timeout(static::PHOTO_TIMEOUT)->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $image = @imagecreatefromstring($response->body());

        return $image ?: null;
    }

    /**
     * A measured block of text: the lines it wraps to, and how to draw them.
     *
     * Returns null for empty text so the caller can filter blocks out and the
     * layout closes up rather than leaving a hole.
     *
     * @return array{lines: array<int, string>, font: string, size: int, leading: int, colour: int, gap: int}|null
     */
    protected static function block(?string $text, string $font, int $size, int $leading, int $colour, int $maxLines, int $maxWidth, int $gap = 0): ?array
    {
        $lines = static::wrap((string) $text, $font, $size, $maxWidth, $maxLines);

        if ($lines === []) {
            return null;
        }

        return compact('lines', 'font', 'size', 'leading', 'colour', 'gap');
    }

    /**
     * Break text into at most $maxLines that fit $maxWidth at this size.
     *
     * Names and department titles are long enough here that a single line would
     * either overflow the card or force a size small enough to lose in a feed.
     *
     * @return array<int, string>
     */
    protected static function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : "{$line} {$word}";
            $box = imagettfbbox($size, 0, $font, $candidate);

            if (($box[2] - $box[0]) > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;

                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $line = $candidate;
            }
        }

        if (count($lines) < $maxLines && $line !== '') {
            $lines[] = $line;
        }

        // Anything that did not fit is cut on the last line rather than dropped
        // silently, so a long name still reads as truncated.
        if (count($lines) === $maxLines) {
            $consumed = implode(' ', $lines);

            if (mb_strlen($consumed) < mb_strlen($text)) {
                $lines[$maxLines - 1] = Str::limit($lines[$maxLines - 1], max(1, mb_strlen($lines[$maxLines - 1]) - 2), '…');
            }
        }

        return $lines;
    }

    /** Allocated colours, taken from the configured palette so the card follows the brand. */
    protected static function palette($canvas): array
    {
        // The palette the administrator configured, so a card matches the site.
        $brand = static::rgb(ColorPalette::resolve()['--color-diu-primary'] ?? '#034ea2');

        return [
            'page' => imagecolorallocate($canvas, 255, 255, 255),
            'brand' => imagecolorallocate($canvas, ...$brand),
            'tint' => imagecolorallocate($canvas,
                (int) round(255 - (255 - $brand[0]) * 0.10),
                (int) round(255 - (255 - $brand[1]) * 0.10),
                (int) round(255 - (255 - $brand[2]) * 0.10)),
            'strong' => imagecolorallocate($canvas, 15, 23, 42),
            'soft' => imagecolorallocate($canvas, 71, 85, 105),
            'muted' => imagecolorallocate($canvas, 100, 116, 139),
            'rule' => imagecolorallocate($canvas, 226, 232, 240),
        ];
    }

    /** @return array{0:int,1:int,2:int} */
    protected static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [3, 78, 162];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function plural(int $count, string $noun): ?string
    {
        return $count > 0
            ? $count . ' ' . $noun . ($count === 1 ? '' : 's')
            : null;
    }
}
