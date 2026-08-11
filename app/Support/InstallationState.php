<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Authoritative "is this application installed?" check.
 *
 * The lock file is the single marker. This lives in Support — not on the
 * middleware — so services and providers can consult it without depending on
 * the HTTP layer (enforced by the Deptrac layer boundaries).
 */
final class InstallationState
{
    public static function isInstalled(): bool
    {
        return file_exists(self::lockPath());
    }

    public static function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }
}
