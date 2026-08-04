<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * E-signature images attached to one form, per named block — the same idea as
 * the CS Form No. 6 block signatures, shared by IPCR and IWOT.
 *
 * The signatories on those two sheets (NCOIC, SC/CO) are usually military
 * supervisors with no account here, so the image belongs to the form rather
 * than to an account. A signatory who DOES have an account can still fall back
 * to their account e-signature (see `resolve`).
 */
class FormSignatures
{
    /** Store (replacing) the image for one block. Returns the stored path. */
    public static function put(Model $form, string $slot, UploadedFile $file, string $dir): string
    {
        $uploads = $form->signature_uploads ?? [];

        if (! empty($uploads[$slot])) {
            Storage::delete($uploads[$slot]);
        }

        $uploads[$slot] = SignatureImage::store($file, $dir);
        $form->update(['signature_uploads' => $uploads]);

        return $uploads[$slot];
    }

    /** Drop the image from one block. */
    public static function forget(Model $form, string $slot): void
    {
        $uploads = $form->signature_uploads ?? [];

        if (! empty($uploads[$slot])) {
            Storage::delete($uploads[$slot]);
            unset($uploads[$slot]);
            $form->update(['signature_uploads' => $uploads]);
        }
    }

    /** The stored path for one block, or null. */
    public static function path(Model $form, string $slot): ?string
    {
        return ($form->signature_uploads ?? [])[$slot] ?? null;
    }

    public static function has(Model $form, string $slot): bool
    {
        $path = self::path($form, $slot);

        return $path !== null && Storage::exists($path);
    }

    /**
     * The <img src> for a block on the printed sheet: the image uploaded onto
     * this form wins; otherwise the signatory's own account e-signature, if
     * they have an account and one was uploaded there.
     *
     * Paths are returned root-relative so the print view works behind any host
     * or subfolder, with a cache-buster so a replaced signature shows at once.
     */
    public static function resolve(Model $form, string $slot, string $routeName, $account = null): ?string
    {
        if (self::has($form, $slot)) {
            return parse_url(route($routeName, [$form, $slot]), PHP_URL_PATH)
                .'?v='.substr(md5(self::path($form, $slot)), 0, 8);
        }

        if ($account?->signature_path) {
            return parse_url(route('signature.show', $account), PHP_URL_PATH);
        }

        return null;
    }
}
