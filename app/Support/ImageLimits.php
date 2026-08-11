<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Upload limits shared by the request rules and the processor.
 *
 * Lives in Support because both layers need them and Requests may not depend
 * on Services (Deptrac). Keeping one definition means the validation message
 * and the enforced limit cannot drift apart.
 */
final class ImageLimits
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** ~40 MP: a truecolor buffer of this size is roughly 160 MB. */
    public const MAX_PIXELS = 40_000_000;

    /** Longest edge after downscaling. */
    public const MAX_DIMENSION = 2000;

    /** Per listing, across the whole gallery. */
    public const MAX_PER_LISTING = 12;

    /** Per single upload request. */
    public const MAX_PER_REQUEST = 6;

    public static function maxKilobytes(): int
    {
        return intdiv(self::MAX_BYTES, 1024);
    }
}
