<?php

declare(strict_types=1);

namespace App\Services\Hours;

use Carbon\CarbonImmutable;

/**
 * The resolved opening schedule of a listing for one concrete date.
 *
 * Produced only by EffectiveHoursResolver — the constructor is not meant to
 * be called from controllers or views, which is why the named constructors
 * carry the invariants (a closed day has no times; an open day has both).
 */
final readonly class EffectiveSchedule
{
    private function __construct(
        public CarbonImmutable $date,
        public bool $isClosed,
        public ?string $opensAt,
        public ?string $closesAt,
        /** True when an exception overrode the weekly schedule for this date. */
        public bool $isException,
        public ?string $note,
    ) {}

    public static function closed(CarbonImmutable $date, bool $isException = false, ?string $note = null): self
    {
        return new self($date, true, null, null, $isException, $note);
    }

    public static function open(
        CarbonImmutable $date,
        string $opensAt,
        string $closesAt,
        bool $isException = false,
        ?string $note = null,
    ): self {
        return new self($date, false, self::toDisplayTime($opensAt), self::toDisplayTime($closesAt), $isException, $note);
    }

    /**
     * TIME columns come back as "H:i" on SQLite and "H:i:s" on MySQL/MariaDB;
     * normalise once here so views never see driver-dependent strings.
     */
    private static function toDisplayTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
