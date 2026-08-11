<?php

declare(strict_types=1);

namespace App\Services\Claims;

use App\Exceptions\InvalidClaimDocument;
use App\Support\StoredClaimDocument;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an ownership-proof upload into a safe, PRIVATELY stored document.
 *
 * The threat model mirrors ImageProcessor, with one addition (PDF):
 *
 *  - Type is decided by CONTENT, never by extension or client MIME: a PDF must
 *    start with the %PDF- magic, an image must decode via getimagesizefromstring.
 *    An executable renamed to .pdf fails both probes and is rejected.
 *  - Images are decoded and RE-ENCODED — only pixels survive; appended
 *    payloads, EXIF and embedded scripts do not.
 *  - PDFs cannot be re-encoded without a heavyweight dependency, so the
 *    compensating controls are: private disk (no direct URL, ever), download
 *    only by staff, always as an attachment, with X-Content-Type-Options:
 *    nosniff — the browser is never given a chance to render it in-origin.
 *  - The stored name is a generated UUID with an extension WE choose; the
 *    client filename is kept only as display metadata for the reviewing admin.
 *  - The SHA-256 of the stored bytes proves later that what an admin downloads
 *    is byte-for-byte what passed this gate.
 */
final class ClaimDocumentProcessor
{
    /** Private local disk — files here are unreachable by URL by construction. */
    public const DISK = 'local';

    public const DIRECTORY = 'claim-documents';

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<int> */
    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

    private const WEBP_QUALITY = 85;

    public function process(UploadedFile $file): StoredClaimDocument
    {
        $size = (int) $file->getSize();

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw InvalidClaimDocument::tooLarge();
        }

        $realPath = (string) $file->getRealPath();

        if ($realPath === '' || ! is_file($realPath)) {
            throw InvalidClaimDocument::notReadable();
        }

        $bytes = @file_get_contents($realPath);

        if ($bytes === false || $bytes === '') {
            throw InvalidClaimDocument::notReadable();
        }

        $originalName = Str::limit($file->getClientOriginalName(), 200, '');

        if (str_starts_with($bytes, '%PDF-')) {
            return $this->storePdf($bytes, $originalName);
        }

        return $this->storeImage($bytes, $originalName);
    }

    private function storePdf(string $bytes, string $originalName): StoredClaimDocument
    {
        return $this->store($bytes, 'pdf', 'application/pdf', $originalName);
    }

    private function storeImage(string $bytes, string $originalName): StoredClaimDocument
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw InvalidClaimDocument::unsupportedType();
        }

        if (! in_array($info[2], self::ALLOWED_IMAGE_TYPES, true)) {
            throw InvalidClaimDocument::unsupportedType();
        }

        // Header dimensions checked BEFORE the pixel buffer is allocated,
        // same decompression-bomb guard as ImageProcessor.
        [$width, $height] = [(int) $info[0], (int) $info[1]];

        if ($width < 1 || $height < 1 || $width * $height > 40_000_000) {
            throw InvalidClaimDocument::tooLarge();
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw InvalidClaimDocument::unsupportedType();
        }

        $encoded = $this->encodeWebp($image);
        unset($image);

        return $this->store($encoded, 'webp', 'image/webp', $originalName);
    }

    private function encodeWebp(GdImage $image): string
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $encoded = (string) ob_get_clean();

        if ($ok === false || $encoded === '') {
            throw InvalidClaimDocument::unsupportedType();
        }

        return $encoded;
    }

    private function store(string $bytes, string $extension, string $mime, string $originalName): StoredClaimDocument
    {
        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(self::DISK)->put($path, $bytes);

        return new StoredClaimDocument(
            disk: self::DISK,
            path: $path,
            mime: $mime,
            sizeBytes: strlen($bytes),
            sha256: hash('sha256', $bytes),
            originalName: $originalName !== '' ? $originalName : 'document.'.$extension,
        );
    }
}
