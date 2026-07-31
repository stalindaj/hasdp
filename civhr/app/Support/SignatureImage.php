<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Signatures arrive as photos or scans: a small squiggle in the middle of a
 * large sheet of paper. The printed form lays the image into a fixed band with
 * object-fit:contain, which fits the WHOLE canvas — so all that blank paper
 * shrinks the ink to a speck no matter how big the band is.
 *
 * Trimming the blank margin at upload time is what actually makes a signature
 * print at a readable size. The stored file becomes a PNG cropped tight to the
 * ink, so the band is filled by signature rather than by paper.
 */
class SignatureImage
{
    /** Ink is anything darker than this (0-255 luminance) once alpha is applied. */
    private const INK_LUMA = 200;

    /** Breathing room kept around the ink, as a fraction of the crop. */
    private const PAD = 0.04;

    /**
     * Store an uploaded signature under $dir, trimmed if that is possible, and
     * return the stored path. Trimming is best-effort: anything unreadable is
     * stored as it arrived.
     */
    public static function store(UploadedFile $file, string $dir): string
    {
        $png = self::trimmed($file);

        if ($png === null) {
            return $file->store($dir);
        }

        $path = $dir.'/'.Str::random(40).'.png';
        Storage::put($path, $png);

        return $path;
    }

    /**
     * Trim the blank margin and return PNG bytes, or null if the image cannot
     * be read, holds no detectable ink, or is already tight. Callers fall back
     * to storing the upload untouched — a signature that prints small is far
     * better than an upload that fails.
     */
    public static function trimmed(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false) {
            return null;
        }

        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return null;
        }

        try {
            $w = imagesx($img);
            $h = imagesy($img);

            // Big phone photos would be millions of pixels to walk in PHP, so
            // the bounding box is found on a sampled grid and the generous pad
            // below covers whatever the sampling steps over.
            $step = max(1, (int) floor(min($w, $h) / 400));

            $minX = $w;
            $minY = $h;
            $maxX = -1;
            $maxY = -1;

            for ($y = 0; $y < $h; $y += $step) {
                for ($x = 0; $x < $w; $x += $step) {
                    if (! self::isInk($img, $x, $y)) {
                        continue;
                    }

                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }

            if ($maxX < 0 || $maxY < 0) {
                return null;   // blank image — nothing to crop to
            }

            $cw = $maxX - $minX + 1;
            $ch = $maxY - $minY + 1;

            $padX = (int) round($cw * self::PAD) + $step;
            $padY = (int) round($ch * self::PAD) + $step;

            $x1 = max(0, $minX - $padX);
            $y1 = max(0, $minY - $padY);
            $x2 = min($w - 1, $maxX + $padX);
            $y2 = min($h - 1, $maxY + $padY);

            $cw = $x2 - $x1 + 1;
            $ch = $y2 - $y1 + 1;

            // Already tight: nothing worth re-encoding.
            if ($cw >= $w * 0.98 && $ch >= $h * 0.98) {
                return null;
            }

            $out = imagecreatetruecolor($cw, $ch);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
            imagecopy($out, $img, 0, 0, $x1, $y1, $cw, $ch);

            ob_start();
            imagepng($out, null, 6);
            $png = ob_get_clean();

            imagedestroy($out);

            return $png ?: null;
        } finally {
            imagedestroy($img);
        }
    }

    /** Dark enough to be ink, and not transparent. */
    private static function isInk(\GdImage $img, int $x, int $y): bool
    {
        $rgba = imagecolorat($img, $x, $y);

        // Alpha is 0 (opaque) to 127 (clear); treat mostly-clear as paper.
        if ((($rgba >> 24) & 0x7F) > 100) {
            return false;
        }

        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < self::INK_LUMA;
    }
}
