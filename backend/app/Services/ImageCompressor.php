<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an uploaded photo, compressing it first when it is large.
 *
 * A photo of 1 MB or less is stored exactly as uploaded. A larger one (a
 * 2-5 MB phone picture) is re-saved as a JPEG — long side at most 2000px,
 * then lower quality, then smaller — until it is 1 MB or under, so storage
 * and every page that shows it stay light. Nothing in the app displays a
 * photo anywhere near that size.
 *
 * Re-encoding drops a JPEG's EXIF data, including the "this was taken
 * sideways" flag phones rely on, so the rotation is applied to the pixels
 * first. If anything about the compression fails (an unreadable file, not
 * enough memory), the original is stored instead: a large photo is better
 * than a failed save.
 */
class ImageCompressor
{
    /** Stored photos are at most this big. */
    public const MAX_BYTES = 1024 * 1024;

    private const MAX_SIDE = 2000;

    /** Returns the stored path on the disk, like UploadedFile::store(). */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string|false
    {
        if ($file->getSize() <= self::MAX_BYTES) {
            return $file->store($directory, $disk);
        }

        try {
            $jpeg = self::compress($file->getRealPath());
        } catch (\Throwable $e) {
            Log::warning('Image compression failed; storing the original', ['exception' => $e]);
            $jpeg = null;
        }

        if ($jpeg === null || strlen($jpeg) >= $file->getSize()) {
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';

        return Storage::disk($disk)->put($path, $jpeg) ? $path : false;
    }

    /** JPEG bytes of at most MAX_BYTES, or null when the file is not a readable JPEG/PNG. */
    private static function compress(string $path): ?string
    {
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        $source = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (! $source) {
            return null;
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $source = self::upright($source, self::orientation($path));
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $fit = min(1, self::MAX_SIDE / max($width, $height));
        $best = null;

        // Quality first — invisible at the sizes the app shows — then size.
        foreach ([[1.0, 82], [1.0, 70], [1.0, 60], [0.8, 65], [0.65, 62], [0.5, 60], [0.4, 55]] as [$scale, $quality]) {
            $w = max(1, (int) round($width * $fit * $scale));
            $h = max(1, (int) round($height * $fit * $scale));

            $canvas = imagecreatetruecolor($w, $h);
            // JPEG has no transparency: a PNG's clear areas go white, not black.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);

            ob_start();
            imagejpeg($canvas, null, $quality);
            $best = ob_get_clean();
            imagedestroy($canvas);

            if (strlen($best) <= self::MAX_BYTES) {
                break;
            }
        }
        imagedestroy($source);

        return $best;
    }

    /**
     * The EXIF orientation (1-8) of a JPEG; 1 when there is none. Read with
     * the exif extension when it is loaded, otherwise straight from the
     * file's APP1 block — the production image does not ship that extension.
     */
    private static function orientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);

            return (int) ($exif['Orientation'] ?? 1) ?: 1;
        }

        $data = @file_get_contents($path, false, null, 0, 131072);
        if ($data === false || ($pos = strpos($data, "Exif\0\0")) === false) {
            return 1;
        }
        $tiff = substr($data, $pos + 6);
        if (strlen($tiff) < 14) {
            return 1;
        }
        $little = substr($tiff, 0, 2) === 'II';
        $u16 = fn (int $o) => ($v = @unpack($little ? 'v' : 'n', substr($tiff, $o, 2))) ? $v[1] : 0;
        $u32 = fn (int $o) => ($v = @unpack($little ? 'V' : 'N', substr($tiff, $o, 4))) ? $v[1] : 0;

        $ifd = $u32(4);
        $entries = $u16($ifd);
        for ($i = 0; $i < $entries && $i < 64; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /** Turn the pixels the way the EXIF orientation says the photo should be seen. */
    private static function upright(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $degrees = match ($orientation) {
            3 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if ($degrees === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $degrees, 0);

        return $rotated ?: $image;
    }
}
