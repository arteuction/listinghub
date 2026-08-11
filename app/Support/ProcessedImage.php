<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The result of re-encoding an upload: what actually landed on disk, not what
 * the client claimed. Every field is derived from the bytes we wrote.
 */
final readonly class ProcessedImage
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mimeType,
        public int $sizeBytes,
        public int $width,
        public int $height,
    ) {}

    /** @return array<string, mixed> */
    public function toMediaAttributes(string $originalName): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            // Kept for display only — never used to build a path.
            'file_name' => $originalName,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'metadata' => ['width' => $this->width, 'height' => $this->height],
        ];
    }
}
