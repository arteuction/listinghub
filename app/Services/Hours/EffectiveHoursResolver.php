<?php

declare(strict_types=1);

namespace App\Services\Hours;

use App\Models\Listing;
use App\Models\ListingHour;
use App\Models\ListingHourException;
use Carbon\CarbonImmutable;

/**
 * Resolves the EFFECTIVE opening schedule of a listing for a concrete date.
 *
 * Precedence — a date-specific exception always wins over the weekly grid:
 *
 *   1. ListingHourException for that date (closed, or special hours);
 *   2. ListingHour row for that day of week;
 *   3. no row at all → null ("no schedule information"), which is different
 *      from "closed" — a listing that never filled in hours should show
 *      nothing rather than claim to be shut.
 *
 * All "what date is it" decisions use Europe/Sofia: the platform serves
 * Bulgarian businesses, and a UTC server clock crossing midnight two hours
 * before Sofia would otherwise flip "днес" to the wrong day.
 */
final class EffectiveHoursResolver
{
    public const TIMEZONE = 'Europe/Sofia';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * Assumes the listing's `hours` and `hourExceptions` relations are loaded
     * (the public controller eager-loads both); works on the collections so a
     * page render costs no extra queries.
     */
    public function for(Listing $listing, ?CarbonImmutable $date = null): ?EffectiveSchedule
    {
        $date ??= self::today();
        $date = $date->setTimezone(self::TIMEZONE)->startOfDay();

        $exception = $listing->hourExceptions
            ->first(fn (ListingHourException $row): bool => $row->date !== null
                && $row->date->format('Y-m-d') === $date->format('Y-m-d'));

        if ($exception !== null) {
            // is_closed forces NULL times at write time (SyncListingHours /
            // the exception request), but guard again: an exception without
            // both times cannot honestly claim to be open.
            if ($exception->is_closed || $exception->opens_at === null || $exception->closes_at === null) {
                return EffectiveSchedule::closed($date, isException: true, note: $exception->note);
            }

            return EffectiveSchedule::open(
                $date,
                (string) $exception->opens_at,
                (string) $exception->closes_at,
                isException: true,
                note: $exception->note,
            );
        }

        $weekly = $listing->hours
            ->first(fn (ListingHour $row): bool => (int) $row->day_of_week === $date->dayOfWeek);

        if ($weekly === null) {
            return null;
        }

        if ($weekly->is_closed || $weekly->opens_at === null || $weekly->closes_at === null) {
            return EffectiveSchedule::closed($date);
        }

        return EffectiveSchedule::open($date, (string) $weekly->opens_at, (string) $weekly->closes_at);
    }
}
