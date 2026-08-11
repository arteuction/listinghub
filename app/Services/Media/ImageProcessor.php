<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Exceptions\InvalidImageUpload;
use App\Support\ImageLimits;
use App\Support\ProcessedImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an upload into a safe, stored image.
 *
 * The threat model, and what answers each part of it:
 *
 *  - A file that is not an image, or is an image with something appended
 *    (polyglot / stored payload): the bytes are decoded by GD and RE-ENCODED.
 *    Only pixels survive; appended data, EXIF and embedded scripts do not.
 *  - SVG and other markup formats: getimagesizefromstring() does not recognise
 *    them, so they are rejected before anything else runs.
 *  - Path traversal via the filename: the client name is NEVER used to build a
 *    path. The stored name is a generated UUID with an extension we choose, so
 *    traversal is impossible by construction rather than by sanitising.
 *  - Executable content served from storage: output is always re-encoded WebP
 *    under an extension we control, so there is no .php to serve.
 *  - Decompression bombs: dimensions are read from the header and checked
 *    BEFORE imagecreatefromstring() allocates width * height * 4 bytes.
 */
final class ImageProcessor
{
    public const DISK = 'public';

    private const WEBP_QUALITY = 82;

    /** @var list<int> */
    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function process(UploadedFile $file, string $directory): ProcessedImage
    {
        // getSize() and getRealPath() are both `int|false` / `string|false`;
        // under strict_types a false would be a TypeError downstream.
        $size = (int) $file->getSize();

        if ($size <= 0 || $size > ImageLimits::MAX_BYTES) {
            throw InvalidImageUpload::tooLarge();
        }

        $realPath = (string) $file->getRealPath();

        if ($realPath === '' || ! is_file($realPath)) {
            throw InvalidImageUpload::notAnImage();
        }

        $bytes = @file_get_contents($realPath);

        if ($bytes === false || $bytes === '') {
            throw InvalidImageUpload::notAnImage();
        }

        $image = $this->decode($bytes);

        try {
            $image = $this->downscale($image);

            $encoded = $this->encodeWebp($image);
        } finally {
            // GdImage is garbage-collected in PHP 8, but freeing the large
            // buffer as soon as possible keeps peak memory down under a burst
            // of concurrent uploads.
            unset($image);
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.webp';

        Storage::disk(self::DISK)->put($path, $encoded);

        $dimensions = getimagesizefromstring($encoded);

        return new ProcessedImage(
            disk: self::DISK,
            path: $path,
            mimeType: 'image/webp',
            sizeBytes: strlen($encoded),
            width: $dimensions === false ? 0 : (int) $dimensions[0],
            height: $dimensions === false ? 0 : (int) $dimensions[1],
        );
    }

    /**
     * Reads the header first so an oversized image is refused before GD is
     * asked to allocate a buffer for it.
     */
    private function decode(string $bytes): GdImage
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw InvalidImageUpload::notAnImage();
        }

        // getimagesizefromstring() always fills offsets 0-3 once it has not
        // returned false, so no null-coalescing is needed here.
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        $type = $info[2];

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw InvalidImageUpload::unsupportedType();
        }

        if ($width < 1 || $height < 1 || $width * $height > ImageLimits::MAX_PIXELS) {
            throw InvalidImageUpload::tooLarge();
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw InvalidImageUpload::notAnImage();
        }

        // Palette images cannot carry an alpha channel through resampling.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function downscale(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= ImageLimits::MAX_DIMENSION) {
            return $image;
        }

        $ratio = ImageLimits::MAX_DIMENSION / $longest;
        $scaled = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio));

        if ($scaled === false) {
            return $image; // keep the original rather than fail the upload
        }

        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        return $scaled;
    }

    private function encodeWebp(GdImage $image): string
    {
        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $encoded = (string) ob_get_clean();

        if ($ok === false || $encoded === '') {
            throw InvalidImageUpload::notAnImage();
        }

        return $encoded;
    }
}
