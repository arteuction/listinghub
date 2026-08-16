<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class ContentBlockConflict extends DomainException
{
    public static function staleVersion(int $expected, int $actual): self
    {
        return new self(
            "Content block version conflict: expected {$expected}, current version is {$actual}."
        );
    }

    public static function revisionNotHistorical(int $targetVersion, int $currentVersion): self
    {
        return new self(
            "Revision {$targetVersion} is not older than current version {$currentVersion}."
        );
    }

    public static function invalidSnapshot(int $version): self
    {
        return new self("Content block revision {$version} contains an invalid snapshot.");
    }
}
