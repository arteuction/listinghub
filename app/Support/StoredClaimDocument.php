<?php

declare(strict_types=1);

namespace App\Support;

/** Metadata of a verified, privately stored claim document. */
final readonly class StoredClaimDocument
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mime,
        public int $sizeBytes,
        public string $sha256,
        public string $originalName,
    ) {}
}
