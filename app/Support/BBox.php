<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Axis-aligned bounding box for a map viewport.
 * Coordinates are WGS-84 decimal degrees.
 * The canonical wire format is "west,south,east,north".
 */
final class BBox
{
    // Bulgaria's geographic envelope with a 0.5° margin for edge listings.
    public const MIN_LNG = 22.0;

    public const MAX_LNG = 29.0;

    public const MIN_LAT = 41.0;

    public const MAX_LAT = 44.5;

    public function __construct(
        public readonly float $west,
        public readonly float $south,
        public readonly float $east,
        public readonly float $north,
    ) {
        if ($west >= $east) {
            throw new InvalidArgumentException('west must be less than east.');
        }
        if ($south >= $north) {
            throw new InvalidArgumentException('south must be less than north.');
        }
    }

    public static function fromString(string $raw): self
    {
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 4) {
            throw new InvalidArgumentException('bbox must be "west,south,east,north".');
        }

        return new self(
            (float) $parts[0],
            (float) $parts[1],
            (float) $parts[2],
            (float) $parts[3],
        );
    }

    public function isWithinBulgaria(): bool
    {
        return $this->west >= self::MIN_LNG
            && $this->east <= self::MAX_LNG
            && $this->south >= self::MIN_LAT
            && $this->north <= self::MAX_LAT;
    }
}
