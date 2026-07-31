<?php

namespace Tests\Feature;

use App\Support\SignatureImage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A signature photographed on a sheet of paper is mostly paper. The print band
 * uses object-fit:contain, so the blank margin — not the band — is what decides
 * how big the ink comes out. These cover the trim that removes it.
 */
class SignatureTrimTest extends TestCase
{
    /** A $w x $h sheet with a dark mark in the given box. */
    private function sheet(int $w, int $h, ?array $ink, bool $transparent = false): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);

        if ($transparent) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        }

        if ($ink !== null) {
            [$x1, $y1, $x2, $y2] = $ink;
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, imagecolorallocate($img, 10, 10, 90));
        }

        $path = tempnam(sys_get_temp_dir(), 'sig').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return new UploadedFile($path, 'sig.png', 'image/png', null, true);
    }

    private function pngSize(string $png): array
    {
        $img = imagecreatefromstring($png);
        $size = [imagesx($img), imagesy($img)];
        imagedestroy($img);

        return $size;
    }

    public function test_it_crops_a_small_mark_out_of_a_large_sheet(): void
    {
        // Ink occupies 300x100 in the middle of a 2000x1500 photo.
        $png = SignatureImage::trimmed($this->sheet(2000, 1500, [850, 700, 1150, 800]));

        $this->assertNotNull($png);
        [$w, $h] = $this->pngSize($png);

        // Cropped close to the ink, with a little padding — and nothing like
        // the original sheet.
        $this->assertLessThan(450, $w);
        $this->assertLessThan(250, $h);
        $this->assertGreaterThan(280, $w);

        // The ink is now most of the image, which is what makes it print big:
        // before the crop it was 1% of the canvas.
        $this->assertGreaterThan(0.5, (300 * 100) / ($w * $h));
    }

    public function test_it_reads_ink_on_a_transparent_background_too(): void
    {
        $png = SignatureImage::trimmed($this->sheet(1200, 900, [500, 400, 700, 500], true));

        $this->assertNotNull($png);
        [$w, $h] = $this->pngSize($png);
        $this->assertLessThan(320, $w);
        $this->assertLessThan(220, $h);
    }

    public function test_an_already_tight_image_is_left_alone(): void
    {
        // Ink fills the canvas: re-encoding would gain nothing.
        $this->assertNull(SignatureImage::trimmed($this->sheet(400, 150, [0, 0, 399, 149])));
    }

    public function test_a_blank_sheet_is_left_alone(): void
    {
        // No ink to crop to — store what they gave us rather than fail.
        $this->assertNull(SignatureImage::trimmed($this->sheet(600, 400, null)));
    }
}
